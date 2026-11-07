<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/**
 * The one place for username rules (ADR-0009): lower-case, 3–32 characters from a–z, 0–9, ".", "_", and "-",
 * starting and ending with a letter or digit. Stored normalised, so sign-in is case-insensitive. A username is
 * set once at creation and never changed.
 */
final class Username
{
    public const int MIN = 3;

    public const int MAX = 32;

    public const string PATTERN = '/^[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?$/';

    public static function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    public static function isValid(string $value): bool
    {
        $length = strlen($value);

        return $length >= self::MIN && $length <= self::MAX && preg_match(self::PATTERN, $value) === 1;
    }

    /**
     * Validation rules for a new username, applied after normalisation.
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['required', 'string', 'min:'.self::MIN, 'max:'.self::MAX, 'regex:'.self::PATTERN, 'unique:users,username'];
    }
}
