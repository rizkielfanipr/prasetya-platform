<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Http\Workspace\PaymentEventsView;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PaymentEventController
{
    /** The old Event Pembayaran page, kept during the ADR-0010 transition; its data comes from the workspace view. */
    public function __invoke(Request $request, PaymentEventsView $events): Response
    {
        return Inertia::render('payments/PaymentEventsPage', $events->props($request));
    }
}
