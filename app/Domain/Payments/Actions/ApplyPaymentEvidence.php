<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Orders\Actions\TransitionOrderStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\TransitionSource;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Data\EvidenceOutcome;
use App\Domain\Payments\Data\GatewayEvidence;
use App\Domain\Payments\Enums\GatewayStatus;
use App\Domain\Payments\Enums\PaymentEventType;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\WebhookRejection;
use App\Domain\Payments\Enums\WebhookStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use App\Support\RequestContext;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Turns verified gateway evidence into state. Both the notification handler and the
 * status check go through here, so there is exactly one path to PAID.
 */
class ApplyPaymentEvidence
{
    public function __construct(
        private readonly TransitionPaymentStatus $transitionPayment,
        private readonly TransitionOrderStatus $transitionOrder,
        private readonly RecordAudit $audit,
        private readonly RequestContext $context,
    ) {}

    public function __invoke(int $paymentId, GatewayEvidence $evidence, TransitionSource $source, ?int $webhookEventId = null, ?User $actor = null): EvidenceOutcome
    {
        return DB::transaction(function () use ($paymentId, $evidence, $source, $webhookEventId, $actor): EvidenceOutcome {
            $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);

            return match ($evidence->status) {
                GatewayStatus::Success => $this->applySuccess($payment, $order, $evidence, $source, $webhookEventId, $actor),
                GatewayStatus::Expired => $this->applyOpenOnly($payment, PaymentStatus::Expired, $evidence, $source, $webhookEventId),
                GatewayStatus::Pending => $this->applyOpenOnly($payment, PaymentStatus::Pending, $evidence, $source, $webhookEventId),
                // DOKU contract: Checkout integrations must ignore FAILED, the customer can still switch method.
                GatewayStatus::Failed, GatewayStatus::NotFinal => $this->ignore($payment, $evidence, $source, $webhookEventId),
                GatewayStatus::Unsupported => $this->reject($payment, WebhookRejection::UnsupportedStatus, $evidence, $source, $webhookEventId),
            };
        });
    }

    private function applySuccess(Payment $payment, Order $order, GatewayEvidence $evidence, TransitionSource $source, ?int $webhookEventId, ?User $actor): EvidenceOutcome
    {
        if ($evidence->amount !== $payment->amount) {
            return $this->reject($payment, WebhookRejection::AmountMismatch, $evidence, $source, $webhookEventId);
        }

        if ($payment->status === PaymentStatus::Paid) {
            return $this->ignore($payment, $evidence, $source, $webhookEventId);
        }

        if (! $payment->status->canTransitionTo(PaymentStatus::Paid)) {
            return $this->reject($payment, WebhookRejection::InvalidTransition, $evidence, $source, $webhookEventId);
        }

        $previous = ['payment_status' => $payment->status->value, 'order_status' => $order->status->value];
        $payment->payment_method ??= $evidence->channel;

        ($this->transitionPayment)($payment, PaymentStatus::Paid, $source, $webhookEventId, $evidence->occurredAt ?? new DateTimeImmutable, ['channel' => $evidence->channel]);

        // A paid payment against a cancelled or already confirmed order stays visible in reconciliation; it is not repaired here.
        if ($order->status->canTransitionTo(OrderStatus::Confirmed)) {
            ($this->transitionOrder)($order, OrderStatus::Confirmed, $source, $actor?->id);
        }

        ($this->audit)('payment.paid', 'payment', $payment->public_id, $actor, $previous, [
            'payment_status' => $payment->status->value,
            'order_status' => $order->status->value,
            'source' => $source->value,
        ]);

        return new EvidenceOutcome(WebhookStatus::Processed);
    }

    private function applyOpenOnly(Payment $payment, PaymentStatus $to, GatewayEvidence $evidence, TransitionSource $source, ?int $webhookEventId): EvidenceOutcome
    {
        // PENDING or EXPIRED arriving after a later state is out-of-order, not an error.
        if (! $payment->status->isOpen() || ! $payment->status->canTransitionTo($to)) {
            return $this->ignore($payment, $evidence, $source, $webhookEventId);
        }

        $payment->payment_method ??= $evidence->channel;
        ($this->transitionPayment)($payment, $to, $source, $webhookEventId, $evidence->occurredAt);

        return new EvidenceOutcome(WebhookStatus::Processed);
    }

    private function ignore(Payment $payment, GatewayEvidence $evidence, TransitionSource $source, ?int $webhookEventId): EvidenceOutcome
    {
        $this->recordEvent($payment, PaymentEventType::EvidenceIgnored, $evidence, $source, $webhookEventId);

        return new EvidenceOutcome(WebhookStatus::Ignored);
    }

    private function reject(Payment $payment, WebhookRejection $reason, GatewayEvidence $evidence, TransitionSource $source, ?int $webhookEventId): EvidenceOutcome
    {
        $this->recordEvent($payment, PaymentEventType::EvidenceRejected, $evidence, $source, $webhookEventId, ['reason' => $reason->value]);

        return new EvidenceOutcome(WebhookStatus::Rejected, $reason);
    }

    /** @param array<string, mixed> $extra */
    private function recordEvent(Payment $payment, PaymentEventType $type, GatewayEvidence $evidence, TransitionSource $source, ?int $webhookEventId, array $extra = []): void
    {
        PaymentEvent::query()->create([
            'payment_id' => $payment->id,
            'webhook_event_id' => $webhookEventId,
            'type' => $type,
            'source' => $source,
            'from_status' => $payment->status,
            'to_status' => $payment->status,
            'metadata' => ['gateway_status' => $evidence->rawStatus, 'amount' => $evidence->amount, ...$extra],
            'request_id' => $this->context->requestId(),
            'occurred_at' => $evidence->occurredAt ?? now(),
        ]);
    }
}
