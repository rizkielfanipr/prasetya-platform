<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum GenerationStatus: string
{
    case Queued = 'QUEUED';
    case Running = 'RUNNING';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
    /** The provider answered, but the content had changed meanwhile, so nothing was applied. */
    case Discarded = 'DISCARDED';

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
