<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice;

use App\Domain\Brands\Enums\ApplicationCode;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Queries\TransactionQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransactionFilterRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', Rule::enum(ApplicationCode::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'order_status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_method' => ['nullable', 'string', 'max:64'],
            'package' => ['nullable', 'string', 'max:64'],
            'theme_code' => ['nullable', 'string', 'max:64'],
            'event_type' => ['nullable', 'string', 'max:64'],
            'event_date' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(TransactionQuery::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
