<?php

declare(strict_types=1);

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Integrations\Duitku\Exceptions\DuitkuException;
use Illuminate\Testing\TestResponse;

function checkout(Order $order, string $key = 'c-1'): TestResponse
{
    return test()->postJson("/api/v1/orders/{$order->public_id}/checkout", [], ['Idempotency-Key' => $key]);
}

it('opens a checkout and persists the attempt first', function (): void {
    $order = Order::factory()->create(['invoice_number' => 'PW-202611-000001']);

    checkout($order)->assertCreated()
        ->assertJsonPath('data.payment_status', 'PENDING')
        ->assertJsonPath('data.checkout_url', url('/dev/fake-checkout/PW-202611-000001'));

    $payment = Payment::query()->sole();
    expect($payment->attempt_number)->toBe(1)
        ->and($payment->gateway_invoice_number)->toBe('PW-202611-000001')
        ->and($payment->amount)->toBe($order->total_amount)
        ->and($payment->events()->pluck('type')->map->value->all())->toContain('ATTEMPT_CREATED', 'CHECKOUT_OPENED');
    expect(fakeGateway()->checkouts[0]->amount->amount)->toBe($order->total_amount);
});

it('requires an idempotency key', function (): void {
    $order = Order::factory()->create();
    $this->postJson("/api/v1/orders/{$order->public_id}/checkout")->assertStatus(400)->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');
});

it('makes one gateway call for repeat clicks, even with new keys', function (): void {
    $order = Order::factory()->create();

    $first = checkout($order, 'a')->json('data.payment_reference');
    expect(checkout($order, 'a')->assertJsonPath('meta.idempotentReplay', true)->json('data.payment_reference'))->toBe($first)
        ->and(checkout($order, 'b')->json('data.payment_reference'))->toBe($first)
        ->and(fakeGateway()->checkouts)->toHaveCount(1)
        ->and(Payment::query()->count())->toBe(1);
});

it('records a gateway failure and allows a new attempt', function (): void {
    $order = Order::factory()->create(['invoice_number' => 'PW-202611-000002']);
    fakeGateway()->failNextWith = new DuitkuException('down', 'DUITKU_HTTP_500');

    checkout($order)->assertStatus(502)->assertJsonPath('error.code', 'GATEWAY_UNAVAILABLE');

    $failed = Payment::query()->sole();
    expect($failed->status)->toBe(PaymentStatus::Failed)->and($failed->failure_code)->toBe('DUITKU_HTTP_500');

    checkout($order)->assertCreated();
    $retry = Payment::query()->where('attempt_number', 2)->sole();
    expect($retry->gateway_invoice_number)->toBe('PW-202611-000002-2');
});

it('refuses paid, expired, and cancelled orders', function (OrderStatus $status, string $code): void {
    checkout(Order::factory()->create(['status' => $status]))->assertStatus(409)->assertJsonPath('error.code', $code);
    expect(fakeGateway()->checkouts)->toBeEmpty();
})->with([
    [OrderStatus::Confirmed, 'ORDER_ALREADY_PAID'],
    [OrderStatus::Expired, 'ORDER_NOT_PAYABLE'],
    [OrderStatus::Cancelled, 'ORDER_NOT_PAYABLE'],
]);

it('refuses an order past its expiry time', function (): void {
    checkout(Order::factory()->create(['expires_at' => now()->subMinute()]))->assertStatus(409)->assertJsonPath('error.code', 'ORDER_NOT_PAYABLE');
});

it('reports an in-flight attempt, then recovers one that was stranded', function (): void {
    $order = Order::factory()->create();
    $payment = Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Created, 'checkout_url' => null]);

    checkout($order, 'x')->assertStatus(409)->assertJsonPath('error.code', 'CHECKOUT_IN_PROGRESS');

    $payment->forceFill(['created_at' => now()->subMinutes(10)])->save();
    checkout($order, 'y')->assertCreated();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_code)->toBe('CHECKOUT_STRANDED');
});

it('does not hand back an expired checkout URL on replay', function (): void {
    $order = Order::factory()->create();
    checkout($order, 'r')->assertCreated();
    Payment::query()->sole()->update(['status' => PaymentStatus::Expired]);

    checkout($order, 'r')->assertJsonPath('meta.idempotentReplay', true)->assertJsonPath('data.checkout_url', null)->assertJsonPath('data.payment_status', 'EXPIRED');
});

it('reports payment status and only exposes a usable checkout URL', function (): void {
    $payment = pendingPayment();

    $this->getJson("/api/v1/orders/{$payment->order->public_id}/payment-status")
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'PENDING')
        ->assertJsonPath('data.checkout_url', $payment->checkout_url);

    $payment->update(['status' => PaymentStatus::Expired]);

    $this->getJson("/api/v1/orders/{$payment->order->public_id}/payment-status")->assertJsonPath('data.checkout_url', null);
});
