<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use App\Domain\Content\Enums\CampaignPriority;
use App\Domain\Content\Models\ContentCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A campaign (ADR-0012, phase 4): name, period, and objective. Priority and the manual active switch are legacy: optional,
 * defaulting to NORMAL and active for a new campaign and kept unchanged on an existing one when not sent.
 */
final class SaveCampaignRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'objective' => ['nullable', 'string', 'max:2000'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'priority' => ['nullable', Rule::enum(CampaignPriority::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['ends_on.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.'];
    }

    public function priority(?ContentCampaign $current): CampaignPriority
    {
        $priority = $this->validated('priority');

        return is_string($priority) ? CampaignPriority::from($priority) : ($current->priority ?? CampaignPriority::Normal);
    }

    public function isActive(?ContentCampaign $current): bool
    {
        return $this->has('is_active') && $this->validated('is_active') !== null ? $this->boolean('is_active') : ($current->is_active ?? true);
    }
}
