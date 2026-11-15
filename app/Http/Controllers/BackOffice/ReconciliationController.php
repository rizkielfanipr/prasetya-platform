<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Payments\Actions\CheckPaymentWithGateway;
use App\Domain\Payments\Enums\WebhookStatus;
use App\Domain\Payments\Exceptions\GatewayException;
use App\Domain\Payments\Models\Payment;
use App\Http\Requests\BackOffice\GatewayCheckRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

final class ReconciliationController
{
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
