<?php

declare(strict_types=1);

namespace App\Domain\Payments\Queries;

use App\Domain\Orders\Models\InvitationOrderDetail;
use App\Domain\Orders\Models\Order;
use App\Domain\Packages\Models\Package;
use App\Domain\Payments\Models\Payment;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class TransactionQuery
{
    public const array SORTABLE = ['created_at', 'total_amount', 'invoice_number'];

    /**
     * @param  array{search?: string|null, brand?: string|null, payment_status?: string|null, order_status?: string|null, payment_method?: string|null, package?: string|null, theme_code?: string|null, event_type?: string|null, event_date?: string|null, date_from?: string|null, date_to?: string|null, sort?: string|null, direction?: string|null}  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? (string) $filters['sort'] : 'created_at';
        $direction = ($filters['direction'] ?? null) === 'asc' ? 'asc' : 'desc';

        return $this->filtered($filters)
            ->with(['application', 'items', 'latestPayment'])
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction) // Stable when the sort column ties.
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return Builder<Order>
     */
    public function filtered(array $filters): Builder
    {
        $query = Order::query();

        if (filled($filters['search'] ?? null)) {
            $term = addcslashes((string) $filters['search'], '%_\\');
            $query->where(fn (Builder $q) => $q
                ->where('invoice_number', 'like', "{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")
                ->orWhere('customer_email', 'like', "{$term}%"));
        }

        if (filled($filters['brand'] ?? null)) {
            $query->whereRelation('application', 'code', $filters['brand']);
        }

        if (filled($filters['order_status'] ?? null)) {
            $query->where('status', $filters['order_status']);
        }

        if (filled($filters['payment_status'] ?? null)) {
            $query->whereHas('latestPayment', fn (Builder $q) => $q->where('status', $filters['payment_status']));
        }

        if (filled($filters['payment_method'] ?? null)) {
            $query->whereHas('latestPayment', fn (Builder $q) => $q->where('payment_method', $filters['payment_method']));
        }

        if (filled($filters['package'] ?? null)) {
            $query->whereHas('items', fn (Builder $q) => $q->where('package_code', $filters['package']));
        }

        // Brand detail filters. A Satamu detail filter therefore only ever matches Satamu orders.
        foreach (['theme_code', 'event_type'] as $column) {
            if (filled($filters[$column] ?? null)) {
                $query->whereHas('invitationDetail', fn (Builder $q) => $q->where($column, $filters[$column]));
            }
        }

        if (filled($filters['event_date'] ?? null)) {
            $query->whereHas('invitationDetail', fn (Builder $q) => $q->whereDate('event_date', (string) $filters['event_date']));
        }

        $range = DateRange::fromBusinessDates($filters['date_from'] ?? null, $filters['date_to'] ?? null);

        if ($range !== null) {
            $query->whereBetween('created_at', [$range->start, $range->end]);
        }

        return $query;
    }

    /**
     * Values the filter bar offers. Packages include inactive ones, because their orders stay in history.
     *
     * @return array{paymentMethods: list<string>, packages: list<array{value: string, label: string, brand: string}>, themeCodes: list<string>, eventTypes: list<string>}
     */
    public function filterOptions(): array
    {
        $distinct = static fn (string $column): array => InvitationOrderDetail::query()->whereNotNull($column)->distinct()->orderBy($column)->pluck($column)->map(strval(...))->values()->all();

        return [
            'paymentMethods' => Payment::query()->whereNotNull('payment_method')->distinct()->orderBy('payment_method')->pluck('payment_method')->map(strval(...))->values()->all(),
            'packages' => Package::query()->with('application')->orderBy('sort_order')->get()
                ->map(static fn (Package $package): array => ['value' => $package->code, 'label' => $package->name, 'brand' => $package->application->code->value])
                ->values()->all(),
            'themeCodes' => $distinct('theme_code'),
            'eventTypes' => $distinct('event_type'),
        ];
    }
}
