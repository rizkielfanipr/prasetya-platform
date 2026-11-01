<?php

declare(strict_types=1);

namespace App\Support\Logging;

/** Replaces sensitive values by key name, recursively, before anything is written to a log. */
final class Redactor
{
    private const array SENSITIVE_KEY_FRAGMENTS = [
        'password', 'secret', 'token', 'signature', 'authorization', 'cookie', 'api_key', 'apikey',
        'session', 'card', 'email', 'phone', 'customer_name', 'virtual_account', 'checkout_url',
    ];

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitive($key)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }

    private static function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
