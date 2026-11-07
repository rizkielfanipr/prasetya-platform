<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Models\User;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\WebhookEvent;
use App\Domain\Payments\Queries\TransactionQuery;
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

    public function show(Order $order): Response
    {
        $paymentIds = Payment::query()->where('order_id', $order->id)->pluck('id');

        // Every active user sees evidence and audit (ADR-0009: one access level).
        return Inertia::render('transactions/TransactionDetailPage', [
            'transaction' => TransactionPresenter::detail($order),
            'webhooks' => WebhookEvent::query()->whereIn('payment_id', $paymentIds)->with('payment.order')->latest('received_at')->limit(50)->get()->map(TransactionPresenter::webhook(...))->all(),
            'audit' => AuditLog::query()
                ->where(fn ($q) => $q->where(['entity_type' => 'order', 'entity_id' => $order->public_id])
                    ->orWhere(fn ($p) => $p->where('entity_type', 'payment')->whereIn('entity_id', Payment::query()->where('order_id', $order->id)->select('public_id'))))
                ->latest('id')->limit(50)->get()->map(TransactionPresenter::audit(...))->all(),
        ]);
    }
}
