<?php

declare(strict_types=1);

namespace App\Domain\Payments\Queries;

use App\Domain\Brands\Enums\ApplicationCode;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** All dashboard aggregates are computed here, in SQL, over the complete dataset. */
final class DashboardMetrics
{
    /** @return array<string, array{value: int, previous: int}> */
    public function summary(DateRange $range, ?string $brand): array
    {
        $previous = $range->previous();

        return [
            'totalPaid' => $this->pair(fn (DateRange $r) => (int) $this->paid($r, $brand)->sum('amount'), $range, $previous),
            'successful' => $this->pair(fn (DateRange $r) => $this->paid($r, $brand)->count(), $range, $previous),
            'pending' => $this->pair(fn (DateRange $r) => $this->createdWithStatus($r, $brand, [PaymentStatus::Created, PaymentStatus::Pending])->count(), $range, $previous),
            'failedOrExpired' => $this->pair(fn (DateRange $r) => $this->createdWithStatus($r, $brand, [PaymentStatus::Failed, PaymentStatus::Expired])->count(), $range, $previous),
            'activeOrders' => $this->pair(fn (DateRange $r) => $this->activeOrders($r, $brand)->count(), $range, $previous),
        ];
    }

    /** @return list<array{brand: string, paidAmount: int, paidCount: int, pendingCount: int, activeOrders: int}> */
    public function brandSummaries(DateRange $range): array
    {
        return array_map(fn (ApplicationCode $code): array => [
            'brand' => $code->value,
            'paidAmount' => (int) $this->paid($range, $code->value)->sum('amount'),
            'paidCount' => $this->paid($range, $code->value)->count(),
            'pendingCount' => $this->createdWithStatus($range, $code->value, [PaymentStatus::Created, PaymentStatus::Pending])->count(),
            'activeOrders' => $this->activeOrders($range, $code->value)->count(),
        ], ApplicationCode::cases());
    }

    /** @return list<array{date: string, WEBWORKS: int, SATAMU: int}> One row per business day, gaps filled with zero. */
    public function revenueSeries(DateRange $range, ?string $brand): array
    {
        $timezone = (string) config('platform.business_timezone');
        // Offset arithmetic instead of CONVERT_TZ, which needs timezone tables that shared hosting often lacks.
        $offsetMinutes = (int) (Carbon::now($timezone)->getOffset() / 60);

        $rows = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->join('applications', 'applications.id', '=', 'orders.application_id')
            ->where('payments.status', PaymentStatus::Paid->value)
            ->whereBetween('payments.paid_at', [$range->start, $range->end])
            ->when($brand !== null, fn ($q) => $q->where('applications.code', $brand))
            ->selectRaw('DATE(DATE_ADD(payments.paid_at, INTERVAL ? MINUTE)) as day, applications.code as brand, SUM(payments.amount) as total', [$offsetMinutes])
            ->groupBy('day', 'brand')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[$row->day][$row->brand] = (int) $row->total;
        }

        $series = [];
        $cursor = $range->start->copy()->setTimezone($timezone)->startOfDay();
        $end = $range->end->copy()->setTimezone($timezone);

        while ($cursor->lte($end)) {
            $day = $cursor->format('Y-m-d');
            $series[] = [
                'date' => $day,
                'WEBWORKS' => $totals[$day]['WEBWORKS'] ?? 0,
                'SATAMU' => $totals[$day]['SATAMU'] ?? 0,
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /** @return array{value: int, previous: int} */
    private function pair(\Closure $metric, DateRange $range, DateRange $previous): array
    {
        return ['value' => $metric($range), 'previous' => $metric($previous)];
    }

    /** @return Builder<Payment> */
    private function paid(DateRange $range, ?string $brand): Builder
    {
        return Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$range->start, $range->end])
            ->when($brand !== null, fn (Builder $q) => $q->whereHas('order.application', fn (Builder $a) => $a->where('code', $brand)));
    }

    /**
     * @param  list<PaymentStatus>  $statuses
     * @return Builder<Payment>
     */
    private function createdWithStatus(DateRange $range, ?string $brand, array $statuses): Builder
    {
        return Payment::query()
            ->whereIn('status', $statuses)
            ->whereBetween('created_at', [$range->start, $range->end])
            ->when($brand !== null, fn (Builder $q) => $q->whereHas('order.application', fn (Builder $a) => $a->where('code', $brand)));
    }

    /** @return Builder<Order> */
    private function activeOrders(DateRange $range, ?string $brand): Builder
    {
        return Order::query()
            ->whereIn('status', OrderStatus::active())
            ->whereBetween('created_at', [$range->start, $range->end])
            ->when($brand !== null, fn (Builder $q) => $q->whereRelation('application', 'code', $brand));
    }
}
