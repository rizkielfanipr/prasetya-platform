<?php

declare(strict_types=1);

namespace App\Domain\Orders\Data;

use App\Domain\Brands\Enums\ApplicationCode;

/** No price here on purpose: the amount only ever comes from the package catalogue. */
final readonly class CreateOrderData
{
    /** @param array<string, string|null> $details */
    public function __construct(
        public ApplicationCode $application,
        public string $packageCode,
        public string $customerName,
        public string $customerEmail,
        public ?string $customerPhone,
        public array $details,
    ) {}
}
