<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Orders\Actions\AddAdminNote;
use App\Domain\Orders\Models\Order;
use App\Http\Requests\BackOffice\StoreAdminNoteRequest;
use Illuminate\Http\RedirectResponse;

final class AdminNoteController
{
    public function store(StoreAdminNoteRequest $request, Order $order, AddAdminNote $addNote): RedirectResponse
    {
        $addNote($order, $request->user(), (string) $request->validated('body'));

        return back()->with('success', 'Catatan disimpan.');
    }
}
