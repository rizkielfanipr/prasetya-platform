<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\Models\User;
use App\Domain\Payments\Models\Payment;

final class PaymentPolicy
{
    public function checkWithGateway(User $user, Payment $payment): bool
    {
        return $user->hasPermission(Permission::RunGatewayCheck);
    }
}
