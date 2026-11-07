<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Payments\Actions\CheckPaymentWithGateway;
use App\Domain\Payments\Enums\WebhookStatus;
use App\Domain\Payments\Exceptions\GatewayException;
use App\Domain\Payments\Models\Payment;
use App\Http\Requests\BackOffice\GatewayCheckRequest;
use App\Http\Workspace\AttentionView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

final class ReconciliationController
{
    /** The old Rekonsiliasi page, kept during the ADR-0010 transition; its data comes from the workspace view. */
    public function index(Request $request, AttentionView $attention): Response
    {
        return Inertia::render('reconciliation/ReconciliationPage', $attention->props($request));
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
