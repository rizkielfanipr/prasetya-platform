<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice;

use Illuminate\Foundation\Http\FormRequest;

final class GatewayCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Every active user may do this (ADR-0009); the auth middleware is the gate.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:255']];
    }
}
