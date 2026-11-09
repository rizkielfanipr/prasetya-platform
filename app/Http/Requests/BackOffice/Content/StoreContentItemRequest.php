<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Support\ContentPlan;
use App\Domain\Content\Support\IdeaPlan;
use App\Support\BusinessTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new post (ADR-0012, phase 3): an idea, the planned time in business time, and optionally a campaign. The working
 * title comes from the idea and the idea becomes the first brief. A title, pillar, or topic sent explicitly still wins.
 */
final class StoreContentItemRequest extends FormRequest
{
    public const int IDEA_MAX = 1000;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $details = ContentDetailsRules::rules();

        return [
            'brand' => ['required', 'string', Rule::exists('content_brands', 'code')->where('is_active', true)],
            'idea' => ['required_without:title', 'nullable', 'string', 'max:'.self::IDEA_MAX],
            ...$details,
            'title' => ['required_without:idea', 'nullable', 'string', 'max:160'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'brand.exists' => 'Pilih brand yang aktif.',
            'idea.required_without' => 'Tulis ide atau topik konten.',
            ...ContentDetailsRules::messages(),
        ];
    }

    /** The idea, trimmed, or null when none was given. */
    public function idea(): ?string
    {
        $idea = $this->validated('idea');
        $idea = is_string($idea) ? trim($idea) : '';

        return $idea === '' ? null : $idea;
    }

    public function plan(ContentBrand $brand): ContentPlan
    {
        $idea = $this->idea();
        $title = $this->validated('title');
        $explicit = ContentDetailsRules::plan($this, is_string($title) && trim($title) !== '' ? $title : IdeaPlan::title((string) $idea));

        return new ContentPlan(
            $explicit->title,
            BusinessTime::parseLocal((string) $this->validated('planned_at')),
            $explicit->pillar ?? ($idea === null ? null : IdeaPlan::pillar($brand, $idea)),
            $explicit->campaign,
            $explicit->topic,
        );
    }
}
