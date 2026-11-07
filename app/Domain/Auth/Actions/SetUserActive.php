<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Exceptions\UserChangeRefused;
use App\Domain\Auth\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Activates or deactivates a user. Nobody can deactivate themselves, and the last active user stays active, so the
 * team can never lock itself out. Deactivation ends the user's sessions and "remember me" at once; their history
 * stays, because users are never deleted.
 */
final class SetUserActive
{
    public function __construct(private readonly RecordAudit $audit) {}

    /** @throws UserChangeRefused */
    public function __invoke(User $user, bool $active, ?User $actor): void
    {
        if (! $active && $actor !== null && $actor->is($user)) {
            throw UserChangeRefused::deactivateSelf();
        }

        DB::transaction(function () use ($user, $active, $actor): void {
            // Lock every active user so two admins cannot deactivate the last two at the same moment.
            $activeIds = User::query()->where('is_active', true)->lockForUpdate()->pluck('id');
            $user->refresh();

            if ($user->is_active === $active) {
                return;
            }

            if (! $active && $activeIds->reject(fn (int $id): bool => $id === $user->id)->isEmpty()) {
                throw UserChangeRefused::lastActiveUser();
            }

            $user->forceFill(['is_active' => $active] + ($active ? [] : ['remember_token' => null]))->save();

            if (! $active) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            ($this->audit)($active ? 'user.activated' : 'user.deactivated', 'user', $user->public_id, $actor, ['is_active' => ! $active], ['is_active' => $active]);
        });
    }
}
