<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'attempt_number' => 1,
            'provider' => 'DOKU',
            'status' => PaymentStatus::Pending,
            'amount' => fn (array $attributes) => Order::query()->whereKey($attributes['order_id'])->value('total_amount'),
            'currency' => 'IDR',
            'gateway_invoice_number' => fn (array $attributes) => Order::query()->whereKey($attributes['order_id'])->value('invoice_number'),
            'gateway_request_id' => (string) Str::uuid(),
            'checkout_url' => 'https://fake-doku.invalid/checkout/token',
            'expires_at' => now()->addHour(),
        ];
    }
}
