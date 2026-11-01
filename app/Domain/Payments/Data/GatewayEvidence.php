<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

use App\Domain\Payments\Enums\GatewayStatus;
use DateTimeImmutable;

final readonly class GatewayEvidence
{
    public function __construct(
        public GatewayStatus $status,
        public string $rawStatus,
        public ?int $amount,
        public ?string $channel,
        public ?DateTimeImmutable $occurredAt,
    ) {}
}
