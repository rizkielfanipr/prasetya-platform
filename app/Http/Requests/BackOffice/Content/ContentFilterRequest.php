<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Migration\LegacyStatusMap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filters shared by the calendar and the item list. */
final class ContentFilterRequest extends FormRequest
{
    /** A bookmarked filter on a retired status (ADR-0011) still opens: it reads as the status it became (ADR-0012). */
    protected function prepareForValidation(): void
    {
        $status = $this->query('status');
        if (is_string($status) && ContentStatus::tryFrom($status) === null && array_key_exists($status, LegacyStatusMap::FORWARD)) {
            $this->merge(['status' => LegacyStatusMap::forward($status)]);
        }
    }

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
