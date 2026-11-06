<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\TransitionSource;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderStatusHistory;
use App\Domain\Payments\Actions\ApplyPaymentEvidence;
use App\Domain\Payments\Enums\GatewayStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\WebhookStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use App\Domain\Payments\Models\WebhookEvent;
use App\Integrations\Duitku\DuitkuSignature;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

function applyCheckedEvidence(Payment $payment, GatewayStatus $status): void
{
    app(ApplyPaymentEvidence::class)($payment->id, gatewayEvidence($payment->amount, $status), TransitionSource::GatewayCheck);
}

it('marks payment paid and order confirmed from a valid notification', function (): void {
    $payment = pendingPayment();

    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount))->assertOk()->assertJsonPath('data.received', true);

    $payment->refresh();
    $event = WebhookEvent::query()->sole();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->payment_method)->toBe('SP')
        ->and($payment->order->status)->toBe(OrderStatus::Confirmed)
        ->and($event->status)->toBe(WebhookStatus::Processed)
        ->and($event->signature_valid)->toBeTrue()
        ->and($event->payment_id)->toBe($payment->id)
        ->and(AuditLog::query()->where('action', 'payment.paid')->count())->toBe(1);
});

it('does not trust an invalid signature', function (): void {
    $payment = pendingPayment();

    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount), secret: 'wrong-secret')
        ->assertStatus(401)->assertJsonPath('error.code', 'WEBHOOK_REJECTED');

    $event = WebhookEvent::query()->sole();
    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($event->status)->toBe(WebhookStatus::Rejected)
        ->and($event->rejection_reason->value)->toBe('INVALID_SIGNATURE')
        ->and($event->sanitized_payload)->toBeNull()
        ->and($event->invoice_number)->toBeNull();
});

it('rejects a callback altered after signing', function (): void {
    $payment = pendingPayment();
    $signature = DuitkuSignature::sign('fake-key-not-real', 'FAKE-MERCHANT', (string) $payment->amount, $payment->gateway_invoice_number);

    sendNotification(notificationBody($payment->gateway_invoice_number, 1, 'SUCCESS', ['signature' => $signature]))->assertStatus(401);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('rejects a missing signature and a foreign merchant code', function (): void {
    $payment = pendingPayment();

    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount, 'SUCCESS', ['signature' => '']))->assertStatus(401);
    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount), merchantCode: 'OTHER')->assertStatus(401);

    expect(WebhookEvent::query()->pluck('rejection_reason')->map->value->all())->toEqualCanonicalizing(['MISSING_HEADERS', 'CLIENT_MISMATCH'])
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('records an unknown invoice and acknowledges it', function (): void {
    sendNotification(notificationBody('PW-000000-999999', 1000))->assertOk();

    $event = WebhookEvent::query()->sole();
    expect($event->status)->toBe(WebhookStatus::Rejected)->and($event->rejection_reason->value)->toBe('UNKNOWN_INVOICE');
});

it('does not apply a mismatched amount', function (int $delta): void {
    $payment = pendingPayment();

    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount + $delta))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->order->status)->toBe(OrderStatus::PendingPayment)
        ->and($payment->payment_method)->toBeNull()
        ->and(WebhookEvent::query()->sole()->rejection_reason->value)->toBe('AMOUNT_MISMATCH')
        ->and(PaymentEvent::query()->where('type', 'EVIDENCE_REJECTED')->count())->toBe(1);
})->with([-1000, 1000]);

it('produces no duplicate side effect from a duplicate delivery', function (): void {
    $payment = pendingPayment();
    $body = notificationBody($payment->gateway_invoice_number, $payment->amount);

    sendNotification($body)->assertOk();
    $paidAt = $payment->refresh()->paid_at;

    sendNotification($body)->assertOk(); // Same event again, as a Duitku retry is.
    sendNotification($body)->assertOk();

    $events = WebhookEvent::query()->orderBy('id')->get();

    expect($events->pluck('status')->map->value->all())->toBe(['PROCESSED', 'DUPLICATE', 'DUPLICATE'])
        ->and($events[1]->duplicate_of_id)->toBe($events[0]->id)
        ->and(AuditLog::query()->where('action', 'payment.paid')->count())->toBe(1)
        ->and(PaymentEvent::query()->where('type', 'STATUS_CHANGED')->where('to_status', 'PAID')->count())->toBe(1)
        ->and(OrderStatusHistory::query()->where('to_status', 'CONFIRMED')->count())->toBe(1)
        ->and($payment->refresh()->paid_at?->equalTo($paidAt))->toBeTrue();
});

it('ignores a failure callback', function (): void {
    $payment = pendingPayment();

    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount, 'FAILED'))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and(WebhookEvent::query()->sole()->status)->toBe(WebhookStatus::Ignored);
});

