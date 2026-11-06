<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

/** A delivery exactly as received, before any trust decision. */
final readonly class InboundNotification
{
    public function __construct(
        public ?string $requestId,
        public string $rawBody,
        public ?string $sourceIp = null,
    ) {}
}
