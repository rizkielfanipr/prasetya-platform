<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

use DateTimeImmutable;

final readonly class CheckoutSession
{
    public function __construct(
        public string $url,
        public string $reference,
        public ?DateTimeImmutable $expiresAt,
    ) {}
}
