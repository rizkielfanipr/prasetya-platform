<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Username;
use Illuminate\Database\Seeder;

/** Credentials only ever come from the environment. Nothing is created if they are absent. */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $username = Username::normalize((string) config('platform.admin.username'));
        $password = config('platform.admin.password');

        if (blank($username) || blank($password)) {
            $this->command?->warn('ADMIN_USERNAME/ADMIN_PASSWORD kosong: admin tidak dibuat. Gunakan php artisan platform:create-admin.');

            return;
        }

        User::query()->firstOrCreate(['username' => $username], [
            'name' => (string) config('platform.admin.name'),
            'password' => (string) $password,
        ]);
    }
}
