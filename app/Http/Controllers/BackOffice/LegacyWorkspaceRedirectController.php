<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Payments\Enums\ReconciliationGroup;
use App\Http\Workspace\AttentionQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Compatibility redirects retained for at least one release cycle after ADR-0010 phase 4, updated by ADR-0016. */
final class LegacyWorkspaceRedirectController
{
    public function paymentEvents(Request $request): RedirectResponse
    {
        return redirect()->route('settings.gateway-log', $request->only(['search', 'status', 'date_from', 'date_to', 'page']));
    }

    public function reconciliation(Request $request): RedirectResponse
    {
        $group = ReconciliationGroup::tryFrom((string) $request->query('group'));
        if ($group === null) {
            return redirect()->route('transactions.index', ['view' => 'attention']);
        }

        $destination = AttentionQueue::destinationFor($group);

        return redirect()->route($destination['route'], $destination['query']);
    }

    public function auditLog(Request $request): RedirectResponse
    {
        return redirect()->route('settings.activity', [
            'scope' => 'all',
            ...$request->only(['search', 'date_from', 'date_to', 'page']),
        ]);
    }
}
