<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

final readonly class VerifiedNotification
{
    /**
     * @param  array<string, mixed>  $sanitizedPayload  Allow-listed fields only
     * @param  bool  $requiresConfirmation  The signature does not cover the status, so a success must be checked with the gateway
     */
    public function __construct(
        public string $invoiceNumber,
        public GatewayEvidence $evidence,
        public string $dedupeKey,
        public array $sanitizedPayload,
        public bool $requiresConfirmation = false,
    ) {}
}
