<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice;

use Illuminate\Foundation\Http\FormRequest;

final class GatewayCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('checkWithGateway', $this->route('payment')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:255']];
    }
}
