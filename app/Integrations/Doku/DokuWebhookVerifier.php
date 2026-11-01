<?php

declare(strict_types=1);

namespace App\Integrations\Doku;

use App\Domain\Payments\Contracts\NotificationVerifier;
use App\Domain\Payments\Data\InboundNotification;
use App\Domain\Payments\Data\VerifiedNotification;
use App\Domain\Payments\Enums\WebhookRejection;
use JsonException;

final class DokuWebhookVerifier implements NotificationVerifier
{
    public function __construct(private readonly DokuConfig $config) {}

    public function rejectionFor(InboundNotification $notification): ?WebhookRejection
    {
        if (blank($notification->clientId) || blank($notification->requestId) || blank($notification->requestTimestamp) || blank($notification->signature)) {
            return WebhookRejection::MissingHeaders;
        }

        // Unconfigured means nothing can be authentic. An empty secret must never validate a signature.
        if (! $this->config->isComplete()) {
            return WebhookRejection::InvalidSignature;
        }

        if (! hash_equals($this->config->clientId, (string) $notification->clientId)) {
            return WebhookRejection::ClientMismatch;
        }

        $expected = DokuSignature::sign(
            $this->config->secretKey,
            (string) $notification->clientId,
            (string) $notification->requestId,
            (string) $notification->requestTimestamp,
            $this->config->notificationPath,
            $notification->rawBody,
        );

        if (! hash_equals($expected, (string) $notification->signature)) {
            return WebhookRejection::InvalidSignature;
        }

        return $this->isStale((string) $notification->requestTimestamp) ? WebhookRejection::StaleTimestamp : null;
    }

    public function parse(string $rawBody): ?VerifiedNotification
    {
        try {
            $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $invoice = is_array($payload) ? data_get($payload, 'order.invoice_number') : null;

        if (! is_array($payload) || ! is_string($invoice) || $invoice === '' || strlen($invoice) > 64) {
            return null;
        }

        return new VerifiedNotification(
            invoiceNumber: $invoice,
            evidence: DokuPayload::evidence($payload),
            dedupeKey: DokuPayload::dedupeKey($payload),
            sanitizedPayload: DokuPayload::sanitize($payload),
        );
    }

    private function isStale(string $timestamp): bool
    {
        if ($this->config->notificationMaxAgeSeconds <= 0) {
            return false;
        }

        $sentAt = strtotime($timestamp);

        return $sentAt === false || abs(time() - $sentAt) > $this->config->notificationMaxAgeSeconds;
    }
}
