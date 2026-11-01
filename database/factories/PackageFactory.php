<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Brands\Enums\ApplicationCode;
use App\Domain\Brands\Models\Application;
use App\Domain\Packages\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Package> */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'application_id' => fn () => Application::query()->where('code', ApplicationCode::Webworks)->value('id'),
            'code' => 'TEST-'.fake()->unique()->bothify('????-####'),
            'name' => fake()->words(2, true),
            'price_amount' => 500000,
            'currency' => 'IDR',
            'is_active' => true,
        ];
    }
}
