<?php

declare(strict_types=1);

namespace App\Support\Eloquent;

use LogicException;

/** Integrity guard for evidence tables: rows can be inserted, never changed or removed through a model. */
trait AppendOnly
{
    protected static function bootAppendOnly(): void
    {
        static::updating(fn () => throw new LogicException(static::class.' is append-only.'));
        static::deleting(fn () => throw new LogicException(static::class.' is append-only.'));
    }
}
