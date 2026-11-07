<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use Illuminate\Support\Facades\DB;

/** Changes a user's display name. The username never changes after creation (ADR-0009). */
final class UpdateUser
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function __invoke(User $user, string $name, User $actor): void
    {
        if ($user->name === $name) {
            return;
        }

        DB::transaction(function () use ($user, $name, $actor): void {
            $previous = $user->name;
            $user->forceFill(['name' => $name])->save();

            ($this->audit)('user.updated', 'user', $user->public_id, $actor, ['name' => $previous], ['name' => $name]);
        });
    }
}
