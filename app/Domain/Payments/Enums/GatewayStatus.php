<?php

declare(strict_types=1);

namespace App\Domain\Payments\Enums;

/** Provider-neutral transaction status as reported by a payment gateway. */
enum GatewayStatus: string
{
    case Success = 'SUCCESS';
    case Pending = 'PENDING';
    case Failed = 'FAILED';
    case Expired = 'EXPIRED';
    case Unsupported = 'UNSUPPORTED';
}
