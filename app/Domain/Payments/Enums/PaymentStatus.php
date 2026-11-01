<?php

declare(strict_types=1);

namespace App\Domain\Payments\Enums;

enum PaymentStatus: string
{
    case Created = 'CREATED';
    case Pending = 'PENDING';
    case Paid = 'PAID';
    case Failed = 'FAILED';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Created => [self::Pending, self::Paid, self::Failed, self::Expired, self::Cancelled],
            self::Pending => [self::Paid, self::Failed, self::Expired, self::Cancelled],
            // Out-of-order delivery: verified money beats an earlier failure or local expiry.
            self::Failed, self::Expired => [self::Paid],
            self::Paid, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isOpen(): bool
    {
        return $this === self::Created || $this === self::Pending;
    }
}
