<?php

declare(strict_types=1);

namespace App\Domain\Packages\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Packages\Models\Package;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only way a package becomes purchasable or stops being purchasable. Activation is a deliberate, audited act,
 * never a side effect of the environment or a re-seed. Deactivating stops new orders only: payment attempts already
 * issued keep receiving callbacks and status checks.
 */
final class SetPackageAvailability
{
    public function __construct(private readonly RecordAudit $audit) {}

    /** @return bool Whether the availability changed. */
    public function __invoke(string $code, bool $active, string $reason, ?User $actor = null): bool
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required to change package availability.');
        }

        return DB::transaction(function () use ($code, $active, $reason, $actor): bool {
            $package = Package::query()->where('code', $code)->lockForUpdate()->first()
                ?? throw new InvalidArgumentException("Unknown package code {$code}.");

            if ($package->is_active === $active) {
                return false;
            }

            $package->is_active = $active;
            $package->save();

            ($this->audit)(
                $active ? 'package.activated' : 'package.deactivated',
                'package',
                $package->code,
                $actor,
                ['is_active' => ! $active],
                ['is_active' => $active, 'price_amount' => $package->price_amount, 'currency' => $package->currency],
                $reason,
            );

            return true;
        });
    }
}
