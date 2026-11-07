<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates an active back-office user from validated input (ADR-0009): the creator types the initial password,
 * and the username is fixed from now on. Audited without the password.
 */
final class CreateUser
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function __invoke(string $name, string $username, string $password, ?User $actor = null): User
    {
        return DB::transaction(function () use ($name, $username, $password, $actor): User {
            $user = User::query()->create([
                'name' => $name,
                'username' => $username,
                'password' => $password,
                'is_active' => true,
            ]);

            ($this->audit)('user.created', 'user', $user->public_id, $actor, null, ['username' => $user->username, 'name' => $user->name]);

            return $user;
        });
    }
}
