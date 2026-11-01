<?php

declare(strict_types=1);

namespace App\Domain\Orders\Exceptions;

use DomainException;

final class InvalidStateTransition extends DomainException
{
    public static function between(string $entity, string $from, string $to): self
    {
        return new self("{$entity} cannot move from {$from} to {$to}.");
    }
}
