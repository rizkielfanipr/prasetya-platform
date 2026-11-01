<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

use App\Support\Money;

final readonly class CheckoutRequest
{
    public function __construct(
        public string $requestId,
        public string $invoiceNumber,
        public Money $amount,
        public int $dueInMinutes,
        public string $callbackUrl,
        public string $customerName,
        public string $customerEmail,
        public ?string $customerPhone,
        public string $itemName,
        public string $itemCode,
    ) {}
}
