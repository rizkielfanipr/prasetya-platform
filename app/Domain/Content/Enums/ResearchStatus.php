<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum ResearchStatus: string
{
    case Queued = 'QUEUED';
    case Running = 'RUNNING';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
}