describe('out-of-order delivery', function (): void {
    it('keeps PAID when FAILED arrives after SUCCESS', function (): void {
        $payment = pendingPayment();
        sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount))->assertOk();
        sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount, 'FAILED'))->assertOk();

        expect($payment->refresh()->status)->toBe(PaymentStatus::Paid);
    });

    it('keeps PAID when EXPIRED arrives after SUCCESS', function (): void {
        $payment = pendingPayment();
        sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount))->assertOk();
        applyCheckedEvidence($payment, GatewayStatus::Expired);

        expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
            ->and($payment->order->status)->toBe(OrderStatus::Confirmed);
    });

    it('accepts SUCCESS after EXPIRED: verified money beats expiry', function (): void {
        $payment = pendingPayment();
        applyCheckedEvidence($payment, GatewayStatus::Expired);
        expect($payment->refresh()->status)->toBe(PaymentStatus::Expired);

        sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount))->assertOk();

        expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
            ->and($payment->order->status)->toBe(OrderStatus::Confirmed);
    });

    it('confirms an order that expired locally when a late payment arrives', function (): void {
        $payment = pendingPayment(['status' => OrderStatus::Expired]);

        sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount))->assertOk();

        expect($payment->refresh()->order->status)->toBe(OrderStatus::Confirmed);
    });
});

it('leaves a cancelled order alone and records the paid payment for reconciliation', function (): void {
    $payment = pendingPayment(['status' => OrderStatus::Cancelled]);

    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->order->status)->toBe(OrderStatus::Cancelled);
});

it('rejects SUCCESS on a cancelled payment as an invalid transition', function (): void {
    $payment = pendingPayment([], ['status' => PaymentStatus::Cancelled]);

    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and(WebhookEvent::query()->sole()->rejection_reason->value)->toBe('INVALID_TRANSITION');
});

it('surfaces an unsupported status from a check without changing state', function (): void {
    $payment = pendingPayment([], ['status' => PaymentStatus::Paid, 'paid_at' => now()]);

    applyCheckedEvidence($payment, GatewayStatus::Unsupported);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and(PaymentEvent::query()->where('type', 'EVIDENCE_REJECTED')->sole()->metadata['reason'])->toBe('UNSUPPORTED_STATUS');
});

it('stores only allow-listed fields', function (): void {
    $payment = pendingPayment();

    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount, 'SUCCESS', [
        'maskedNumber' => '411111******1111',
        'customerName' => 'Secret Person',
        'merchantUserId' => 'secret@example.test',
        'bankOrderId' => '1900600000000046',
    ]))->assertOk();

    $stored = (string) json_encode(WebhookEvent::query()->sole()->getAttributes());

    expect($stored)->not->toContain('411111')->not->toContain('Secret Person')->not->toContain('secret@example')->not->toContain('1900600000000046')
        ->toContain($payment->gateway_invoice_number);
});

it('acknowledges an authentic but unusable callback without changing anything', function (): void {
    sendNotification(notificationBody(str_repeat('X', 65), 1000))->assertOk();

    expect(WebhookEvent::query()->sole()->rejection_reason->value)->toBe('MALFORMED_PAYLOAD');
});

it('asks Duitku to retry after a processing failure, then succeeds on the retry', function (): void {
    $payment = pendingPayment();
    $body = notificationBody($payment->gateway_invoice_number, $payment->amount);

    $failing = Mockery::mock(ApplyPaymentEvidence::class);
    $failing->shouldReceive('__invoke')->once()->andThrow(new RuntimeException('database went away'));
    $real = app()->make(ApplyPaymentEvidence::class);
    app()->instance(ApplyPaymentEvidence::class, $failing);

    sendNotification($body)->assertStatus(500)->assertJsonPath('error.code', 'INTERNAL_ERROR');
    expect(WebhookEvent::query()->sole()->status)->toBe(WebhookStatus::Failed)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Pending);

    app()->instance(ApplyPaymentEvidence::class, $real);
    sendNotification($body)->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and(WebhookEvent::query()->orderBy('id')->first()->status)->toBe(WebhookStatus::Processed);
});

it('keeps asking Duitku to retry while the retry also fails', function (): void {
    $payment = pendingPayment();
    $body = notificationBody($payment->gateway_invoice_number, $payment->amount);

    $failing = Mockery::mock(ApplyPaymentEvidence::class);
    $failing->shouldReceive('__invoke')->twice()->andThrow(new RuntimeException('database went away'));
    app()->instance(ApplyPaymentEvidence::class, $failing);

    sendNotification($body)->assertStatus(500);
    sendNotification($body)->assertStatus(500);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('rolls back the whole evidence transaction when the order write fails', function (): void {
    $payment = pendingPayment();
    Order::query()->whereKey($payment->order_id)->update(['status' => OrderStatus::PendingPayment]);

    try {
        DB::transaction(function () use ($payment): void {
            app(ApplyPaymentEvidence::class)($payment->id, gatewayEvidence($payment->amount), TransitionSource::GatewayNotification);

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->order->status)->toBe(OrderStatus::PendingPayment)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('enforces database uniqueness', function (): void {
    $payment = pendingPayment();

    expect(fn () => Order::factory()->create(['invoice_number' => $payment->order->invoice_number]))
        ->toThrow(UniqueConstraintViolationException::class);

    expect(fn () => Payment::factory()->create(['gateway_invoice_number' => $payment->gateway_invoice_number]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('keeps evidence tables append-only', function (): void {
    $payment = pendingPayment();
    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount))->assertOk();

    expect(fn () => AuditLog::query()->firstOrFail()->update(['action' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => PaymentEvent::query()->firstOrFail()->delete())->toThrow(LogicException::class);
});
