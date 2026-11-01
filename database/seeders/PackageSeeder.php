<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Brands\Enums\ApplicationCode;
use App\Domain\Brands\Models\Application;
use App\Domain\Packages\Models\Package;
use Illuminate\Database\Seeder;

/**
 * Codes are permanent. Webworks prices match the live pricing section of prasetya-webworks. Satamu's are
 * marked as dummy content in satamu-invitation, so they stay inactive in production until they are decided.
 */
class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $satamuActive = ! app()->isProduction();

        $catalogue = [
            [ApplicationCode::Webworks, 'PW-LANDING-STARTER', 'Landing Starter', 499000, true],
            [ApplicationCode::Webworks, 'PW-LANDING-PROFESSIONAL', 'Landing Professional', 799000, true],
            [ApplicationCode::Webworks, 'PW-COMPANY-PROFILE', 'Company Profile', 1999000, true],
            [ApplicationCode::Webworks, 'PW-BUSINESS-WEBSITE', 'Business Website', 2999000, true],
            [ApplicationCode::Satamu, 'SAT-SEDERHANA', 'Sederhana', 49000, $satamuActive],
            [ApplicationCode::Satamu, 'SAT-SPESIAL', 'Spesial', 79000, $satamuActive],
            [ApplicationCode::Satamu, 'SAT-ISTIMEWA', 'Istimewa', 149000, $satamuActive],
        ];

        foreach ($catalogue as $index => [$application, $code, $name, $price, $active]) {
            Package::query()->updateOrCreate(['code' => $code], [
                'application_id' => Application::query()->where('code', $application)->value('id'),
                'name' => $name,
                'price_amount' => $price,
                'currency' => 'IDR',
                'is_active' => $active,
                'sort_order' => $index,
            ]);
        }
    }
}
