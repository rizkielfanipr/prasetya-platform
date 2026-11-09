<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use Illuminate\Foundation\Http\FormRequest;

final class SaveBrandKnowledgeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['source_text' => ['required', 'string', 'max:50000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['source_text.required' => 'Tulis informasi brand terlebih dahulu.', 'source_text.max' => 'Brand Knowledge maksimal 50.000 karakter.'];
    }
}
