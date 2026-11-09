<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use App\Domain\Content\Models\ContentCampaign;
use App\Domain\Content\Models\ContentPillar;
use App\Domain\Content\Support\ContentPlan;
use App\Support\BusinessTime;
use Illuminate\Foundation\Http\FormRequest;

/** An item's plan, shared by creating an item and changing its plan. Brand ownership is checked by ContentPlan. */
final class ContentDetailsRules
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'planned_at' => ['required', 'date_format:'.BusinessTime::LOCAL_FORMAT],
            'pillar' => ['nullable', 'string', 'size:26', 'exists:content_pillars,public_id'],
            'campaign' => ['nullable', 'string', 'size:26', 'exists:content_campaigns,public_id'],
            'topic' => ['nullable', 'string', 'max:80'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'planned_at.date_format' => 'Isi tanggal dan jam rencana posting.',
            'pillar.exists' => 'Pilih pilar yang ada.',
            'campaign.exists' => 'Pilih campaign yang ada.',
        ];
    }

    public static function plan(FormRequest $request, ?string $title = null): ContentPlan
    {
        $pillar = $request->validated('pillar');
        $campaign = $request->validated('campaign');
        $topic = $request->validated('topic');

        return new ContentPlan(
            $title ?? (string) $request->validated('title'),
            BusinessTime::parseLocal((string) $request->validated('planned_at')),
            is_string($pillar) ? ContentPillar::query()->where('public_id', $pillar)->firstOrFail() : null,
            is_string($campaign) ? ContentCampaign::query()->where('public_id', $campaign)->firstOrFail() : null,
            is_string($topic) ? $topic : null,
        );
    }
}
