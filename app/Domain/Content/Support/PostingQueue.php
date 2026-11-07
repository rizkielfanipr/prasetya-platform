<?php

declare(strict_types=1);

namespace App\Domain\Content\Support;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentItem;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Approved posts waiting to be posted by hand (ADR-0011, phase 7). Overdue and due-today items are reminders; nothing
 * here ever publishes: only a person marking the post does.
 */
final class PostingQueue
{
    /** The posting checklist a person confirms when marking a post published, kept as evidence. */
    public const array CHECKLIST = ['image', 'caption', 'alt_text', 'account'];

    /** @return Builder<ContentItem> */
    public function waiting(): Builder
    {
        return ContentItem::query()->where('status', ContentStatus::Approved);
    }

    /** Approved posts planned up to the end of today (WIB), not yet marked posted. */
    public function dueCount(): int
    {
        return $this->waiting()->where('planned_at', '<=', $this->endOfToday())->count();
    }

    /** overdue (an earlier day), today, upcoming, or null when the item is not waiting to be posted. */
    public function dueState(ContentItem $item): ?string
    {
        if ($item->status !== ContentStatus::Approved) {
            return null;
        }
        $day = $item->planned_at->setTimezone(BusinessTime::zone())->toDateString();
        $today = CarbonImmutable::now(BusinessTime::zone())->toDateString();

        return match (true) {
            $day < $today => 'overdue',
            $day === $today => 'today',
            default => 'upcoming',
        };
    }

    /** Whole business days between the planned day and today; positive when late. */
    public function daysLate(ContentItem $item): int
    {
        $planned = CarbonImmutable::parse($item->planned_at->setTimezone(BusinessTime::zone())->toDateString(), BusinessTime::zone());

        return (int) $planned->diffInDays(CarbonImmutable::now(BusinessTime::zone())->startOfDay(), false);
    }

    public function endOfToday(): CarbonImmutable
    {
        return CarbonImmutable::now(BusinessTime::zone())->endOfDay()->utc();
    }
}
