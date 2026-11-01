<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\Models\User;
use App\Domain\Orders\Models\Order;

final class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewTransactions);
    }

    public function view(User $user, Order $order): bool
    {
        return $user->hasPermission(Permission::ViewTransactions);
    }

    public function addNote(User $user, Order $order): bool
    {
        return $user->hasPermission(Permission::AddOrderNotes);
    }
}
