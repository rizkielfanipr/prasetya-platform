<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([ApplicationSeeder::class, PackageSeeder::class, ContentBrandSeeder::class, AdminUserSeeder::class]);

        if ($this->container->environment('local', 'testing', 'e2e')) {
            $this->call([DemoDataSeeder::class, ContentDemoSeeder::class]);
        }
    }
}
