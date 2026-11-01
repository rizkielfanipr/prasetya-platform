<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Brands\Enums\ApplicationCode;
use App\Domain\Brands\Models\Application;
use App\Domain\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'application_id' => fn () => Application::query()->where('code', ApplicationCode::Webworks)->value('id'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '08'.fake()->numerify('##########'),
        ];
    }
}
