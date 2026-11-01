<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Brands\Enums\ApplicationCode;
use App\Domain\Brands\Models\Application;
use Illuminate\Database\Seeder;

class ApplicationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ApplicationCode::cases() as $code) {
            Application::query()->updateOrCreate(['code' => $code], [
                'name' => $code->displayName(),
                'invoice_prefix' => $code->invoicePrefix(),
                'is_active' => true,
            ]);
        }
    }
}
