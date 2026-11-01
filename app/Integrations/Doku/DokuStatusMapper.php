<?php

declare(strict_types=1);

namespace App\Integrations\Doku;

use App\Domain\Payments\Enums\GatewayStatus;

/** transaction.status values from DOKU "Check Status API" Transaction Status Mapping. */
final class DokuStatusMapper
{
    public static function map(string $status): GatewayStatus
    {
        return match (strtoupper($status)) {
            'SUCCESS' => GatewayStatus::Success,
            'PENDING' => GatewayStatus::Pending,
            'FAILED' => GatewayStatus::Failed,
            'EXPIRED' => GatewayStatus::Expired,
            'TIMEOUT', 'REDIRECT' => GatewayStatus::NotFinal,
            // REFUNDED is a real final status, but refunds are outside this release: surface it, change nothing.
            default => GatewayStatus::Unsupported,
        };
    }
}
