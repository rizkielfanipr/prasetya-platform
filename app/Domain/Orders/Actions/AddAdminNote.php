<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Orders\Models\AdminNote;
use App\Domain\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

final class AddAdminNote
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function __invoke(Order $order, User $author, string $body): AdminNote
    {
        return DB::transaction(function () use ($order, $author, $body): AdminNote {
            $note = AdminNote::query()->create(['order_id' => $order->id, 'author_user_id' => $author->id, 'body' => $body]);
            ($this->audit)('order.note_added', 'order', $order->public_id, $author);

            return $note;
        });
    }
}
