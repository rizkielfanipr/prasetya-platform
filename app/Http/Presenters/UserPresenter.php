<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Domain\Auth\Models\User;

/** The back-office contract for users; resources/js/types/backoffice.ts mirrors it. Never includes the email. */
final class UserPresenter
{
    /** @return array<string, mixed> */
    public static function row(User $user, User $viewer, int $activeCount): array
    {
        $isSelf = $user->is($viewer);

        return [
            'id' => $user->public_id,
            'name' => $user->name,
            'username' => $user->username,
            'isActive' => $user->is_active,
            'isSelf' => $isSelf,
            // Why "Nonaktifkan" is unavailable, if it is: the same rules SetUserActive enforces on the server.
            'deactivateBlockedReason' => match (true) {
                ! $user->is_active => null,
                $isSelf => 'Anda tidak dapat menonaktifkan akun sendiri.',
                $activeCount <= 1 => 'Pengguna aktif terakhir tidak dapat dinonaktifkan.',
                default => null,
            },
            'lastLoginAt' => $user->last_login_at?->toIso8601ZuluString(),
            'createdAt' => $user->created_at->toIso8601ZuluString(),
        ];
    }
}
