<?php

declare(strict_types=1);

namespace App\Domain\Auth\Enums;

enum Role: string
{
    case SuperAdmin = 'SUPER_ADMIN';
    case Finance = 'FINANCE';
    case Operations = 'OPERATIONS';

    /** @return list<Permission> */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::cases(),
            self::Finance => [
                Permission::ViewTransactions,
                Permission::AddOrderNotes,
                Permission::ViewPaymentEvents,
                Permission::ViewReconciliation,
                Permission::RunGatewayCheck,
                Permission::ViewAuditLog,
            ],
            self::Operations => [
                Permission::ViewTransactions,
                Permission::AddOrderNotes,
            ],
        };
    }

    public function allows(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
