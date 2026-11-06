<?php

declare(strict_types=1);

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Contracts\NotificationVerifier;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\WebhookStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\WebhookEvent;
use App\Integrations\Duitku\DuitkuConfig;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => useDuitkuSandbox());

it('marks the payment paid only after the check confirms it', function (): void {
    $payment = pendingPayment();
    fakeDuitkuCheck('00', $payment->amount);

    sendDuitkuCallback(duitkuCallback($payment))->assertOk();

    $event = WebhookEvent::query()->sole();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->payment_method)->toBe('SP')
        ->and($payment->order->refresh()->status)->toBe(OrderStatus::Confirmed)
        ->and($event->provider)->toBe('DUITKU')
        ->and($event->status)->toBe(WebhookStatus::Processed)
        ->and($event->signature_valid)->toBeTrue();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['merchantOrderId'] === $payment->gateway_invoice_number);
});

it('does not trust a validly signed success the check does not confirm, and recovers on retry', function (): void {
    $payment = pendingPayment();
    Http::fake([STATUS_URL => Http::sequence()
        ->push(['statusCode' => '01', 'amount' => (string) $payment->amount])
        ->push(['statusCode' => '00', 'amount' => (string) $payment->amount])]);

    sendDuitkuCallback(duitkuCallback($payment))->assertStatus(500);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and(WebhookEvent::query()->sole()->status)->toBe(WebhookStatus::Failed);

    sendDuitkuCallback(duitkuCallback($payment))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid);
});

it('asks for a retry when the check cannot be reached', function (): void {
    $payment = pendingPayment();
    Http::fake([STATUS_URL => Http::response([], 503)]);

    sendDuitkuCallback(duitkuCallback($payment))->assertStatus(500);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('uses the checked amount, not the callback amount', function (): void {
    $payment = pendingPayment();
    fakeDuitkuCheck('00', $payment->amount - 1000);

    sendDuitkuCallback(duitkuCallback($payment))->assertOk();

    $event = WebhookEvent::query()->sole();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($event->status)->toBe(WebhookStatus::Rejected)
        ->and($event->rejection_reason->value)->toBe('AMOUNT_MISMATCH');
});

it('ignores a failure callback without calling the check', function (string $resultCode): void {
    $payment = pendingPayment();
    Http::fake();

    sendDuitkuCallback(duitkuCallback($payment, ['resultCode' => $resultCode]))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and(WebhookEvent::query()->sole()->status)->toBe(WebhookStatus::Ignored);

    Http::assertNothingSent();
})->with(['01', '02']);

it('does not check again for a repeat of a finished callback', function (): void {
    $payment = pendingPayment();
    fakeDuitkuCheck('00', $payment->amount);

    sendDuitkuCallback(duitkuCallback($payment))->assertOk();
    sendDuitkuCallback(duitkuCallback($payment))->assertOk();

    expect(WebhookEvent::query()->where('status', WebhookStatus::Duplicate)->count())->toBe(1);

    Http::assertSentCount(1);
});

it('rejects forged and misdirected callbacks without calling the check', function (array $overrides, string $key, string $ip, string $reason): void {
    $payment = pendingPayment();
    Http::fake();

    sendDuitkuCallback(duitkuCallback($payment, $overrides, $key), $ip)->assertStatus(401);

    $event = WebhookEvent::query()->sole();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($event->signature_valid)->toBeFalse()
        ->and($event->rejection_reason->value)->toBe($reason);

    Http::assertNothingSent();
})->with([
    'wrong key' => [[], 'wrong-key', DUITKU_IP, 'INVALID_SIGNATURE'],
    'signature for another amount' => [['signature' => hash_hmac('sha256', 'DTEST1PW-X', 'unit-key')], 'unit-key', DUITKU_IP, 'INVALID_SIGNATURE'],
    'other merchant' => [['merchantCode' => 'DOTHER'], 'unit-key', DUITKU_IP, 'CLIENT_MISMATCH'],
    'missing signature' => [['signature' => ''], 'unit-key', DUITKU_IP, 'MISSING_HEADERS'],
    'unlisted address' => [[], 'unit-key', '203.0.113.9', 'SOURCE_NOT_ALLOWED'],
]);

it('accepts any address when the allow-list is turned off', function (): void {
    config(['duitku.enforce_callback_ip' => false]);
    app()->forgetInstance(DuitkuConfig::class);
    app()->forgetInstance(NotificationVerifier::class);

    $payment = pendingPayment();
    fakeDuitkuCheck('00', $payment->amount);

    sendDuitkuCallback(duitkuCallback($payment), '203.0.113.9')->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid);
});

it('never validates anything when unconfigured', function (): void {
    config(['duitku.merchant_code' => '', 'duitku.api_key' => '']);
    app()->forgetInstance(DuitkuConfig::class);
    app()->forgetInstance(NotificationVerifier::class);

    $payment = pendingPayment();
    Http::fake();

    sendDuitkuCallback(duitkuCallback($payment, ['merchantCode' => 'DTEST'], ''))->assertStatus(401);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('acknowledges an unknown invoice without calling the check', function (): void {
    $payment = pendingPayment();
    Http::fake();

    sendDuitkuCallback(duitkuCallback($payment, ['merchantOrderId' => 'PW-UNKNOWN']))->assertOk();

    expect(WebhookEvent::query()->sole()->rejection_reason->value)->toBe('UNKNOWN_INVOICE');

    Http::assertNothingSent();
});

it('stores only allow-listed fields', function (): void {
    $payment = pendingPayment();
    fakeDuitkuCheck('00', $payment->amount);

    sendDuitkuCallback(duitkuCallback($payment, ['maskedNumber' => '411111******1111']))->assertOk();

    $stored = (string) json_encode(WebhookEvent::query()->sole()->sanitized_payload);

    expect($stored)->toContain('DTESTREF1')
        ->not->toContain('Budi')
        ->not->toContain('411111')
        ->not->toContain('budi@example.test');
});

it('sends the customer back through the configured return page', function (): void {
    Http::fake(['api-sandbox.duitku.com/*' => Http::response([
        'statusCode' => '00', 'reference' => 'DTESTREF1', 'paymentUrl' => 'https://app-sandbox.duitku.com/redirect_checkout?reference=DTESTREF1',
    ])]);

    $order = Order::factory()->create();

    test()->postJson("/api/v1/orders/{$order->public_id}/checkout", [], ['Idempotency-Key' => 'duitku-1'])
        ->assertCreated()
        ->assertJsonPath('data.checkout_url', 'https://app-sandbox.duitku.com/redirect_checkout?reference=DTESTREF1');

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request['returnUrl'], "?order={$order->public_id}")
        && str_ends_with((string) $request['callbackUrl'], '/api/v1/webhooks/duitku'));

    expect(Payment::query()->sole()->provider)->toBe('DUITKU');
});
