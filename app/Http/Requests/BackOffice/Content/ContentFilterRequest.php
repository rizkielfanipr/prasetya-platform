<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use App\Domain\Content\Enums\ContentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filters shared by the calendar and the item list. */
final class ContentFilterRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'brand' => ['nullable', 'string', 'max:32'],
            'pillar' => ['nullable', 'string', 'size:26'],
            'campaign' => ['nullable', 'string', 'size:26'],
            'status' => ['nullable', Rule::enum(ContentStatus::class)],
            'week' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['planned_at', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
