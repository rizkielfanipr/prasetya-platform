<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Payments\Actions\CheckPaymentWithGateway;
use App\Domain\Payments\Enums\ReconciliationGroup;
use App\Domain\Payments\Enums\WebhookStatus;
use App\Domain\Payments\Exceptions\GatewayException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Queries\ReconciliationReport;
use App\Http\Presenters\ReconciliationPresenter;
use App\Http\Requests\BackOffice\GatewayCheckRequest;
use App\Support\Paginated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ReconciliationController
{
    public function index(Request $request, ReconciliationReport $report): Response
    {

        $validated = $request->validate(['group' => ['nullable', Rule::enum(ReconciliationGroup::class)]]);
        $group = ReconciliationGroup::tryFrom((string) ($validated['group'] ?? '')) ?? ReconciliationGroup::OrdersWithoutAttempt;

        return Inertia::render('reconciliation/ReconciliationPage', [
            'group' => $group->value,
            'counts' => $report->counts(),
            'thresholds' => config('platform.reconciliation'),
            'issues' => Paginated::present($report->paginate($group), ReconciliationPresenter::row(...)),
        ]);
    }

    public function checkStatus(GatewayCheckRequest $request, Payment $payment, CheckPaymentWithGateway $check): RedirectResponse
    {
        try {
            $outcome = $check($payment, $request->user(), $request->validated('reason'));
        } catch (GatewayException $e) {
            return back()->with('error', "Status gateway belum dapat diperiksa ({$e->failureCode}). Tidak ada data yang berubah.");
        }

        Cache::forget('reconciliation.open-issues');

        return back()->with('success', match ($outcome?->status) {
            null => 'Gateway belum memiliki transaksi ini. Tidak ada data yang berubah.',
            WebhookStatus::Processed => 'Status diperbarui dari bukti gateway.',
            WebhookStatus::Rejected => "Bukti gateway ditolak ({$outcome->rejection?->value}). Tidak ada data yang berubah.",
            default => 'Gateway belum melaporkan perubahan. Tidak ada data yang berubah.',
        });
    }
}
