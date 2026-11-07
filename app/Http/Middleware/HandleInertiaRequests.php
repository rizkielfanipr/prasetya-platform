<?php

declare(strict_types=1);

namespace App\Http\Middleware;

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
                    'username' => $user->username,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'openIssueCount' => fn () => $user instanceof User
                ? Cache::remember('reconciliation.open-issues', 60, static fn (): int => app(ReconciliationReport::class)->openIssueCount())
                : null,
            'requestId' => fn () => app(RequestContext::class)->requestId(),
        ];
    }
}
