<?php

declare(strict_types=1);

namespace App\Domain\Content\Support;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Enums\RuleSeverity;
use App\Domain\Content\Models\ContentBrandProfile;
use App\Domain\Content\Models\ContentFact;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentRevision;
use App\Support\BusinessTime;

/**
 * Checks a revision against its brand's current rules, approved facts, pillar, campaign, and topic spacing. Pure
 * rules over stored data: no AI, no network, and the same input always gives the same findings (ADR-0011, phase 2).
 *
 * Errors block submission for review. Warnings are shown to the writer and the reviewer, who decide.
 */
final class ContentRuleValidator
{
    /** Public text: what ends up on Instagram. The brief and visual direction are internal. */
    private const array PUBLIC_FIELDS = ['caption' => 'caption', 'hashtags' => 'hashtag', 'alt_text' => 'alt text'];

    /** @return list<RuleFinding> */
    public function check(ContentItem $item, ContentRevision $revision): array
    {
        $item->loadMissing(['brand.currentProfile', 'pillar', 'campaign']);
        $profile = $item->brand->currentProfile;
        $findings = [];

        if ($profile === null) {
            $findings[] = $this->warning('no_profile', 'Aturan brand belum diisi, jadi konten belum dapat diperiksa terhadapnya.');
        } else {
            array_push($findings, ...$this->profileRules($profile, $revision), ...$this->topicSpacing($profile, $item));
        }

        array_push($findings, ...$this->claims($this->usableFacts($item), $revision), ...$this->plan($item));

        return $findings;
    }

    /** @param  list<RuleFinding>  $findings */
    public static function errors(array $findings): int
    {
        return count(array_filter($findings, static fn (RuleFinding $f): bool => $f->severity === RuleSeverity::Error));
    }

    /** @return list<RuleFinding> */
    private function profileRules(ContentBrandProfile $profile, ContentRevision $revision): array
    {
        $findings = [];
        foreach (self::PUBLIC_FIELDS as $field => $label) {
            $text = (string) $revision->{$field};
            foreach ($profile->forbidden_terms as $term) {
                if ($this->contains($text, $term)) {
                    $findings[] = $this->error('forbidden_term', ucfirst($label)." memakai kata terlarang \"{$term}\".", $field);
                }
            }
            foreach ($profile->forbidden_claims as $claim) {
                if ($this->contains($text, $claim)) {
                    $findings[] = $this->error('forbidden_claim', ucfirst($label)." memuat klaim terlarang \"{$claim}\".", $field);
                }
            }
        }

        $caption = (string) $revision->caption;
        $length = mb_strlen($caption);
        if ($profile->caption_max_length !== null && $length > $profile->caption_max_length) {
            $findings[] = $this->error('caption_too_long', "Caption {$length} karakter; batas brand {$profile->caption_max_length}.", 'caption');
        }
        if ($profile->caption_min_length !== null && $length < $profile->caption_min_length) {
            $findings[] = $this->warning('caption_too_short', "Caption {$length} karakter; brand menyarankan minimal {$profile->caption_min_length}.", 'caption');
        }

        $tags = $this->hashtags($caption.' '.$revision->hashtags);
        $count = count($tags);
        if ($profile->hashtag_max !== null && $count > $profile->hashtag_max) {
            $findings[] = $this->error('too_many_hashtags', "{$count} hashtag; batas brand {$profile->hashtag_max}.", 'hashtags');
        }
        if ($profile->hashtag_min !== null && $count < $profile->hashtag_min) {
            $findings[] = $this->warning('too_few_hashtags', "{$count} hashtag; brand menyarankan minimal {$profile->hashtag_min}.", 'hashtags');
        }
        foreach ($profile->forbidden_hashtags as $tag) {
            if (in_array($this->tag($tag), $tags, true)) {
                $findings[] = $this->error('forbidden_hashtag', "Hashtag {$this->display($tag)} dilarang brand.", 'hashtags');
            }
        }
        $missing = array_values(array_filter($profile->required_hashtags, fn (string $tag): bool => ! in_array($this->tag($tag), $tags, true)));
        if ($missing !== []) {
            $findings[] = $this->warning('missing_hashtags', 'Hashtag wajib brand belum ada: '.implode(', ', array_map($this->display(...), $missing)).'.', 'hashtags');
        }

        if ($profile->allowed_ctas !== [] && trim($caption) !== '' && ! array_any($profile->allowed_ctas, fn (string $cta): bool => $this->contains($caption, $cta))) {
            $findings[] = $this->warning('no_cta', 'Caption belum memakai CTA yang disetujui brand.', 'caption');
        }

        return $findings;
    }

    /**
     * Statements of the facts content of this item may rely on: brand-wide or of its campaign, not retired, and not
     * expired on the planned business day.
     *
     * @return list<string>
     */
    public function usableFacts(ContentItem $item): array
    {
        $day = $item->planned_at->setTimezone(BusinessTime::zone())->toDateString();
        $item->loadMissing('brand.currentKnowledge.chunks');

        $facts = ContentFact::query()
            ->where('content_brand_id', $item->content_brand_id)
            ->where(fn ($q) => $q->whereNull('content_campaign_id')->orWhere('content_campaign_id', $item->content_campaign_id))
            ->usableOn($day)
            ->orderBy('id')
            ->pluck('statement')
            ->values()
            ->all();

        return [...$facts, ...($item->brand->currentKnowledge?->chunks->pluck('content')->all() ?? [])];
    }

