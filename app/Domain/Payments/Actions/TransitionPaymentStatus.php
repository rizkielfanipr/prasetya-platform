<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Orders\Enums\TransitionSource;
use App\Domain\Orders\Exceptions\InvalidStateTransition;
use App\Domain\Payments\Enums\PaymentEventType;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use App\Support\RequestContext;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/** The only way a payment's status changes. Callers own the surrounding transaction. */
final class TransitionPaymentStatus
{
    public function __construct(private readonly RequestContext $context) {}

    /** @param array<string, mixed> $metadata */
    public function __invoke(
        Payment $payment,
        PaymentStatus $to,
        TransitionSource $source,
        ?int $webhookEventId = null,
        ?DateTimeInterface $occurredAt = null,
        array $metadata = [],
    ): void {
        $from = $payment->status;

        if (! $from->canTransitionTo($to)) {
            throw InvalidStateTransition::between('Payment', $from->value, $to->value);
        }

        $at = $occurredAt ?? now();
        $payment->status = $to;
        $payment->last_event_at = now();

        if ($to === PaymentStatus::Paid) {
            $payment->paid_at = Carbon::instance($at);
        }

        $payment->save();

        PaymentEvent::query()->create([
            'payment_id' => $payment->id,
            'webhook_event_id' => $webhookEventId,
            'type' => PaymentEventType::StatusChanged,
            'source' => $source,
            'from_status' => $from,
            'to_status' => $to,
            'metadata' => $metadata === [] ? null : $metadata,
            'request_id' => $this->context->requestId(),
            'occurred_at' => $at,
        ]);
    }
}
