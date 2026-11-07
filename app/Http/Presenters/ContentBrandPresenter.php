<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentBrandProfile;
use App\Domain\Content\Models\ContentCampaign;
use App\Domain\Content\Models\ContentFact;
use App\Domain\Content\Models\ContentPillar;
use App\Domain\Content\Support\RuleFinding;

/** Brand knowledge for the screen; resources/js/types/content.ts mirrors it. */
final class ContentBrandPresenter
{
    /** @return array<string, mixed> */
    public static function rules(ContentBrandProfile $profile): array
    {
        return [
            'version' => $profile->version,
            'audience' => $profile->audience,
            'voice' => $profile->voice,
            'visualGuidance' => $profile->visual_guidance,
            'preferredTerms' => $profile->preferred_terms,
            'forbiddenTerms' => $profile->forbidden_terms,
            'forbiddenClaims' => $profile->forbidden_claims,
            'allowedCtas' => $profile->allowed_ctas,
            'requiredHashtags' => $profile->required_hashtags,
            'forbiddenHashtags' => $profile->forbidden_hashtags,
            'approvedExamples' => $profile->approved_examples,
            'rejectedExamples' => $profile->rejected_examples,
            'captionMinLength' => $profile->caption_min_length,
            'captionMaxLength' => $profile->caption_max_length,
            'hashtagMin' => $profile->hashtag_min,
            'hashtagMax' => $profile->hashtag_max,
            'topicRepeatDays' => $profile->topic_repeat_days,
            'changeNote' => $profile->change_note,
            'createdBy' => $profile->creator->name ?? 'Sistem',
            'createdAt' => $profile->created_at->toIso8601ZuluString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function version(ContentBrandProfile $profile): array
    {
        return [
            'version' => $profile->version,
            'changeNote' => $profile->change_note,
            'createdBy' => $profile->creator->name ?? 'Sistem',
            'createdAt' => $profile->created_at->toIso8601ZuluString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function pillar(ContentPillar $pillar): array
    {
        return ['id' => $pillar->public_id, 'name' => $pillar->name, 'description' => $pillar->description, 'targetShare' => $pillar->target_share, 'isActive' => $pillar->is_active];
    }

    /** @return array<string, mixed> */
    public static function campaign(ContentCampaign $campaign): array
    {
        return [
            'id' => $campaign->public_id,
            'name' => $campaign->name,
            'objective' => $campaign->objective,
            'startsOn' => $campaign->starts_on->toDateString(),
            'endsOn' => $campaign->ends_on->toDateString(),
            'priority' => $campaign->priority->value,
            'isActive' => $campaign->is_active,
        ];
    }

    /** @return array<string, mixed> */
    public static function fact(ContentFact $fact): array
    {
        return [
            'id' => $fact->public_id,
            'statement' => $fact->statement,
            'source' => $fact->source,
            'campaign' => $fact->campaign?->name,
            'validUntil' => $fact->valid_until?->toDateString(),
            'createdBy' => $fact->creator->name,
            'createdAt' => $fact->created_at->toIso8601ZuluString(),
            'retiredAt' => $fact->retired_at?->toIso8601ZuluString(),
            'retiredBy' => $fact->retiredBy?->name,
            'retiredReason' => $fact->retired_reason,
        ];
    }

    /**
     * Pillars and campaigns an item of this brand can be planned with. Needs pillars and campaigns loaded.
     *
     * @return array{pillars: list<array<string, mixed>>, campaigns: list<array<string, mixed>>}
     */
    public static function planOptions(ContentBrand $brand): array
    {
        return [
            'pillars' => $brand->pillars->map(self::pillar(...))->values()->all(),
            'campaigns' => $brand->campaigns->map(self::campaign(...))->values()->all(),
        ];
    }

    /**
     * @param  list<RuleFinding>  $findings
     * @return list<array{code: string, severity: string, message: string, field: string|null}>
     */
    public static function findings(array $findings): array
    {
        return array_map(static fn (RuleFinding $finding): array => $finding->toArray(), $findings);
    }
}
