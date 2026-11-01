<?php

declare(strict_types=1);

namespace App\Domain\Orders\Enums;

enum TransitionSource: string
{
    case Api = 'API';
    case GatewayNotification = 'GATEWAY_NOTIFICATION';
    case GatewayCheck = 'GATEWAY_CHECK';
    case Scheduler = 'SCHEDULER';
    case Admin = 'ADMIN';
}
