<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use App\Domain\Content\Enums\RevisionArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'areas' => ['nullable', 'array', 'max:6'],
            'areas.*' => ['string', 'distinct', Rule::enum(RevisionArea::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['areas.*.enum' => 'Pilih bagian yang ada.', 'note.required' => 'Tulis catatan agar penulis tahu apa yang perlu diubah atau mengapa konten ditolak.'];
    }

    /** @return list<RevisionArea> */
    public function areas(): array
    {
        $areas = $this->validated('areas');

        return is_array($areas) ? array_values(array_map(static fn (string $a): RevisionArea => RevisionArea::from($a), $areas)) : [];
    }
}
