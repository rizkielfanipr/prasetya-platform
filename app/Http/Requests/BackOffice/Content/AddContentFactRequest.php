<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use App\Domain\Content\Enums\ContentFactCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** An approved fact and where it comes from (price list, contract, owner's message), never an AI answer. */
final class AddContentFactRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(ContentFactCategory::class)],
            'statement' => ['required', 'string', 'max:1000'],
            'source' => ['required', 'string', 'max:255'],
            'campaign' => ['nullable', 'string', 'size:26', 'exists:content_campaigns,public_id'],
            'valid_until' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['source.required' => 'Tulis sumber knowledge ini, misalnya daftar harga atau persetujuan pemilik.'];
    }
}
