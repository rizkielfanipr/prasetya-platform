<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Brands\Enums\ApplicationCode;
use App\Domain\Brands\Models\Application;
use App\Domain\Packages\Models\Package;
use Illuminate\Database\Seeder;

/**
 * Codes are permanent. Webworks prices match the live pricing section of prasetya-webworks. Satamu's are
 * marked as dummy content in satamu-invitation, so a new Satamu package starts inactive in every environment.
 *
 * Availability is catalogue state: the seeder sets it only when it creates a package and never changes it on an
 * existing one. Use `platform:package-activate` / `platform:package-deactivate` (SetPackageAvailability).
 */
class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = [
            [ApplicationCode::Webworks, 'PW-LANDING-STARTER', 'Landing Starter', 499000, true],
            [ApplicationCode::Webworks, 'PW-LANDING-PROFESSIONAL', 'Landing Professional', 799000, true],
            [ApplicationCode::Webworks, 'PW-COMPANY-PROFILE', 'Company Profile', 1999000, true],
            [ApplicationCode::Webworks, 'PW-BUSINESS-WEBSITE', 'Business Website', 2999000, true],
            [ApplicationCode::Satamu, 'SAT-SEDERHANA', 'Sederhana', 49000, false],
            [ApplicationCode::Satamu, 'SAT-SPESIAL', 'Spesial', 79000, false],
            [ApplicationCode::Satamu, 'SAT-ISTIMEWA', 'Istimewa', 149000, false],
        ];

        foreach ($catalogue as $index => [$application, $code, $name, $price, $activeWhenCreated]) {
            $package = Package::query()->firstOrNew(['code' => $code]);
            $package->fill([
                'application_id' => Application::query()->where('code', $application)->value('id'),
                'name' => $name,
                'price_amount' => $price,
                'currency' => 'IDR',
                'sort_order' => $index,
            ]);

            if (! $package->exists) {
                $package->is_active = $activeWhenCreated;
            }

            $package->save();
        }
    }
}
