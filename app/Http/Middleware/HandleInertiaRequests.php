<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\Models\User;
use App\Domain\Payments\Queries\ReconciliationReport;
use App\Support\RequestContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user instanceof User ? [
                    'id' => $user->public_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                ] : null,
                // Display hints only. Every one of these is enforced again by a policy on the server.
                'permissions' => $user instanceof User ? array_map(
                    static fn (Permission $p): string => $p->value,
                    array_values(array_filter($user->role->permissions(), $user->hasPermission(...))),
                ) : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'openIssueCount' => fn () => $user instanceof User && $user->hasPermission(Permission::ViewReconciliation)
                ? Cache::remember('reconciliation.open-issues', 60, static fn (): int => app(ReconciliationReport::class)->openIssueCount())
                : null,
            'requestId' => fn () => app(RequestContext::class)->requestId(),
        ];
    }
}
