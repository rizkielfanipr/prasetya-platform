<?php

declare(strict_types=1);

namespace App\Domain\Content\Research;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentCampaign;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Support\ContentRuleValidator;
use App\Support\BusinessTime;

/**
 * Internal research comes first (ADR-0011): campaigns running on the planned day, approved facts, and the brand's
 * recent and similar content, so a post does not repeat what was said lately. Deterministic, no network.
 */
final class InternalContext
{
    public const int SIMILAR_DAYS = 90;

    public const float SIMILAR_THRESHOLD = 0.4;

    public function __construct(private readonly ContentRuleValidator $rules) {}

    /** @return array{campaigns: list<array{name: string, objective: string|null}>, facts: int, similar: list<array{id: string, title: string, status: string, plannedAt: string, score: float}>} */
    public function for(ContentItem $item): array
    {
        $day = $item->planned_at->setTimezone(BusinessTime::zone())->toDateString();
        $campaigns = ContentCampaign::query()->where('content_brand_id', $item->content_brand_id)->where('is_active', true)
            ->where('starts_on', '<=', $day)->where('ends_on', '>=', $day)->orderBy('starts_on')->get();

        $mine = $this->tokens($item->title.' '.$item->topic);
        $similar = [];
        $candidates = ContentItem::query()->where('content_brand_id', $item->content_brand_id)->whereKeyNot($item->id)
            ->where('status', '!=', ContentStatus::Archived)
            ->whereBetween('planned_at', [$item->planned_at->copy()->subDays(self::SIMILAR_DAYS), $item->planned_at->copy()->addDays(self::SIMILAR_DAYS)])
            ->get(['id', 'public_id', 'title', 'topic', 'status', 'planned_at']);
        foreach ($candidates as $other) {
            $score = $this->similarity($mine, $this->tokens($other->title.' '.$other->topic));
            if ($score >= self::SIMILAR_THRESHOLD) {
                $similar[] = ['id' => $other->public_id, 'title' => $other->title, 'status' => $other->status->value, 'plannedAt' => $other->planned_at->toIso8601ZuluString(), 'score' => round($score, 2)];
            }
        }
        usort($similar, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return [
            'campaigns' => $campaigns->map(static fn (ContentCampaign $c): array => ['name' => $c->name, 'objective' => $c->objective])->values()->all(),
            'facts' => count($this->rules->usableFacts($item)),
            'similar' => array_slice($similar, 0, 5),
        ];
    }

    /** @return list<string> lower-case words of three letters or more, without common Indonesian stop words */
    public function tokens(string $text): array
    {
        $stop = ['dan', 'yang', 'untuk', 'dengan', 'dari', 'ini', 'itu', 'kamu', 'anda', 'kita', 'kami', 'bisa', 'agar', 'pada', 'dalam', 'atau', 'juga', 'lebih', 'cara', 'apa', 'kenapa', 'bagaimana'];
        preg_match_all('/[\p{L}\p{N}]{3,}/u', mb_strtolower($text), $matches);

        return array_values(array_unique(array_diff($matches[0], $stop)));
    }

    /**
     * Jaccard overlap of two word sets.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    public function similarity(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        return count(array_intersect($a, $b)) / count(array_unique([...$a, ...$b]));
    }
}
