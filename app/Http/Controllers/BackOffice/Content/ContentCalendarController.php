<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Content;

use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentItem;
use App\Http\Presenters\ContentPresenter;
use App\Http\Requests\BackOffice\Content\ContentFilterRequest;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Kalender: one week, Monday to Sunday in business time. A planned time passing changes nothing. */
final class ContentCalendarController
{
    public function home(): RedirectResponse
    {
        return redirect()->route('content.calendar');
    }

    public function __invoke(ContentFilterRequest $request): Response
    {
        /** @var array{brand?: string|null, status?: string|null, week?: string|null} $filters */
        $filters = $request->validated();
        $zone = BusinessTime::zone();
        $start = CarbonImmutable::parse($filters['week'] ?? 'today', $zone)->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
        $end = $start->addWeek();

        $items = self::filtered($filters)
            ->whereBetween('planned_at', [$start->utc(), $end->utc()->subSecond()])
            ->with(['brand', 'activeRevision.asset'])
            ->orderBy('planned_at')
            ->orderBy('id')
            ->get();

        return Inertia::render('content/ContentCalendarPage', [
            'filters' => ['brand' => $filters['brand'] ?? null, 'status' => $filters['status'] ?? null, 'week' => $start->toDateString()],
            'week' => [
                'start' => $start->toDateString(),
                'end' => $end->subDay()->toDateString(),
                'previous' => $start->subWeek()->toDateString(),
                'next' => $end->toDateString(),
                'today' => CarbonImmutable::now($zone)->toDateString(),
                'days' => array_map(static fn (int $offset): string => $start->addDays($offset)->toDateString(), range(0, 6)),
            ],
            'items' => $items->map(ContentPresenter::row(...))->all(),
            'brands' => self::brands(),
        ]);
    }

    /**
     * @param  array{brand?: string|null, status?: string|null}  $filters
     * @return Builder<ContentItem>
     */
    public static function filtered(array $filters): Builder
    {
        return ContentItem::query()
            ->when($filters['brand'] ?? null, static fn (Builder $q, string $code) => $q->whereHas('brand', static fn (Builder $b) => $b->where('code', $code)))
            ->when($filters['status'] ?? null, static fn (Builder $q, string $status) => $q->where('status', $status));
    }

    /** @return list<array{code: string, name: string}> */
    public static function brands(): array
    {
        return ContentBrand::query()->where('is_active', true)->orderBy('name')->get()->map(ContentPresenter::brand(...))->values()->all();
    }
}
