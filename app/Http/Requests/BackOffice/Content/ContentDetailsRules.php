<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use App\Support\BusinessTime;

/** Title and planned time, shared by creating an item and changing its plan. */
final class ContentDetailsRules
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'planned_at' => ['required', 'date_format:'.BusinessTime::LOCAL_FORMAT],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return ['planned_at.date_format' => 'Isi tanggal dan jam rencana posting.'];
    }
}
