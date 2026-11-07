<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum SourceOrigin: string
{
    case Search = 'SEARCH';
    case Manual = 'MANUAL';
}
