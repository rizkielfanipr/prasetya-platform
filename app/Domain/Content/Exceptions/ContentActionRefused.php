<?php

declare(strict_types=1);

namespace App\Domain\Content\Exceptions;

use DomainException;

/** A content action that the state machine or a rule forbids. The message is shown to the user. */
final class ContentActionRefused extends DomainException
{
    public static function because(string $message): self
    {
        return new self($message);
    }

    public static function staleRevision(): self
    {
        return new self('Konten sudah berubah sejak halaman dibuka. Muat ulang, lalu periksa versi terbaru.');
    }
}
