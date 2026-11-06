<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\Models\User;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\WebhookEvent;
use App\Domain\Payments\Queries\TransactionQuery;
use App\Http\Presenters\TransactionPresenter;
use App\Http\Requests\BackOffice\TransactionFilterRequest;
use App\Support\Paginated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class TransactionController
{
    public function index(TransactionFilterRequest $request, TransactionQuery $query): Response
    {
        Gate::authorize('viewAny', Order::class);

        $filters = $request->validated();

        return Inertia::render('transactions/TransactionListPage', [
            'filters' => array_merge(array_fill_keys(['search', 'brand', 'payment_status', 'order_status', 'payment_method', 'package', 'theme_code', 'event_type', 'event_date', 'date_from', 'date_to', 'sort', 'direction'], null), $filters),
            ...$query->filterOptions(),
            'transactions' => Paginated::present($query->paginate($filters), TransactionPresenter::row(...)),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        Gate::authorize('view', $order);

        /** @var User $user */
        $user = $request->user();
        $paymentIds = Payment::query()->where('order_id', $order->id)->pluck('id');
        $canSeeEvidence = $user->hasPermission(Permission::ViewPaymentEvents);

        return Inertia::render('transactions/TransactionDetailPage', [
            'transaction' => TransactionPresenter::detail($order),
            // Evidence and audit are finance data: withheld server-side, not just hidden in the UI.
            'webhooks' => $canSeeEvidence
                ? WebhookEvent::query()->whereIn('payment_id', $paymentIds)->with('payment.order')->latest('received_at')->limit(50)->get()->map(TransactionPresenter::webhook(...))->all()
                : null,
            'audit' => $user->hasPermission(Permission::ViewAuditLog)
                ? AuditLog::query()
                    ->where(fn ($q) => $q->where(['entity_type' => 'order', 'entity_id' => $order->public_id])
                        ->orWhere(fn ($p) => $p->where('entity_type', 'payment')->whereIn('entity_id', Payment::query()->where('order_id', $order->id)->select('public_id'))))
                    ->latest('id')->limit(50)->get()->map(TransactionPresenter::audit(...))->all()
                : null,
        ]);
    }
}
