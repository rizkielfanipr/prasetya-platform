<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Audit\Models\AuditLog;
use App\Http\Presenters\TransactionPresenter;
use App\Support\DateRange;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AuditLogController
{
    public function __invoke(Request $request): Response
    {

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        $range = DateRange::fromBusinessDates($filters['date_from'] ?? null, $filters['date_to'] ?? null);

        $logs = AuditLog::query()
            ->when($filters['search'] ?? null, static function (Builder $q, string $search): void {
                $term = addcslashes($search, '%_\\');
                $q->where(fn (Builder $inner) => $inner->where('action', 'like', "{$term}%")->orWhere('entity_id', 'like', "{$term}%")->orWhere('actor_label', 'like', "{$term}%"));
            })
            ->when($range !== null, static fn (Builder $q) => $q->whereBetween('created_at', [$range->start, $range->end]))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('audit/AuditLogPage', [
            'filters' => array_merge(['search' => null, 'date_from' => null, 'date_to' => null], $filters),
            'logs' => Paginated::present($logs, TransactionPresenter::audit(...)),
        ]);
    }
}
