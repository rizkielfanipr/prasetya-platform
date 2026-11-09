<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Content;

use App\Domain\Content\Enums\CampaignPriority;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentItem;
use App\Http\Presenters\ContentBrandPresenter;
use App\Http\Presenters\ContentPresenter;
use App\Http\Requests\BackOffice\Content\ContentFilterRequest;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
        /** @var array{brand?: string|null, pillar?: string|null, campaign?: string|null, status?: string|null, week?: string|null} $filters */
        $filters = $request->validated();
        $zone = BusinessTime::zone();
        $start = CarbonImmutable::parse($filters['week'] ?? 'today', $zone)->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
        $end = $start->addWeek();

        $items = self::filtered($filters)
            ->whereBetween('planned_at', [$start->utc(), $end->utc()->subSecond()])
            ->with(self::ROW_RELATIONS)
            ->orderBy('planned_at')
            ->orderBy('id')
            ->get();

        return Inertia::render('content/ContentCalendarPage', [
            'filters' => [...self::currentFilters($filters), 'week' => $start->toDateString()],
            'week' => [
                'start' => $start->toDateString(),
                'end' => $end->subDay()->toDateString(),
                'previous' => $start->subWeek()->toDateString(),
                'next' => $end->toDateString(),
                'today' => CarbonImmutable::now($zone)->toDateString(),
                'days' => array_map(static fn (int $offset): string => $start->addDays($offset)->toDateString(), range(0, 6)),
            ],
            'items' => $items->map(ContentPresenter::row(...))->all(),
            'composition' => self::composition($items),
            'manageCampaigns' => $request->boolean('manage_campaigns'),
            'priorities' => array_map(static fn (CampaignPriority $priority): string => $priority->value, CampaignPriority::cases()),
            ...self::options(),
        ]);
    }

    public const array ROW_RELATIONS = ['brand', 'pillar', 'campaign', 'activeRevision.asset'];

    /**
     * @param  array{brand?: string|null, pillar?: string|null, campaign?: string|null, status?: string|null}  $filters
     * @return Builder<ContentItem>
     */
    public static function filtered(array $filters): Builder
    {
        return ContentItem::query()
            ->when($filters['brand'] ?? null, static fn (Builder $q, string $code) => $q->whereHas('brand', static fn (Builder $b) => $b->where('code', $code)))
            ->when($filters['pillar'] ?? null, static fn (Builder $q, string $id) => $q->whereHas('pillar', static fn (Builder $p) => $p->where('public_id', $id)))
            ->when($filters['campaign'] ?? null, static fn (Builder $q, string $id) => $q->whereHas('campaign', static fn (Builder $c) => $c->where('public_id', $id)))
            ->when($filters['status'] ?? null, static fn (Builder $q, string $status) => $q->where('status', $status));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{brand: string|null, pillar: string|null, campaign: string|null, status: string|null}
     */
    public static function currentFilters(array $filters): array
    {
        $value = static fn (string $key): ?string => is_string($filters[$key] ?? null) ? $filters[$key] : null;

        return ['brand' => $value('brand'), 'pillar' => $value('pillar'), 'campaign' => $value('campaign'), 'status' => $value('status')];
    }

    /**
     * Active brands, each with the pillars and campaigns an item can be planned with.
     *
     * @return array{brands: list<array<string, mixed>>}
     */
    public static function options(): array
    {
        $brands = ContentBrand::query()->where('is_active', true)->with(['pillars', 'campaigns'])->orderBy('name')->get();

        return ['brands' => $brands->map(static fn (ContentBrand $brand): array => ['id' => $brand->public_id] + ContentPresenter::brand($brand) + ContentBrandPresenter::planOptions($brand))->values()->all()];
    }

    /**
     * How the week's planned posts spread over each brand's pillars, against their targets. Rejected items do not
     * count; an item without a pillar counts as unassigned.
     *
     * @param  Collection<int, ContentItem>  $items
     * @return list<array<string, mixed>>
     */
    private static function composition(Collection $items): array
    {
        $counted = $items->reject(static fn (ContentItem $item): bool => $item->status === ContentStatus::Archived);
        $brands = ContentBrand::query()->whereIn('id', $counted->pluck('content_brand_id')->unique())
            ->orWhereHas('pillars', static fn (Builder $q) => $q->where('is_active', true))
            ->with('pillars')->orderBy('name')->get();

        return $brands->map(static function (ContentBrand $brand) use ($counted): array {
            $mine = $counted->where('content_brand_id', $brand->id);
            $pillars = $brand->pillars->filter(static fn ($pillar): bool => $pillar->is_active || $mine->contains('content_pillar_id', $pillar->id));

            return [
                'brand' => ContentPresenter::brand($brand),
                'total' => $mine->count(),
                'unassigned' => $mine->whereNull('content_pillar_id')->count(),
                'pillars' => $pillars->map(static fn ($pillar): array => [
                    'id' => $pillar->public_id,
                    'name' => $pillar->name,
                    'targetShare' => $pillar->target_share,
                    'count' => $mine->where('content_pillar_id', $pillar->id)->count(),
                ])->values()->all(),
            ];
        })->values()->all();
    }
}
