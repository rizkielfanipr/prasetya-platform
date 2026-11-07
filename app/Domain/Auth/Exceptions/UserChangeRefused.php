<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use DomainException;

/** A user change that would lock the team out or leave nobody able to sign in. The message is shown to the admin. */
final class UserChangeRefused extends DomainException
{
    public static function deactivateSelf(): self
    {
        return new self('Anda tidak dapat menonaktifkan akun sendiri.');
    }

    public static function ownPassword(): self
    {
        return new self('Ganti kata sandi Anda sendiri di Pengaturan, dengan kata sandi lama.');
    }

    public static function lastActiveUser(): self
    {
        return new self('Pengguna aktif terakhir tidak dapat dinonaktifkan.');
    }
}
