<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

/** A reviewer's decision on one revision. */
enum ContentDecision: string
{
    case Approved = 'APPROVED';
    case RevisionRequested = 'REVISION_REQUESTED';
    case Rejected = 'REJECTED';
}