    /**
     * The checks that need only the rules, the facts, and the text: used for stored content and for evaluating a
     * provider against fixtures without touching the database.
     *
     * @param  list<string>  $facts
     * @return list<RuleFinding>
     */
    public function evaluate(?ContentBrandProfile $profile, array $facts, ContentRevision $revision): array
    {
        return [...($profile === null ? [] : $this->profileRules($profile, $revision)), ...$this->claims($facts, $revision)];
    }

    /**
     * Prices and percentages must come from an approved fact. A price that no fact states is an error; a percentage
     * is a warning, because "100% responsif" is not always a business claim.
     *
     * @param  list<string>  $facts
     * @return list<RuleFinding>
     */
    private function claims(array $facts, ContentRevision $revision): array
    {
        $text = $revision->caption.' '.$revision->alt_text;
        preg_match_all('/Rp\.?\s?\d[\d.,]*|\d+(?:[.,]\d+)?\s?%/iu', $text, $matches);
        $claims = array_values(array_unique(array_map(static fn (string $m): string => rtrim($m, '.,'), $matches[0])));
        if ($claims === []) {
            return [];
        }

        $statements = implode("\n", array_map($this->compact(...), $facts));
        $findings = [];
        foreach ($claims as $claim) {
            if (! str_contains($statements, $this->compact($claim))) {
                $isPrice = stripos($claim, 'rp') === 0;
                $message = "\"{$claim}\" tidak ada di fakta yang disetujui untuk tanggal ini.";
                $findings[] = $isPrice ? $this->error('unapproved_price', $message, 'caption') : $this->warning('unapproved_number', $message, 'caption');
            }
        }

        return $findings;
    }

    /** @return list<RuleFinding> */
    private function plan(ContentItem $item): array
    {
        $findings = [];
        if ($item->pillar !== null && ! $item->pillar->is_active) {
            $findings[] = $this->warning('inactive_pillar', "Pilar \"{$item->pillar->name}\" sudah tidak aktif.");
        }
        $campaign = $item->campaign;
        if ($campaign !== null) {
            if (! $campaign->is_active) {
                $findings[] = $this->warning('inactive_campaign', "Kampanye \"{$campaign->name}\" sudah tidak aktif.");
            }
            if (! $campaign->covers($item->planned_at->setTimezone(BusinessTime::zone()))) {
                $findings[] = $this->warning('outside_campaign', "Rencana posting di luar periode kampanye \"{$campaign->name}\".");
            }
        }

        return $findings;
    }

    /** @return list<RuleFinding> */
    private function topicSpacing(ContentBrandProfile $profile, ContentItem $item): array
    {
        if ($profile->topic_repeat_days === null || $item->topic === null || trim($item->topic) === '') {
            return [];
        }
        $days = $profile->topic_repeat_days;
        $nearby = ContentItem::query()
            ->where('content_brand_id', $item->content_brand_id)
            ->whereKeyNot($item->id)
            ->where('status', '!=', ContentStatus::Rejected)
            ->whereRaw('lower(topic) = ?', [mb_strtolower(trim($item->topic))])
            ->whereBetween('planned_at', [$item->planned_at->copy()->subDays($days), $item->planned_at->copy()->addDays($days)])
            ->orderBy('planned_at')
            ->first();

        return $nearby === null ? [] : [$this->warning(
            'topic_repeated',
            "Topik \"{$item->topic}\" juga dipakai \"{$nearby->title}\" ({$nearby->planned_at->setTimezone(BusinessTime::zone())->format('d/m/Y')}); brand meminta jarak minimal {$days} hari.",
        )];
    }

    /** Whole-word, case-insensitive match of a phrase in a text. */
    private function contains(string $text, string $phrase): bool
    {
        $phrase = trim($phrase);
        if ($phrase === '' || $text === '') {
            return false;
        }
        $pattern = '/(?<![\p{L}\p{N}])'.preg_replace('/\s+/u', '\s+', preg_quote($phrase, '/')).'(?![\p{L}\p{N}])/iu';

        return preg_match($pattern, $text) === 1;
    }

    /** @return list<string> lower-case hashtags without '#' */
    private function hashtags(string $text): array
    {
        preg_match_all('/#([^\s#]+)/u', $text, $matches);

        return array_values(array_unique(array_map($this->tag(...), $matches[1])));
    }

    private function tag(string $tag): string
    {
        return mb_strtolower(ltrim(trim($tag), '#'));
    }

    private function display(string $tag): string
    {
        return '#'.ltrim(trim($tag), '#');
    }

    /** Lower case without spaces, so "Rp 99.000" and "rp99.000" compare equal. */
    private function compact(string $value): string
    {
        return (string) preg_replace('/\s+/u', '', mb_strtolower($value));
    }

    private function error(string $code, string $message, ?string $field = null): RuleFinding
    {
        return new RuleFinding($code, RuleSeverity::Error, $message, $field);
    }

    private function warning(string $code, string $message, ?string $field = null): RuleFinding
    {
        return new RuleFinding($code, RuleSeverity::Warning, $message, $field);
    }
}
