<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

/** How much a source can be leaned on. Never enough to state a business fact: those come from approved facts only. */
enum SourceTrust: string
{
    case Official = 'OFFICIAL';
    case General = 'GENERAL';
    case UserGenerated = 'USER_GENERATED';
}
