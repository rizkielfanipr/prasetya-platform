<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\Domain\Payments\Models\Payment;
use App\Integrations\Duitku\FakeNotificationSender;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Stands in for Duitku's hosted page while no account exists. The route only exists with the fake gateway outside production. */
final class FakeCheckoutController
{
    public function show(Payment $payment): View
    {
        return view('dev.fake-checkout', ['payment' => $payment, 'returnUrl' => $this->returnUrl($payment)]);
    }

    public function pay(Request $request, Payment $payment, FakeNotificationSender $sender): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(['SUCCESS', 'EXPIRED'])]]);

        if ($validated['status'] === 'SUCCESS') {
            $sender->pay($payment);
        } else {
            $sender->expire($payment);
        }

        return redirect()->away($this->returnUrl($payment));
    }

    private function returnUrl(Payment $payment): string
    {
        $payment->loadMissing('order.application');
        $base = (string) config('platform.return_urls.'.$payment->order->application->code->value);

        return $base.'?order='.$payment->order->public_id;
    }
}
