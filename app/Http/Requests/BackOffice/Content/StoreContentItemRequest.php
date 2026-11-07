<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use App\Domain\Content\Support\ContentPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A planned post: brand, working title, and planned time in business time. */
final class StoreContentItemRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'brand' => ['required', 'string', Rule::exists('content_brands', 'code')->where('is_active', true)],
            ...ContentDetailsRules::rules(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['brand.exists' => 'Pilih brand yang aktif.', ...ContentDetailsRules::messages()];
    }

    public function plan(): ContentPlan
    {
        return ContentDetailsRules::plan($this);
    }
}
