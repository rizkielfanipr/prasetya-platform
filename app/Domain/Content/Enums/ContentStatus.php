<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

/** Content item lifecycle (ADR-0011, data-dictionary.md). Only the actions in App\Domain\Content\Actions move it. */
enum ContentStatus: string
{
    case Draft = 'DRAFT';
    case ReadyForReview = 'READY_FOR_REVIEW';
    case RevisionRequested = 'REVISION_REQUESTED';
    case Approved = 'APPROVED';
    case Published = 'PUBLISHED';
    case Rejected = 'REJECTED';

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Draft => [self::ReadyForReview, self::Rejected],
            self::ReadyForReview => [self::RevisionRequested, self::Approved, self::Rejected],
            self::RevisionRequested => [self::ReadyForReview],
            // Editing approved content creates a new revision that goes back to review.
            self::Approved => [self::Published, self::ReadyForReview],
            self::Published, self::Rejected => [],
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
