<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\WebhookEvent;
use App\Domain\Payments\Queries\ReconciliationReport;
use App\Domain\Payments\Queries\TransactionQuery;
use App\Http\Presenters\ReconciliationPresenter;
use App\Http\Presenters\TransactionActivityPresenter;
use App\Http\Presenters\TransactionPresenter;
use App\Http\Requests\BackOffice\TransactionFilterRequest;
use App\Support\Paginated;
use Inertia\Inertia;
use Inertia\Response;

final class TransactionController
{
    public function index(TransactionFilterRequest $request, TransactionQuery $query): Response
    {
        $filters = $request->validated();

        return Inertia::render('transactions/TransactionListPage', [
            'filters' => array_merge(array_fill_keys(['search', 'brand', 'payment_status', 'order_status', 'payment_method', 'package', 'theme_code', 'event_type', 'event_date', 'date_from', 'date_to', 'sort', 'direction'], null), $filters),
            ...$query->filterOptions(),
            'transactions' => Paginated::present($query->paginate($filters), TransactionPresenter::row(...)),
        ]);
    }

    public function show(Order $order, ReconciliationReport $report): Response
    {
        $order->loadMissing(['payments.events']);
        $paymentIds = $order->payments->pluck('public_id');

        $webhooks = WebhookEvent::query()->whereIn('payment_id', $order->payments->pluck('id'))->with('payment.order')->latest('received_at')->orderByDesc('id')->limit(50)->get();
        $audit = AuditLog::query()->forEntities(['order' => [$order->public_id], 'payment' => $paymentIds])->latest('id')->limit(50)->get();

        // Every active user sees evidence and audit (ADR-0009). Everything here belongs to this order only (ADR-0010).
        return Inertia::render('transactions/TransactionDetailPage', [
            'transaction' => TransactionPresenter::detail($order),
            'webhooks' => $webhooks->map(TransactionPresenter::webhook(...))->all(),
            'reconciliationIssues' => array_map(static fn (array $issue): array => ReconciliationPresenter::scoped($issue['group'], $issue['record']), $report->forOrder($order)),
            'activity' => TransactionActivityPresenter::timeline($order, $webhooks, $audit),
        ]);
    }
}
