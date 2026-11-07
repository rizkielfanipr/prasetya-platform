<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use Illuminate\Foundation\Http\FormRequest;

final class SavePillarRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_share' => ['required', 'integer', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
