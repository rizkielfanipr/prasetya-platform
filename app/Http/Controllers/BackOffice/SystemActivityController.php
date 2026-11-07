<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Audit\Queries\AuditTrail;
use App\Http\Presenters\TransactionPresenter;
use App\Support\Paginated;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan → Aktivitas Sistem (ADR-0010): audit that belongs to no transaction and to the management of no user.
 * "Semua audit" keeps a search across every entry, as the old Audit Log page had.
 */
final class SystemActivityController
{
    public function __invoke(Request $request, AuditTrail $trail): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', Rule::in(['system', 'all'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $logs = $trail->search($filters)->latest('id')->paginate(20)->withQueryString();

        return Inertia::render('settings/SystemActivityPage', [
            'filters' => array_merge(['search' => null, 'scope' => null, 'date_from' => null, 'date_to' => null], array_intersect_key($filters, array_flip(['search', 'scope', 'date_from', 'date_to']))),
            'logs' => Paginated::present($logs, TransactionPresenter::audit(...)),
        ]);
    }
}
