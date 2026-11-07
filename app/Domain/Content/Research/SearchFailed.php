<?php

declare(strict_types=1);

namespace App\Domain\Content\Research;

use RuntimeException;

/** A search or page read that failed. The message is safe to store and show: never a key or a raw body. */
final class SearchFailed extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
