<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Audit\Queries\AuditTrail;
use App\Http\Presenters\TransactionPresenter;
use App\Support\Paginated;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AuditLogController
{
    /** The old Audit Log page (every entry), kept during the ADR-0010 transition. Its query is AuditTrail's. */
    public function __invoke(Request $request, AuditTrail $trail): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $logs = $trail->search([...$filters, 'scope' => 'all'])->latest('id')->paginate(20)->withQueryString();

        return Inertia::render('audit/AuditLogPage', [
            'filters' => array_merge(['search' => null, 'date_from' => null, 'date_to' => null], $filters),
            'logs' => Paginated::present($logs, TransactionPresenter::audit(...)),
        ]);
    }
}
