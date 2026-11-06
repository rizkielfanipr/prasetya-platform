<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * IDR amount in whole rupiah. Duitku takes paymentAmount without decimals,
 * so rupiah is the smallest unit this platform ever handles.
 */
final readonly class Money
{
    public function __construct(public int $amount, public string $currency = 'IDR')
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }
    }

    public function times(int $quantity): self
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        return new self($this->amount * $quantity, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }
}
