<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Content\Models\ContentBrand;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentBrand> */
class ContentBrandFactory extends Factory
{
    protected $model = ContentBrand::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => 'BRAND-'.fake()->unique()->numerify('####'),
            'name' => fake()->company(),
            'is_active' => true,
        ];
    }
}
