<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use Illuminate\Foundation\Http\FormRequest;

/** A review decision on one revision. Approval may carry a note; a revision request or rejection must. */
final class ReviewContentRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $noteRequired = ! $this->routeIs('content.items.approve');

        return [
            'revision' => ['required', 'string', 'size:26'],
            'note' => [$noteRequired ? 'required' : 'nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['note.required' => 'Tulis catatan agar penulis tahu apa yang perlu diubah atau mengapa konten ditolak.'];
    }
}
