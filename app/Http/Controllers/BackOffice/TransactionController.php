<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\ReconciliationGroup;
use App\Domain\Payments\Models\WebhookEvent;
use App\Domain\Payments\Queries\ReconciliationReport;
use App\Http\Presenters\ReconciliationPresenter;
use App\Http\Presenters\TransactionActivityPresenter;
use App\Http\Presenters\TransactionPresenter;
use App\Http\Workspace\AllTransactionsView;
use App\Http\Workspace\AttentionQueue;
use App\Http\Workspace\AttentionView;
use App\Http\Workspace\TransactionWorkspaceView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TransactionController
{
    /**
     * The Transaksi workspace (ADR-0010, ADR-0016): one route, two views. Each view validates its own filters and builds
     * its own props, so a filter of one view never reaches another. Links to the retired event view and to the old
     * reconciliation groups are sent to their new homes.
     */
    public function index(Request $request, AllTransactionsView $all, AttentionView $attention): Response|RedirectResponse
    {
        if ($request->query('view') === 'events') {
            return redirect()->route('settings.gateway-log', $request->only(['search', 'status', 'date_from', 'date_to', 'page']));
        }

        $group = ReconciliationGroup::tryFrom((string) $request->query('group'));
        if ($request->query('view') === 'attention' && $group !== null) {
            $destination = AttentionQueue::destinationFor($group);

            return redirect()->route($destination['route'], $destination['query']);
        }

        $view = TransactionWorkspaceView::fromQuery($request->query('view'));

        return Inertia::render('transactions/TransactionWorkspacePage', ['view' => $view->value, ...match ($view) {
            TransactionWorkspaceView::All => $all->props($request),
            TransactionWorkspaceView::Attention => $attention->props($request),
        }]);
    }

    public function show(Order $order, ReconciliationReport $report): Response
    {
        $order->loadMissing(['payments.events']);
        $paymentIds = $order->payments->pluck('public_id');

        $webhooks = WebhookEvent::query()->whereIn('payment_id', $order->payments->pluck('id'))->with('payment.order')->latest('received_at')->orderByDesc('id')->limit(50)->get();
        $audit = AuditLog::query()->forEntities(['order' => [$order->public_id], 'payment' => $paymentIds])->latest('id')->limit(50)->get();

        // Every active user sees evidence and audit (ADR-0009). Everything here belongs to this order only (ADR-0010).
        // Webhooks reach the page as entries of the one timeline; their diagnostics stay in the Log Gateway (ADR-0016).
        return Inertia::render('transactions/TransactionDetailPage', [
            'transaction' => TransactionPresenter::detail($order),
            'reconciliationIssues' => array_map(static fn (array $issue): array => ReconciliationPresenter::scoped($issue['group'], $issue['record']), $report->forOrder($order)),
            'activity' => TransactionActivityPresenter::timeline($order, $webhooks, $audit),
        ]);
    }
}
