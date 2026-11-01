<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\TransitionSource;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderStatusHistory;
use App\Domain\Payments\Actions\ApplyPaymentEvidence;
use App\Domain\Payments\Contracts\NotificationVerifier;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\WebhookStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use App\Domain\Payments\Models\WebhookEvent;
use App\Integrations\Doku\DokuConfig;
use App\Integrations\Doku\DokuPayload;
use App\Integrations\Doku\DokuSignature;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('marks payment paid and order confirmed from a valid notification', function (): void {
    $payment = pendingPayment();

    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount))->assertOk()->assertJsonPath('data.received', true);

    $payment->refresh();
    $event = WebhookEvent::query()->sole();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->paid_at?->toIso8601ZuluString())->toBe('2026-11-01T03:24:23Z')
        ->and($payment->payment_method)->toBe('VIRTUAL_ACCOUNT_BCA')
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

it('rejects a body altered after signing', function (): void {
    $payment = pendingPayment();
    $raw = (string) json_encode(notificationBody($payment->gateway_invoice_number, $payment->amount));
    $timestamp = gmdate('Y-m-d\TH:i:s\Z');
    $signature = DokuSignature::sign('test-secret-key', 'TEST-CLIENT', 'r1', $timestamp, '/api/v1/webhooks/doku', $raw);

    $this->call('POST', '/api/v1/webhooks/doku', server: [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_CLIENT_ID' => 'TEST-CLIENT', 'HTTP_REQUEST_ID' => 'r1',
        'HTTP_REQUEST_TIMESTAMP' => $timestamp, 'HTTP_SIGNATURE' => $signature,
    ], content: str_replace((string) $payment->amount, '1', $raw))->assertStatus(401);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('rejects missing headers and a foreign client id', function (): void {
    $payment = pendingPayment();

    $this->postJson('/api/v1/webhooks/doku', notificationBody($payment->gateway_invoice_number, $payment->amount))->assertStatus(401);
    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount), clientId: 'OTHER')->assertStatus(401);

    expect(WebhookEvent::query()->pluck('rejection_reason')->map->value->all())->toEqualCanonicalizing(['MISSING_HEADERS', 'CLIENT_MISMATCH'])
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('cannot be satisfied with an empty secret when unconfigured', function (): void {
    $payment = pendingPayment();
    config(['doku.client_id' => '', 'doku.secret_key' => '']);
    app()->forgetInstance(DokuConfig::class);
    app()->forgetInstance(NotificationVerifier::class);

    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount), secret: '', clientId: '')->assertStatus(401);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
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

    sendNotification($body)->assertOk(); // New Request-Id, same event, as a DOKU retry may be.
    sendNotification($body)->assertOk();

    $events = WebhookEvent::query()->orderBy('id')->get();

    expect($events->pluck('status')->map->value->all())->toBe(['PROCESSED', 'DUPLICATE', 'DUPLICATE'])
        ->and($events[1]->duplicate_of_id)->toBe($events[0]->id)
        ->and(AuditLog::query()->where('action', 'payment.paid')->count())->toBe(1)
        ->and(PaymentEvent::query()->where('type', 'STATUS_CHANGED')->where('to_status', 'PAID')->count())->toBe(1)
        ->and(OrderStatusHistory::query()->where('to_status', 'CONFIRMED')->count())->toBe(1)
        ->and($payment->refresh()->paid_at?->equalTo($paidAt))->toBeTrue();
});

it('treats a replay of the identical signed request as a duplicate', function (): void {
    $payment = pendingPayment();
    $body = notificationBody($payment->gateway_invoice_number, $payment->amount);

    sendNotification($body, requestId: 'same', timestamp: '2026-11-01T03:24:30Z')->assertOk();
    sendNotification($body, requestId: 'same', timestamp: '2026-11-01T03:24:30Z')->assertOk();

    expect(WebhookEvent::query()->where('status', WebhookStatus::Duplicate)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'payment.paid')->count())->toBe(1);
});

