<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum CampaignPriority: string
{
    case High = 'HIGH';
    case Normal = 'NORMAL';
    case Low = 'LOW';
}
