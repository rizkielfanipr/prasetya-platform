<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Payments\Enums\WebhookStatus;
use App\Domain\Payments\Models\WebhookEvent;
use App\Http\Presenters\TransactionPresenter;
use App\Support\DateRange;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class PaymentEventController
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', WebhookEvent::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(WebhookStatus::class)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
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

        return Inertia::render('payments/PaymentEventsPage', [
            'filters' => array_merge(['search' => null, 'status' => null, 'date_from' => null, 'date_to' => null], $filters),
            'events' => Paginated::present($events, TransactionPresenter::webhook(...)),
        ]);
    }
}
