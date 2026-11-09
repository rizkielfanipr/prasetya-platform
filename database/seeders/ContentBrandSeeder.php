<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Content\Models\ContentBrand;
use Illuminate\Database\Seeder;

/** Content Studio brands approved by the owner (ADR-0011). Idempotent; safe in production. */
class ContentBrandSeeder extends Seeder
{
    public function run(): void
    {
        ContentBrand::query()->updateOrCreate(['code' => 'WEBWORKS'], ['name' => 'Prasetya Webworks', 'is_active' => true]);
    }
}
