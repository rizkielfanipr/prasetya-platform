<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Compatibility redirects retained for at least one release cycle after ADR-0010 phase 4. */
final class LegacyWorkspaceRedirectController
{
    public function paymentEvents(Request $request): RedirectResponse
    {
        return redirect()->route('transactions.index', [
            'view' => 'events',
            ...$request->only(['search', 'status', 'date_from', 'date_to', 'page']),
        ]);
    }

    public function reconciliation(Request $request): RedirectResponse
    {
        return redirect()->route('transactions.index', [
            'view' => 'attention',
            ...$request->only(['group', 'page']),
        ]);
    }

    public function auditLog(Request $request): RedirectResponse
    {
        return redirect()->route('settings.activity', [
            'scope' => 'all',
            ...$request->only(['search', 'date_from', 'date_to', 'page']),
        ]);
    }
}
