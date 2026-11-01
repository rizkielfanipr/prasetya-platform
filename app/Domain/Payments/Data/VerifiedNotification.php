<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

final readonly class VerifiedNotification
{
    /** @param array<string, mixed> $sanitizedPayload Allow-listed fields only */
    public function __construct(
        public string $invoiceNumber,
        public GatewayEvidence $evidence,
        public string $dedupeKey,
        public array $sanitizedPayload,
    ) {}
}
