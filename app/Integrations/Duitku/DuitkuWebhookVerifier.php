<?php

declare(strict_types=1);

namespace App\Integrations\Duitku;

use App\Domain\Payments\Contracts\NotificationVerifier;
use App\Domain\Payments\Data\InboundNotification;
use App\Domain\Payments\Data\VerifiedNotification;
use App\Domain\Payments\Enums\WebhookRejection;

final class DuitkuWebhookVerifier implements NotificationVerifier
{
    public function __construct(private readonly DuitkuConfig $config) {}

    public function rejectionFor(InboundNotification $notification): ?WebhookRejection
    {
        if ($this->config->enforceCallbackIp && ! in_array($notification->sourceIp, $this->config->callbackIps, true)) {
            return WebhookRejection::SourceNotAllowed;
        }

        $fields = DuitkuPayload::fields($notification->rawBody);
        $merchantCode = DuitkuPayload::string($fields, 'merchantCode');
        $amount = DuitkuPayload::string($fields, 'amount');
        $invoice = DuitkuPayload::string($fields, 'merchantOrderId');
        $signature = DuitkuPayload::string($fields, 'signature');

        if ($merchantCode === null || $amount === null || $invoice === null || $signature === null) {
            return WebhookRejection::MissingHeaders;
        }

        // Unconfigured means nothing can be authentic. An empty key must never validate a signature.
        if (! $this->config->isComplete()) {
            return WebhookRejection::InvalidSignature;
        }

        if (! hash_equals($this->config->merchantCode, $merchantCode)) {
            return WebhookRejection::ClientMismatch;
        }

        return hash_equals(DuitkuSignature::sign($this->config->apiKey, $merchantCode, $amount, $invoice), $signature)
            ? null
            : WebhookRejection::InvalidSignature;
    }

    public function parse(string $rawBody): ?VerifiedNotification
    {
        $fields = DuitkuPayload::fields($rawBody);
        $invoice = DuitkuPayload::string($fields, 'merchantOrderId');

        if ($invoice === null || strlen($invoice) > 64) {
            return null;
        }

        return new VerifiedNotification(
            invoiceNumber: $invoice,
            evidence: DuitkuPayload::evidence($fields),
            dedupeKey: DuitkuPayload::dedupeKey($fields),
            sanitizedPayload: DuitkuPayload::sanitize($fields),
            // The signature does not cover resultCode: see ADR-0007.
            requiresConfirmation: true,
        );
    }
}
