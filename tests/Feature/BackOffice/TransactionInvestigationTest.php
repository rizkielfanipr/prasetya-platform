<?php

declare(strict_types=1);

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\AdminNote;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Enums\GatewayStatus;
use App\Domain\Payments\Enums\PaymentEventType;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Integrations\Duitku\Exceptions\DuitkuException;
use Illuminate\Support\Facades\Cache;

/* ADR-0010, phase 1: one transaction can be investigated and checked from its own detail page. */

/** @return array<string, mixed> */
function detailProps(Order $order): array
{
    return test()->actingAs(admin())->get("/transactions/{$order->public_id}")->assertOk()->viewData('page')['props'];
}

it('lists only the open reconciliation issues of this transaction', function (): void {
    $mine = Order::factory()->create(['status' => OrderStatus::PendingPayment, 'created_at' => now()->subHours(2)]);
    $other = Order::factory()->create(['status' => OrderStatus::PendingPayment, 'created_at' => now()->subHours(2)]);
    sendNotification(notificationBody('PW-209901-000001', 1000))->assertOk(); // Unknown invoice: belongs to no order.

    $issues = detailProps($mine)['reconciliationIssues'];

    expect($issues)->toHaveCount(1)
        ->and($issues[0])->toMatchArray(['group' => 'orders_without_attempt', 'orderId' => $mine->public_id, 'needsAction' => true])
        ->and(collect($issues)->pluck('orderId')->all())->not->toContain($other->public_id)
        ->and(collect($issues)->pluck('invoice')->all())->not->toContain('PW-209901-000001');
});

it('flags a payment pending too long and offers a check on it', function (): void {
    $payment = pendingPayment(['created_at' => now()->subHours(3)], ['created_at' => now()->subHours(3)]);

    $issues = collect(detailProps($payment->order)['reconciliationIssues']);
    $pending = $issues->firstWhere('group', 'pending_too_long');

    expect($pending)->not->toBeNull()
        ->and($pending['checkablePaymentId'])->toBe($payment->public_id)
        ->and($issues->firstWhere('group', 'orders_without_success'))->not->toBeNull();
});

it('shows a duplicate delivery as information, not as an issue needing action', function (): void {
    $payment = pendingPayment();
    $body = notificationBody($payment->gateway_invoice_number, $payment->amount);
    sendNotification($body)->assertOk();
    sendNotification($body)->assertOk();

    $duplicate = collect(detailProps($payment->order)['reconciliationIssues'])->firstWhere('group', 'duplicate_webhooks');

    expect($duplicate)->not->toBeNull()->and($duplicate['needsAction'])->toBeFalse();
});

it('builds one newest-first timeline from every source of this transaction only', function (): void {
    $payment = pendingPayment();
    $order = $payment->order;
    $other = pendingPayment();
    $author = admin();

    $this->travel(1)->seconds();
    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount))->assertOk();
    $this->travel(1)->seconds();
    AdminNote::query()->create(['order_id' => $order->id, 'author_user_id' => $author->id, 'body' => 'Pelanggan menelepon']);
    AdminNote::query()->create(['order_id' => $other->order->id, 'author_user_id' => $author->id, 'body' => 'Bukan milik pesanan ini']);
    app(RecordAudit::class)('order.note_added', 'order', $other->order->public_id, $author);

    $activity = collect(detailProps($order)['activity']);

    expect($activity->pluck('source')->unique()->sort()->values()->all())->toBe(['audit', 'note', 'order_status', 'payment_event', 'webhook'])
        ->and($activity->pluck('at')->all())->toBe($activity->pluck('at')->sortDesc()->values()->all())
        ->and($activity->pluck('data.body')->filter()->values()->all())->toBe(['Pelanggan menelepon'])
        ->and($activity->where('source', 'audit')->pluck('data.action')->all())->not->toContain('order.note_added')
        ->and($activity->pluck('id')->unique())->toHaveCount($activity->count());
});

it('keeps the same order for entries at the same instant', function (): void {
    $payment = pendingPayment();
    $this->freezeTime();
    foreach (range(1, 3) as $n) {
        AdminNote::query()->create(['order_id' => $payment->order_id, 'author_user_id' => admin()->id, 'body' => "Catatan {$n}"]);
    }

    $first = collect(detailProps($payment->order)['activity'])->pluck('id')->all();
    $second = collect(detailProps($payment->order)['activity'])->pluck('id')->all();

    expect($first)->toBe($second);
});

