<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Orders\Enums\TransitionSource;
use App\Domain\Payments\Contracts\NotificationVerifier;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Data\InboundNotification;
use App\Domain\Payments\Data\VerifiedNotification;
use App\Domain\Payments\Enums\WebhookRejection;
use App\Domain\Payments\Enums\WebhookStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\WebhookEvent;
use App\Support\Logging\OperationLog;
use App\Support\RequestContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/**
 * Every delivery leaves a sanitised evidence row, whatever happens to it.
 * Returns the row; the caller maps its status to an HTTP acknowledgement.
 */
final class ProcessGatewayNotification
{
    public function __construct(
        private readonly NotificationVerifier $verifier,
        private readonly PaymentGateway $gateway,
        private readonly ApplyPaymentEvidence $applyEvidence,
        private readonly RequestContext $context,
    ) {}

    public function __invoke(InboundNotification $inbound): WebhookEvent
    {
        $startedAt = microtime(true);
        $event = $this->handle($inbound);

        OperationLog::record('payments.notification', $event->status->value, [
            'provider' => $event->provider,
            'webhook_event' => $event->public_id,
            'invoice' => $event->invoice_number,
            'rejection' => $event->rejection_reason?->value,
        ], $startedAt);

        return $event;
    }

    private function handle(InboundNotification $inbound): WebhookEvent
    {
        $rejection = $this->verifier->rejectionFor($inbound);

        if ($rejection !== null) {
            return $this->store($inbound, ['status' => WebhookStatus::Rejected, 'rejection_reason' => $rejection, 'signature_valid' => false, 'processed_at' => now()]);
        }

        $notification = $this->verifier->parse($inbound->rawBody);

        if ($notification === null) {
            return $this->store($inbound, ['status' => WebhookStatus::Rejected, 'rejection_reason' => WebhookRejection::MalformedPayload, 'signature_valid' => true, 'processed_at' => now()]);
        }

        $attributes = [
            'status' => WebhookStatus::Verified,
            'signature_valid' => true,
            'dedupe_key' => $notification->dedupeKey,
            'invoice_number' => $notification->invoiceNumber,
            'amount' => $notification->evidence->amount,
            'transaction_status' => $notification->evidence->rawStatus,
            'channel' => $notification->evidence->channel,
            'sanitized_payload' => $notification->sanitizedPayload,
        ];

        try {
            $event = $this->store($inbound, [...$attributes, 'primary_dedupe_key' => $notification->dedupeKey]);
        } catch (UniqueConstraintViolationException) {
            return $this->handleRepeat($inbound, $attributes, $notification);
        }

        return $this->process($event, $notification);
    }

    /** @param array<string, mixed> $attributes */
    private function handleRepeat(InboundNotification $inbound, array $attributes, VerifiedNotification $notification): WebhookEvent
    {
        $original = WebhookEvent::query()->where('primary_dedupe_key', $notification->dedupeKey)->firstOrFail();

        $duplicate = $this->store($inbound, [...$attributes, 'status' => WebhookStatus::Duplicate, 'duplicate_of_id' => $original->id, 'payment_id' => $original->payment_id, 'processed_at' => now()]);

        // The first delivery crashed before finishing: this retry is what DOKU's retry schedule is for.
        if ($original->status === WebhookStatus::Failed && $this->process($original, $notification)->status === WebhookStatus::Failed) {
            return $original; // Still failing: the caller must keep asking DOKU to retry.
        }

        return $duplicate;
    }

    private function process(WebhookEvent $event, VerifiedNotification $notification): WebhookEvent
    {
        $payment = Payment::query()->where('gateway_invoice_number', $notification->invoiceNumber)->first();

        if ($payment === null) {
            $event->update(['status' => WebhookStatus::Rejected, 'rejection_reason' => WebhookRejection::UnknownInvoice, 'processed_at' => now()]);

            return $event;
        }

        $event->update(['payment_id' => $payment->id]);

        try {
            $outcome = ($this->applyEvidence)($payment->id, $notification->evidence, TransitionSource::GatewayNotification, $event->id);
        } catch (Throwable $e) {
            $event->update(['status' => WebhookStatus::Failed, 'processed_at' => now()]);
            report($e);

            return $event;
        }

        $event->update(['status' => $outcome->status, 'rejection_reason' => $outcome->rejection, 'processed_at' => now()]);

        return $event;
    }

    /** @param array<string, mixed> $attributes */
    private function store(InboundNotification $inbound, array $attributes): WebhookEvent
    {
        return WebhookEvent::query()->create([
            'provider' => $this->gateway->name(),
            'provider_request_id' => $inbound->requestId !== null ? mb_substr($inbound->requestId, 0, 128) : null,
            'payload_digest' => hash('sha256', $inbound->rawBody),
            'request_id' => $this->context->requestId(),
            'received_at' => now(),
            ...$attributes,
        ]);
    }
}
