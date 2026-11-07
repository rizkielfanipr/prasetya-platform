<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Exceptions\UserChangeRefused;
use App\Domain\Auth\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sets a new password for another user (ADR-0009: recovery without email) and signs them out everywhere:
 * their sessions are deleted and "remember me" stops working. Audited without the password.
 */
final class ResetUserPassword
{
    public function __construct(private readonly RecordAudit $audit) {}

    /** @throws UserChangeRefused when the actor targets themselves: their own change needs the current password. */
    public function __invoke(User $user, string $password, ?User $actor = null): void
    {
        if ($actor !== null && $actor->is($user)) {
            throw UserChangeRefused::ownPassword();
        }

        DB::transaction(function () use ($user, $password, $actor): void {
            $user->forceFill(['password' => $password, 'remember_token' => null])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            ($this->audit)('user.password_reset', 'user', $user->public_id, $actor, null, ['username' => $user->username]);
        });
    }
}
