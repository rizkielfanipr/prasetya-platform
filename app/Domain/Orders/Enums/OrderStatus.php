<?php

declare(strict_types=1);

namespace App\Domain\Orders\Enums;

enum OrderStatus: string
{
    case Draft = 'DRAFT';
    case PendingPayment = 'PENDING_PAYMENT';
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';
    case Expired = 'EXPIRED';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingPayment, self::Cancelled],
            self::PendingPayment => [self::Confirmed, self::Cancelled, self::Expired],
            // Verified money beats local expiry: a late payment still confirms the order.
            self::Expired => [self::Confirmed],
            self::Confirmed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /** @return list<self> Orders still counted as live work on the dashboard. */
    public static function active(): array
    {
        return [self::PendingPayment, self::Confirmed];
    }
}
