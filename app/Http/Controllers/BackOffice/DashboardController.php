<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Queries\DashboardMetrics;
use App\Domain\Payments\Queries\TransactionQuery;
use App\Http\Presenters\TransactionPresenter;
use App\Http\Requests\BackOffice\DashboardFilterRequest;
use App\Support\DateRange;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController
{
    public function __invoke(DashboardFilterRequest $request, DashboardMetrics $metrics, TransactionQuery $transactions): Response
    {
        Gate::authorize('viewAny', Order::class);

        $filters = $request->validated();
        $brand = $filters['brand'] ?? null;
        $period = (int) ($filters['period'] ?? 30);
        $range = DateRange::fromBusinessDates($filters['date_from'] ?? null, $filters['date_to'] ?? null) ?? DateRange::lastDays($period);

        return Inertia::render('dashboard/DashboardPage', [
            'filters' => [
                'brand' => $brand,
                'period' => (string) $period,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'payment_status' => $filters['payment_status'] ?? null,
            ],
            'summary' => $metrics->summary($range, $brand),
            'brands' => $metrics->brandSummaries($range),
            // Deferred so the summary paints first; the chart is also code-split on the client.
            'revenue' => Inertia::defer(static fn (): array => $metrics->revenueSeries($range, $brand)),
            'recentTransactions' => array_map(
                TransactionPresenter::row(...),
                $transactions->filtered(['brand' => $brand, 'payment_status' => $filters['payment_status'] ?? null])
                    ->with(['application', 'items', 'latestPayment'])
                    ->latest('id')
                    ->limit(8)
                    ->get()
                    ->all(),
            ),
        ]);
    }
}
