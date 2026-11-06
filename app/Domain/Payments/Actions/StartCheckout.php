<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\TransitionSource;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Data\CheckoutRequest;
use App\Domain\Payments\Enums\PaymentEventType;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Exceptions\GatewayException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use App\Support\Api\ApiErrorCode;
use App\Support\Api\ApiException;
use App\Support\Logging\OperationLog;
use App\Support\Money;
use App\Support\RequestContext;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Returns a usable checkout for an order: the open attempt if there is one (repeat clicks, refreshes),
 * otherwise a new attempt. The attempt is committed before the gateway call and no transaction spans the call.
 */
final class StartCheckout
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly TransitionPaymentStatus $transition,
        private readonly RequestContext $context,
    ) {}

    public function __invoke(Order $order): Payment
    {
        $payment = $this->reserveAttempt($order);

        return $payment->checkout_url !== null ? $payment : $this->openCheckout($order, $payment);
    }

    private function reserveAttempt(Order $order): Payment
    {
        return DB::transaction(function () use ($order): Payment {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            $this->assertPayable($locked);

            $latest = Payment::query()->where('order_id', $locked->id)->orderByDesc('attempt_number')->first();

            if ($latest !== null && $latest->status === PaymentStatus::Pending && ($latest->expires_at === null || $latest->expires_at->isFuture())) {
                return $latest;
            }

            if ($latest !== null && $latest->status === PaymentStatus::Created) {
                $this->resolveInFlight($latest);
            }

            return $this->createAttempt($locked, $latest);
        });
    }

    private function assertPayable(Order $order): void
    {
        if ($order->status === OrderStatus::Confirmed) {
            throw new ApiException(ApiErrorCode::OrderAlreadyPaid, 'Pesanan ini sudah dibayar.', 409);
        }

        if ($order->status !== OrderStatus::PendingPayment || ($order->expires_at !== null && $order->expires_at->isPast())) {
            throw new ApiException(ApiErrorCode::OrderNotPayable, 'Pesanan ini tidak dapat dibayar.', 409);
        }
    }

    private function resolveInFlight(Payment $latest): void
    {
        if ($latest->created_at->diffInMinutes(now()) < (int) config('platform.stranded_checkout_minutes')) {
            throw new ApiException(ApiErrorCode::CheckoutInProgress, 'Checkout untuk pesanan ini sedang dibuat.', 409);
        }

        // The process died between reserving and calling the gateway; no customer ever received this URL.
        $latest->failure_code = 'CHECKOUT_STRANDED';
        ($this->transition)($latest, PaymentStatus::Failed, TransitionSource::Api);
    }

    private function createAttempt(Order $order, ?Payment $latest): Payment
    {
        $attempt = ($latest->attempt_number ?? 0) + 1;

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'attempt_number' => $attempt,
            'provider' => $this->gateway->name(),
            'status' => PaymentStatus::Created,
            'amount' => $order->total_amount,
            'currency' => $order->currency,
            'gateway_invoice_number' => $attempt === 1 ? $order->invoice_number : "{$order->invoice_number}-{$attempt}",
            'gateway_request_id' => (string) Str::uuid(),
        ]);

        $this->recordEvent($payment, PaymentEventType::AttemptCreated);

        return $payment;
    }

    private function openCheckout(Order $order, Payment $payment): Payment
    {
        $startedAt = microtime(true);
        $order->loadMissing(['application', 'items']);
        $item = $order->items->firstOrFail();
        $due = $this->dueMinutes($order);

        try {
            $session = $this->gateway->createCheckout(new CheckoutRequest(
                requestId: $payment->gateway_request_id,
                invoiceNumber: $payment->gateway_invoice_number,
                amount: new Money($payment->amount, $payment->currency),
                dueInMinutes: $due,
                returnUrl: (string) config('platform.return_urls.'.$order->application->code->value).'?order='.$order->public_id,
                customerName: $order->customer_name,
                customerEmail: $order->customer_email,
                customerPhone: $order->customer_phone,
                itemName: $item->package_name,
                itemCode: $item->package_code,
            ));
        } catch (GatewayException $e) {
            DB::transaction(function () use ($payment, $e): void {
                $payment->failure_code = $e->failureCode;
                ($this->transition)($payment, PaymentStatus::Failed, TransitionSource::Api, metadata: ['failure_code' => $e->failureCode]);
                $this->recordEvent($payment, PaymentEventType::CheckoutFailed, ['failure_code' => $e->failureCode]);
            });

            OperationLog::record('payments.checkout', 'FAILED', ['invoice' => $payment->gateway_invoice_number, 'error_code' => $e->failureCode], $startedAt);

            throw new ApiException(ApiErrorCode::GatewayUnavailable, 'Pembayaran belum dapat dibuat. Silakan coba lagi.', 502);
        }

        return DB::transaction(function () use ($payment, $session, $due, $startedAt): Payment {
            $payment->checkout_url = $session->url;
            $payment->gateway_reference = $session->reference;
            $payment->expires_at = Carbon::instance($session->expiresAt ?? new DateTimeImmutable("+{$due} minutes"));
            ($this->transition)($payment, PaymentStatus::Pending, TransitionSource::Api);
            $this->recordEvent($payment, PaymentEventType::CheckoutOpened);

            OperationLog::record('payments.checkout', 'OPENED', ['invoice' => $payment->gateway_invoice_number], $startedAt);

            return $payment;
        });
    }

    private function dueMinutes(Order $order): int
    {
        $configured = (int) config('platform.payment_due_minutes');

        if ($order->expires_at === null) {
            return $configured;
        }

        return max(1, min($configured, (int) now()->diffInMinutes($order->expires_at)));
    }

    /** @param array<string, mixed>|null $metadata */
    private function recordEvent(Payment $payment, PaymentEventType $type, ?array $metadata = null): void
    {
        PaymentEvent::query()->create([
            'payment_id' => $payment->id,
            'type' => $type,
            'source' => TransitionSource::Api,
            'from_status' => $payment->status,
            'to_status' => $payment->status,
            'metadata' => $metadata,
            'request_id' => $this->context->requestId(),
            'occurred_at' => now(),
        ]);
    }
}
