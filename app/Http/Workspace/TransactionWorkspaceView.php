<?php

declare(strict_types=1);

namespace App\Http\Workspace;

/** Views of the Transaksi workspace (ADR-0016), kept in the URL as ?view=. The event log lives in Pengaturan. */
enum TransactionWorkspaceView: string
{
    case All = 'all';
    case Attention = 'attention';

    /** An unknown or missing value falls back to the transaction list instead of failing. */
    public static function fromQuery(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::All) : self::All;
    }
}
