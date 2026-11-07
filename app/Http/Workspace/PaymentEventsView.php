<?php

declare(strict_types=1);

namespace App\Http\Workspace;

use App\Domain\Payments\Enums\WebhookStatus;
use App\Domain\Payments\Models\WebhookEvent;
use App\Http\Presenters\TransactionPresenter;
use App\Support\DateRange;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Transaksi → Event Pembayaran: every webhook delivery, including those for an invoice no order matches. */
final class PaymentEventsView
{
    /** @return array<string, mixed> */
    public function props(Request $request): array
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(WebhookStatus::class)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $range = DateRange::fromBusinessDates($filters['date_from'] ?? null, $filters['date_to'] ?? null);

        $events = WebhookEvent::query()
            ->with('payment.order')
            ->when($filters['search'] ?? null, static fn (Builder $q, string $search) => $q->where('invoice_number', 'like', addcslashes($search, '%_\\').'%'))
            ->when($filters['status'] ?? null, static fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($range !== null, static fn (Builder $q) => $q->whereBetween('received_at', [$range->start, $range->end]))
            ->latest('received_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return [
            'filters' => array_merge(['search' => null, 'status' => null, 'date_from' => null, 'date_to' => null], array_intersect_key($filters, array_flip(['search', 'status', 'date_from', 'date_to']))),
            'events' => Paginated::present($events, TransactionPresenter::webhook(...)),
        ];
    }
}
