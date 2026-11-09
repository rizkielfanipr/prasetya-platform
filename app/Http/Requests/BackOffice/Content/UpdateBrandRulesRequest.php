<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use Illuminate\Foundation\Http\FormRequest;

/** One canonical Brand Guide document plus the audit note required for its new version. */
final class UpdateBrandRulesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'guide' => ['required', 'string', 'max:30000'],
            'change_note' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'guide.required' => 'Isi panduan brand.',
            'guide.max' => 'Panduan brand maksimal 30.000 karakter.',
            'change_note.required' => 'Tulis alasan perubahan aturan.',
        ];
    }
}
