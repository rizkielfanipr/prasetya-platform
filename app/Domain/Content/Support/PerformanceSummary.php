<?php

declare(strict_types=1);

namespace App\Domain\Content\Support;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentInsightSnapshot;
use App\Domain\Content\Models\ContentItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * How published posts performed, from each post's latest manual snapshot (ADR-0011, phase 8). The same structured
 * summary feeds the Insight screen and, later, the strategist's read tool. It recommends nothing and changes no rule
 * or prompt by itself.
 */
final class PerformanceSummary
{
    /** Published longer than this without a snapshot: remind someone to record it. */
    public const int REMIND_AFTER_DAYS = 2;

    /**
     * @return array{
     *     window: array{days: int, from: string},
     *     totals: array{posts: int, measured: int, reach: int, avgReach: int|null, avgEngagement: float|null, saves: int},
     *     byPillar: list<array<string, mixed>>, byCampaign: list<array<string, mixed>>, byTopic: list<array<string, mixed>>,
     *     top: list<array<string, mixed>>, missing: list<array<string, mixed>>
     * }
     */
    public function forBrand(?int $brandId, int $days): array
    {
        $from = CarbonImmutable::now()->subDays($days);
        $items = ContentItem::query()
            ->where('status', ContentStatus::Published)
            ->when($brandId !== null, static fn ($q) => $q->where('content_brand_id', $brandId))
            ->whereHas('publication', static fn ($q) => $q->where('published_at', '>=', $from))
            ->with(['pillar', 'campaign', 'publication', 'activeRevision'])
            ->get();
        $latest = ContentInsightSnapshot::query()
            ->whereIn('content_item_id', $items->pluck('id'))
            ->orderByDesc('measured_at')->orderByDesc('id')
            ->get()
            ->unique('content_item_id')
            ->keyBy('content_item_id');

        $rows = $items->map(static function (ContentItem $item) use ($latest): array {
            /** @var ContentInsightSnapshot|null $snapshot */
            $snapshot = $latest->get($item->id);

            return ['item' => $item, 'snapshot' => $snapshot];
        });
        $measured = $rows->filter(static fn (array $r): bool => $r['snapshot'] !== null);

        return [
            'window' => ['days' => $days, 'from' => $from->toIso8601ZuluString()],
            'totals' => [
                'posts' => $rows->count(),
                'measured' => $measured->count(),
                'reach' => (int) $measured->sum(static fn (array $r): int => $r['snapshot']->reach),
                'avgReach' => $this->avgReach($measured),
                'avgEngagement' => $this->avgEngagement($measured),
                'saves' => (int) $measured->sum(static fn (array $r): int => (int) $r['snapshot']->saves),
            ],
            'byPillar' => $this->group($measured, static fn (ContentItem $i): ?string => $i->pillar?->name),
            'byCampaign' => $this->group($measured, static fn (ContentItem $i): ?string => $i->campaign?->name),
            'byTopic' => array_slice($this->group($measured, static fn (ContentItem $i): ?string => $i->topic === null ? null : mb_strtolower(trim($i->topic))), 0, 10),
            'top' => $measured
                ->sortByDesc(static fn (array $r): float => $r['snapshot']->engagementRate() ?? 0.0)
                ->take(5)
                ->map(static fn (array $r): array => [
                    'id' => $r['item']->public_id,
                    'title' => $r['item']->title,
                    'pillar' => $r['item']->pillar?->name,
                    'topic' => $r['item']->topic,
                    'publishedAt' => $r['item']->publication?->published_at->toIso8601ZuluString(),
                    'reach' => $r['snapshot']->reach,
                    'saves' => $r['snapshot']->saves,
                    'engagement' => $r['snapshot']->engagementRate(),
                    'visualDirection' => $r['item']->activeRevision?->visual_direction,
                ])->values()->all(),
            'missing' => $rows
                ->filter(static fn (array $r): bool => $r['snapshot'] === null && $r['item']->publication !== null && $r['item']->publication->published_at->lte(now()->subDays(self::REMIND_AFTER_DAYS)))
                ->map(static fn (array $r): array => ['id' => $r['item']->public_id, 'title' => $r['item']->title, 'publishedAt' => $r['item']->publication?->published_at->toIso8601ZuluString()])
                ->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, array{item: ContentItem, snapshot: ContentInsightSnapshot}>  $measured
     * @param  callable(ContentItem): ?string  $key
     * @return list<array{name: string, posts: int, avgReach: int|null, avgEngagement: float|null, avgSaves: float|null}>
     */
    private function group(Collection $measured, callable $key): array
    {
        return $measured
            ->groupBy(static fn (array $r): string => $key($r['item']) ?? '')
            ->reject(static fn (Collection $rows, string $name): bool => $name === '')
            ->map(fn (Collection $rows, string $name): array => [
                'name' => $name,
                'posts' => $rows->count(),
                'avgReach' => $this->avgReach($rows),
                'avgEngagement' => $this->avgEngagement($rows),
                'avgSaves' => round((float) $rows->avg(static fn (array $r): int => (int) $r['snapshot']->saves), 1),
            ])
            ->sortByDesc('avgEngagement')
            ->values()->all();
    }

    /** @param  Collection<int, array{item: ContentItem, snapshot: ContentInsightSnapshot}>  $rows */
    private function avgReach(Collection $rows): ?int
    {
        return $rows->isEmpty() ? null : (int) round((float) $rows->avg(static fn (array $r): int => $r['snapshot']->reach));
    }

    /** @param  Collection<int, array{item: ContentItem, snapshot: ContentInsightSnapshot}>  $rows */
    private function avgEngagement(Collection $rows): ?float
    {
        $rates = $rows->map(static fn (array $r): ?float => $r['snapshot']->engagementRate())->filter(static fn (?float $v): bool => $v !== null);

        return $rates->isEmpty() ? null : round((float) $rates->avg(), 4);
    }
}
