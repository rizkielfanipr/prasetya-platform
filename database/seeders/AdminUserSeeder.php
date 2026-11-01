<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Models\User;
use Illuminate\Database\Seeder;

/** Credentials only ever come from the environment. Nothing is created if they are absent. */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('platform.admin.email');
        $password = config('platform.admin.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn('ADMIN_EMAIL/ADMIN_PASSWORD kosong: admin tidak dibuat. Gunakan php artisan platform:create-admin.');

            return;
        }

        User::query()->firstOrCreate(['email' => $email], [
            'name' => (string) config('platform.admin.name'),
            'password' => (string) $password,
            'role' => Role::SuperAdmin,
        ]);
    }
}
