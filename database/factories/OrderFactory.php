<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Brands\Enums\ApplicationCode;
use App\Domain\Brands\Models\Application;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Packages\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'application_id' => fn () => Application::query()->where('code', ApplicationCode::Webworks)->value('id'),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['application_id' => $attributes['application_id']])->id,
            'invoice_number' => 'PW-'.fake()->unique()->numerify('202611-######'),
            'status' => OrderStatus::PendingPayment,
            'total_amount' => 500000,
            'currency' => 'IDR',
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => '08'.fake()->numerify('##########'),
            'expires_at' => now()->addDays(3),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Order $order): void {
            $package = Package::query()->where('application_id', $order->application_id)->first()
                ?? Package::factory()->create(['application_id' => $order->application_id]);

            OrderItem::query()->create([
                'order_id' => $order->id,
                'package_id' => $package->id,
                'package_code' => $package->code,
                'package_name' => $package->name,
                'unit_amount' => $order->total_amount,
                'quantity' => 1,
                'line_amount' => $order->total_amount,
            ]);
        });
    }
}
