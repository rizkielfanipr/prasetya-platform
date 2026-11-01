<?php

declare(strict_types=1);

namespace App\Domain\Packages\Services;

use App\Domain\Brands\Models\Application;
use App\Domain\Packages\Models\Package;
use App\Support\Api\ApiErrorCode;
use App\Support\Api\ApiException;

/** Authoritative source of what can be bought, by which brand, at what price. */
final class PackageCatalogue
{
    public function purchasable(Application $application, string $code): Package
    {
        $package = Package::query()->where('code', $code)->first();

        if ($package === null) {
            throw new ApiException(ApiErrorCode::PackageNotFound, 'Paket tidak ditemukan.', 422, ['package_code' => ['Paket tidak ditemukan.']]);
        }

        if ($package->application_id !== $application->id) {
            throw new ApiException(ApiErrorCode::PackageApplicationMismatch, 'Paket tidak tersedia untuk brand ini.', 422, ['package_code' => ['Paket tidak tersedia untuk brand ini.']]);
        }

        if (! $package->is_active) {
            throw new ApiException(ApiErrorCode::PackageInactive, 'Paket sedang tidak tersedia.', 422, ['package_code' => ['Paket sedang tidak tersedia.']]);
        }

        return $package;
    }
}
