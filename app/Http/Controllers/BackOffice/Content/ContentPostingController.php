<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Content;

use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Support\PostingQueue;
use App\Http\Presenters\ContentPresenter;
use Inertia\Inertia;
use Inertia\Response;

/** Siap posting (ADR-0011, phase 7): approved posts waiting to be posted by hand, overdue first. */
final class ContentPostingController
{
    public const int UPCOMING_DAYS = 14;

    public function __invoke(PostingQueue $queue): Response
    {
        $items = $queue->waiting()
            ->where('planned_at', '<=', $queue->endOfToday()->addDays(self::UPCOMING_DAYS))
            ->with(ContentCalendarController::ROW_RELATIONS)
            ->orderBy('planned_at')
            ->limit(100)
            ->get();
        $row = static fn (ContentItem $item): array => [...ContentPresenter::row($item), 'daysLate' => $queue->daysLate($item)];
        $group = static fn (string $state): array => $items->filter(static fn (ContentItem $i): bool => $queue->dueState($i) === $state)->map($row)->values()->all();

        return Inertia::render('content/ContentToPostPage', [
            'overdue' => $group('overdue'),
            'today' => $group('today'),
            'upcoming' => $group('upcoming'),
            'upcomingDays' => self::UPCOMING_DAYS,
            'later' => $queue->waiting()->where('planned_at', '>', $queue->endOfToday()->addDays(self::UPCOMING_DAYS))->count(),
        ]);
    }
}
