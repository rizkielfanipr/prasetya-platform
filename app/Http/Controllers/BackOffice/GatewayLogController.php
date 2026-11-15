<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Http\Workspace\GatewayLogView;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Pengaturan → Log Gateway (ADR-0016): every webhook delivery, for diagnosis. Daily review is Perlu Ditinjau. */
final class GatewayLogController
{
    public function __invoke(Request $request, GatewayLogView $log): Response
    {
        return Inertia::render('settings/GatewayLogPage', $log->props($request));
    }
}
