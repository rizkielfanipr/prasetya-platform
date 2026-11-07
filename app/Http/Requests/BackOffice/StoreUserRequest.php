<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice;

use App\Domain\Auth\Username;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** A new user: name, a username fixed from now on, and an initial password typed by the creator (ADR-0009). */
final class StoreUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['username' => Username::normalize((string) $this->input('username', ''))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => Username::rules(),
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'username.regex' => 'Username hanya boleh huruf kecil, angka, titik, garis bawah, atau tanda hubung, dan harus diawali serta diakhiri huruf atau angka.',
            'username.unique' => 'Username ini sudah dipakai.',
        ];
    }
}
