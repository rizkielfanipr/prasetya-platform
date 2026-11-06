<?php

declare(strict_types=1);

namespace App\Integrations\Duitku;

/** Every Duitku signature is a lowercase hex HMAC-SHA256 over concatenated values, keyed with the API key. */
final class DuitkuSignature
{
    public static function sign(#[\SensitiveParameter] string $apiKey, string ...$parts): string
    {
        return hash_hmac('sha256', implode('', $parts), $apiKey);
    }
}
