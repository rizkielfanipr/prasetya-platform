<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAdminNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('addNote', $this->route('order')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'min:3', 'max:2000']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['body' => 'catatan'];
    }
}
