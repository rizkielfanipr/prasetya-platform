<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Content;

use Illuminate\Http\RedirectResponse;

/**
 * The retired Siap posting page (ADR-0012, phase 2). Ready posts are a Kalender filter now; old links and bookmarks land
 * on the list of Ready posts, soonest first. The posting reminder sits on the Kalender itself.
 */
final class ContentPostingController
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('content.calendar', ['view' => 'list', 'status' => 'READY', 'sort' => 'planned_at', 'direction' => 'asc']);
    }
}
