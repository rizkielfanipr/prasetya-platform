<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

/**
 * Content item lifecycle (ADR-0012): one operator plans, drafts, marks Ready, and records the post.
 * Only the actions in App\Domain\Content\Actions move it; AI and queues only ever write Draft content.
 * Legacy editorial statuses were migrated (App\Domain\Content\Migration\LegacyStatusMap).
 */
enum ContentStatus: string
{
    case Draft = 'DRAFT';
    case Ready = 'READY';
    case Published = 'PUBLISHED';
    case Archived = 'ARCHIVED';

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Draft => [self::Ready, self::Archived],
            self::Ready => [self::Draft, self::Published, self::Archived],
            self::Published, self::Archived => [],
        };
    }

    public function canBecome(self $next): bool
    {
        return in_array($next, $this->next(), true);
    }

    public function isFinal(): bool
    {
        return $this->next() === [];
    }
}
