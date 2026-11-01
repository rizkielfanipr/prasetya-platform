<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

enum IdempotencyDecision
{
    case Replay;
    case Reused;
    case InProgress;
    case TakeOver;

    /** Pure decision for a key that already exists. */
    public static function for(IdempotencyKey $existing, string $fingerprint, \DateTimeInterface $now): self
    {
        if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
            return self::Reused;
        }

        if ($existing->status === IdempotencyKey::STATUS_COMPLETED) {
            return self::Replay;
        }

        return $existing->locked_until !== null && $existing->locked_until->getTimestamp() > $now->getTimestamp()
            ? self::InProgress
            : self::TakeOver;
    }
}
