<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice;

use App\Domain\Brands\Enums\ApplicationCode;
use App\Domain\Payments\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DashboardFilterRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'brand' => ['nullable', Rule::enum(ApplicationCode::class)],
            'period' => ['nullable', Rule::in(['7', '30', '90'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
        ];
    }
}
