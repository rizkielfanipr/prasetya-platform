<?php

declare(strict_types=1);

namespace App\Integrations\Doku;

use App\Domain\Payments\Data\GatewayEvidence;
use DateTimeImmutable;
use Exception;

/** Reads the fields this platform relies on. Parsing is non-strict, as DOKU requires: unknown fields are ignored. */
final class DokuPayload
{
    /** @param array<mixed> $payload */
    public static function evidence(array $payload): GatewayEvidence
    {
        $status = data_get($payload, 'transaction.status');
        $amount = data_get($payload, 'order.amount');
        $rawStatus = is_string($status) ? $status : 'UNKNOWN';

        return new GatewayEvidence(
            status: DokuStatusMapper::map($rawStatus),
            rawStatus: $rawStatus,
            amount: is_numeric($amount) && (float) $amount === floor((float) $amount) ? (int) $amount : null,
            channel: self::stringOrNull(data_get($payload, 'channel.id')),
            occurredAt: self::parseDate(data_get($payload, 'transaction.date')),
        );
    }

    /**
     * Allow-list. Card, account, customer, and any future fields never reach storage.
     *
     * @param  array<mixed>  $payload
     * @return array<string, mixed>
     */
    public static function sanitize(array $payload): array
    {
        return [
            'service' => ['id' => self::stringOrNull(data_get($payload, 'service.id'))],
            'acquirer' => ['id' => self::stringOrNull(data_get($payload, 'acquirer.id'))],
            'channel' => ['id' => self::stringOrNull(data_get($payload, 'channel.id'))],
            'order' => [
                'invoice_number' => self::stringOrNull(data_get($payload, 'order.invoice_number')),
                'amount' => is_numeric(data_get($payload, 'order.amount')) ? (int) data_get($payload, 'order.amount') : null,
            ],
            'transaction' => [
                'status' => self::stringOrNull(data_get($payload, 'transaction.status')),
                'type' => self::stringOrNull(data_get($payload, 'transaction.type')),
                'date' => self::stringOrNull(data_get($payload, 'transaction.date')),
                'original_request_id' => self::stringOrNull(data_get($payload, 'transaction.original_request_id')),
            ],
        ];
    }

    /** @param array<mixed> $payload */
    public static function dedupeKey(array $payload): string
    {
        // Content-based: DOKU does not document whether a retry keeps its Request-Id.
        return hash('sha256', implode('|', [
            'DOKU',
            self::stringOrNull(data_get($payload, 'order.invoice_number')),
            data_get($payload, 'order.amount'),
            self::stringOrNull(data_get($payload, 'transaction.status')),
            self::stringOrNull(data_get($payload, 'transaction.date')),
            self::stringOrNull(data_get($payload, 'transaction.original_request_id')),
        ]));
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, 128) : null;
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }
}