it('rejects stale timestamps only when the age check is enabled', function (): void {
    $payment = pendingPayment();
    config(['doku.notification_max_age_seconds' => 300]);
    app()->forgetInstance(DokuConfig::class);
    app()->forgetInstance(NotificationVerifier::class);

    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount), timestamp: gmdate('Y-m-d\TH:i:s\Z', time() - 3600))->assertStatus(401);

    expect(WebhookEvent::query()->sole()->rejection_reason->value)->toBe('STALE_TIMESTAMP')
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('ignores FAILED on Checkout, per the DOKU contract', function (): void {
    $payment = pendingPayment();

    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount, 'FAILED'))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and(WebhookEvent::query()->sole()->status)->toBe(WebhookStatus::Ignored);
});

describe('out-of-order delivery', function (): void {
    it('keeps PAID when FAILED arrives after SUCCESS', function (): void {
        $payment = pendingPayment();
        sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount))->assertOk();
        sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount, 'FAILED', ['transaction' => ['date' => '2026-11-01T02:00:00Z']]))->assertOk();

        expect($payment->refresh()->status)->toBe(PaymentStatus::Paid);
    });

    it('keeps PAID when EXPIRED arrives after SUCCESS', function (): void {
        $payment = pendingPayment();
        sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount))->assertOk();
        sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount, 'EXPIRED'))->assertOk();

        expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
            ->and($payment->order->status)->toBe(OrderStatus::Confirmed);
    });

    it('accepts SUCCESS after EXPIRED: verified money beats expiry', function (): void {
        $payment = pendingPayment();
        sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount, 'EXPIRED'))->assertOk();
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

it('surfaces REFUNDED without changing state', function (): void {
    $payment = pendingPayment([], ['status' => PaymentStatus::Paid, 'paid_at' => now()]);

    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount, 'REFUNDED'))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and(WebhookEvent::query()->sole()->rejection_reason->value)->toBe('UNSUPPORTED_STATUS');
});

it('stores only allow-listed fields', function (): void {
    $payment = pendingPayment();

    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount, 'SUCCESS', [
        'card_payment' => ['card_masked' => '411111******1111'],
        'customer' => ['name' => 'Secret Person', 'email' => 'secret@example.test'],
        'virtual_account_info' => ['virtual_account_number' => '1900600000000046'],
    ]))->assertOk();

    $stored = (string) json_encode(WebhookEvent::query()->sole()->getAttributes());

    expect($stored)->not->toContain('411111')->not->toContain('Secret Person')->not->toContain('secret@example')->not->toContain('1900600000000046')->not->toContain('HMACSHA256')
        ->toContain($payment->gateway_invoice_number);
});

it('acknowledges an authentic but malformed body without changing anything', function (): void {
    $raw = 'not json';
    $timestamp = gmdate('Y-m-d\TH:i:s\Z');

    $this->call('POST', '/api/v1/webhooks/doku', server: [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_CLIENT_ID' => 'TEST-CLIENT', 'HTTP_REQUEST_ID' => 'r2',
        'HTTP_REQUEST_TIMESTAMP' => $timestamp,
        'HTTP_SIGNATURE' => DokuSignature::sign('test-secret-key', 'TEST-CLIENT', 'r2', $timestamp, '/api/v1/webhooks/doku', $raw),
    ], content: $raw)->assertOk();

    expect(WebhookEvent::query()->sole()->rejection_reason->value)->toBe('MALFORMED_PAYLOAD');
});

it('asks DOKU to retry after a processing failure, then succeeds on the retry', function (): void {
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

it('keeps asking DOKU to retry while the retry also fails', function (): void {
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
            app(ApplyPaymentEvidence::class)($payment->id, DokuPayload::evidence(notificationBody('x', $payment->amount)), TransitionSource::GatewayNotification);

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
