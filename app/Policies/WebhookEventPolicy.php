<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\Models\User;

final class WebhookEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewPaymentEvents);
    }
}
