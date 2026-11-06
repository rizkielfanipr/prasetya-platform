<?php

declare(strict_types=1);

namespace App\Integrations\Duitku;

use App\Domain\Payments\Enums\GatewayStatus;

final class DuitkuStatusMapper
{
    /** Callback resultCode. It is not covered by the signature, so only 00 means anything, and only after a check. */
    public static function fromCallback(string $resultCode): GatewayStatus
    {
        return $resultCode === '00' ? GatewayStatus::Success : GatewayStatus::Failed;
    }

    /** Check Transaction statusCode. Canceled is ignored rather than closing the payment: see ADR-0007. */
    public static function fromCheck(string $statusCode): GatewayStatus
    {
        return match ($statusCode) {
            '00' => GatewayStatus::Success,
            '01' => GatewayStatus::Pending,
            '02' => GatewayStatus::Failed,
            default => GatewayStatus::Unsupported,
        };
    }
}