it('sends no raw payload, signature, or secret to the detail page', function (): void {
    $payment = pendingPayment();
    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount, 'SUCCESS', ['maskedNumber' => '411111******1111']))->assertOk();

    $content = $this->actingAs(admin())->get("/transactions/{$payment->order->public_id}")->getContent();

    expect($content)->not->toContain('411111')->not->toContain('fake-key-not-real')->not->toContain('"signature"');
});

it('marks which attempts can be checked and shows the last check', function (): void {
    $pending = pendingPayment();
    $paid = Payment::factory()->create(['order_id' => $pending->order_id, 'attempt_number' => 2, 'gateway_invoice_number' => $pending->order->invoice_number.'-2', 'status' => PaymentStatus::Paid]);
    fakeGateway()->statusResult = gatewayEvidence($pending->amount, GatewayStatus::Pending);

    $this->actingAs(admin())->post("/reconciliation/payments/{$pending->public_id}/check-status", ['reason' => 'cek dari detail']);

    $payments = collect(detailProps($pending->order)['transaction']['payments'])->keyBy('id');
    expect($payments[$pending->public_id]['checkable'])->toBeTrue()
        ->and($payments[$pending->public_id]['lastGatewayCheck'])->toMatchArray(['gatewayStatus' => 'PENDING'])
        ->and($payments[$paid->public_id]['checkable'])->toBeFalse()
        ->and($payments[$paid->public_id]['lastGatewayCheck'])->toBeNull();
});

it('runs Cek status from the detail page through the same domain action, back to the detail', function (?GatewayStatus $status, int $amountDelta, string $flash, PaymentStatus $after): void {
    $payment = pendingPayment();
    $detail = "/transactions/{$payment->order->public_id}";
    fakeGateway()->statusResult = $status === null ? null : gatewayEvidence($payment->amount + $amountDelta, $status);
    Cache::put('reconciliation.open-issues', 99, 60);

    $this->actingAs(admin())->from($detail)->post("/reconciliation/payments/{$payment->public_id}/check-status", ['reason' => 'uji'])
        ->assertRedirect($detail)
        ->assertSessionHas('success', $flash);

    expect($payment->refresh()->status)->toBe($after)
        ->and(Cache::has('reconciliation.open-issues'))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'payment.gateway_checked')->where('entity_id', $payment->public_id)->sole()->reason)->toBe('uji')
        ->and($payment->events()->where('type', PaymentEventType::GatewayChecked)->count())->toBe(1);

    $audit = collect(detailProps($payment->order)['activity'])->where('source', 'audit')->pluck('data.action')->all();
    expect($audit)->toContain('payment.gateway_checked');
})->with([
    'paid' => [GatewayStatus::Success, 0, 'Status diperbarui dari bukti gateway.', PaymentStatus::Paid],
    'still pending' => [GatewayStatus::Pending, 0, 'Gateway belum melaporkan perubahan. Tidak ada data yang berubah.', PaymentStatus::Pending],
    'no record' => [null, 0, 'Gateway belum memiliki transaksi ini. Tidak ada data yang berubah.', PaymentStatus::Pending],
    'amount mismatch' => [GatewayStatus::Success, -1, 'Bukti gateway ditolak (AMOUNT_MISMATCH). Tidak ada data yang berubah.', PaymentStatus::Pending],
]);

it('reports a gateway outage from the detail page without changing anything', function (): void {
    $payment = pendingPayment();
    $detail = "/transactions/{$payment->order->public_id}";
    $this->mock(PaymentGateway::class)->shouldReceive('checkStatus')->andThrow(new DuitkuException('down', 'DUITKU_HTTP_503'));

    $this->actingAs(admin())->from($detail)->post("/reconciliation/payments/{$payment->public_id}/check-status")
        ->assertRedirect($detail)
        ->assertSessionHas('error', 'Status gateway belum dapat diperiksa (DUITKU_HTTP_503). Tidak ada data yang berubah.');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and(AuditLog::query()->where('action', 'payment.gateway_checked')->exists())->toBeFalse();
});

it('shows an empty payment section for an order that never reached checkout', function (): void {
    $order = Order::factory()->create();

    $props = detailProps($order);

    expect($props['transaction']['payments'])->toBe([])
        ->and($props['webhooks'])->toBe([])
        ->and(collect($props['activity'])->whereIn('source', ['payment_event', 'webhook'])->all())->toBe([]);
});
