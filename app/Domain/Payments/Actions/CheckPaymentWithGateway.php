<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Orders\Enums\TransitionSource;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Data\EvidenceOutcome;
use App\Domain\Payments\Enums\PaymentEventType;
use App\Domain\Payments\Exceptions\GatewayException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use App\Support\RequestContext;

/**
 * Admin-triggered status check. It cannot assert anything itself: the gateway's answer goes through
 * the same evidence path as a notification, so an administrator can never simply mark a payment paid.
 */
final class CheckPaymentWithGateway
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ApplyPaymentEvidence $applyEvidence,
        private readonly RecordAudit $audit,
        private readonly RequestContext $context,
    ) {}

    /** @throws GatewayException */
    public function __invoke(Payment $payment, User $actor, ?string $reason): ?EvidenceOutcome
    {
        $before = $payment->status->value;
        $evidence = $this->gateway->checkStatus($payment->gateway_invoice_number);

        PaymentEvent::query()->create([
            'payment_id' => $payment->id,
            'type' => PaymentEventType::GatewayChecked,
            'source' => TransitionSource::GatewayCheck,
            'from_status' => $payment->status,
            'to_status' => $payment->status,
            'metadata' => ['gateway_status' => $evidence->rawStatus ?? 'NOT_FOUND', 'actor' => $actor->public_id],
            'request_id' => $this->context->requestId(),
            'occurred_at' => now(),
        ]);

        $outcome = $evidence === null ? null : ($this->applyEvidence)($payment->id, $evidence, TransitionSource::GatewayCheck, null, $actor);

        ($this->audit)('payment.gateway_checked', 'payment', $payment->public_id, $actor, ['status' => $before], [
            'status' => $payment->refresh()->status->value,
            'gateway_status' => $evidence->rawStatus ?? 'NOT_FOUND',
        ], $reason);

        return $outcome;
    }
}
