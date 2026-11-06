<?php

declare(strict_types=1);

namespace App\Integrations\Duitku;

use App\Domain\Payments\Data\GatewayEvidence;

/** Reads the fields this platform relies on from a form-encoded callback. */
final class DuitkuPayload
{
    /** @return array<string, mixed> */
    public static function fields(string $rawBody): array
    {
        parse_str($rawBody, $fields);

        return $fields;
    }

    /** @param array<string, mixed> $fields */
    public static function string(array $fields, string $key): ?string
    {
        $value = $fields[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function amount(mixed $value): ?int
    {
        return is_numeric($value) && (float) $value === floor((float) $value) ? (int) $value : null;
    }

    /** @param array<string, mixed> $fields */
    public static function evidence(array $fields): GatewayEvidence
    {
        $resultCode = self::string($fields, 'resultCode') ?? 'UNKNOWN';

        return new GatewayEvidence(
            status: DuitkuStatusMapper::fromCallback($resultCode),
            rawStatus: mb_substr($resultCode, 0, 32),
            amount: self::amount($fields['amount'] ?? null),
            channel: self::limited($fields, 'paymentCode'),
            occurredAt: null, // settlementDate is a settlement day, not when the customer paid.
        );
    }

    /**
     * Allow-list. Names, card data, and any future fields never reach storage.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function sanitize(array $fields): array
    {
        return [
            'paymentCode' => self::limited($fields, 'paymentCode'),
            'resultCode' => self::limited($fields, 'resultCode'),
            'merchantOrderId' => self::limited($fields, 'merchantOrderId'),
            'amount' => self::amount($fields['amount'] ?? null),
            'reference' => self::limited($fields, 'reference'),
            'issuerCode' => self::limited($fields, 'issuerCode'),
            'settlementDate' => self::limited($fields, 'settlementDate'),
        ];
    }

    /** @param array<string, mixed> $fields */
    public static function dedupeKey(array $fields): string
    {
        // The signature is the same for every callback of an order, so it cannot identify a delivery.
        return hash('sha256', implode('|', [
            'DUITKU',
            self::string($fields, 'merchantOrderId'),
            self::string($fields, 'amount'),
            self::string($fields, 'resultCode'),
            self::string($fields, 'reference'),
        ]));
    }

    /** @param array<string, mixed> $fields */
    private static function limited(array $fields, string $key): ?string
    {
        $value = self::string($fields, $key);

        return $value !== null ? mb_substr($value, 0, 128) : null;
    }
}
