<?php

declare(strict_types=1);

namespace App\Integrations\Doku;

/**
 * Non-SNAP request signature, per DOKU "Signature Component from Request Header".
 * Digest is included only when a body exists: DOKU applies it to POST, not to GET.
 */
final class DokuSignature
{
    public const string PREFIX = 'HMACSHA256=';

    public static function digest(string $rawBody): string
    {
        return base64_encode(hash('sha256', $rawBody, true));
    }

    public static function components(string $clientId, string $requestId, string $timestamp, string $target, ?string $rawBody): string
    {
        $lines = [
            "Client-Id:{$clientId}",
            "Request-Id:{$requestId}",
            "Request-Timestamp:{$timestamp}",
            "Request-Target:{$target}",
        ];

        if ($rawBody !== null) {
            $lines[] = 'Digest:'.self::digest($rawBody);
        }

        return implode("\n", $lines);
    }

    public static function sign(#[\SensitiveParameter] string $secret, string $clientId, string $requestId, string $timestamp, string $target, ?string $rawBody): string
    {
        $components = self::components($clientId, $requestId, $timestamp, $target, $rawBody);

        return self::PREFIX.base64_encode(hash_hmac('sha256', $components, $secret, true));
    }
}
