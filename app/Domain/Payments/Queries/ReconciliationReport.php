<?php

declare(strict_types=1);

namespace App\Domain\Payments\Queries;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\ReconciliationGroup;
use App\Domain\Payments\Enums\WebhookRejection;
use App\Domain\Payments\Enums\WebhookStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/** Observational: every method here only reads. Nothing in this report repairs a record. */
final class ReconciliationReport
{
    /** @return array<string, int> */
    public function counts(): array
    {
        $counts = [];

        foreach (ReconciliationGroup::cases() as $group) {
            $counts[$group->value] = $this->query($group)->count();
        }

        return $counts;
    }

    public function openIssueCount(): int
    {
        $total = 0;

        foreach (ReconciliationGroup::cases() as $group) {
            if ($group->needsAction()) {
                $total += $this->query($group)->count();
            }
        }

        return $total;
    }

    /** @return LengthAwarePaginator<int, Model> */
    public function paginate(ReconciliationGroup $group, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($group)->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    /** @return Builder<covariant Model> */
    private function query(ReconciliationGroup $group): Builder
    {
        return match ($group) {
            ReconciliationGroup::OrdersWithoutAttempt => $this->ordersWithoutAttempt(),
            ReconciliationGroup::OrdersWithoutSuccess => $this->ordersWithoutSuccess(),
            ReconciliationGroup::UnmatchedPayments => $this->webhooksRejectedFor([WebhookRejection::UnknownInvoice]),
            ReconciliationGroup::AmountMismatch => $this->webhooksRejectedFor([WebhookRejection::AmountMismatch]),
            ReconciliationGroup::InvalidTransitions => $this->webhooksRejectedFor([WebhookRejection::InvalidTransition, WebhookRejection::UnsupportedStatus]),
            ReconciliationGroup::RejectedWebhooks => WebhookEvent::query()->with('payment.order.application')
                ->where('status', WebhookStatus::Rejected)
                ->whereNotIn('rejection_reason', [
                    WebhookRejection::UnknownInvoice->value,
                    WebhookRejection::AmountMismatch->value,
                    WebhookRejection::InvalidTransition->value,
                    WebhookRejection::UnsupportedStatus->value,
                ]),
            ReconciliationGroup::DuplicateWebhooks => WebhookEvent::query()->with('payment.order.application')->where('status', WebhookStatus::Duplicate),
            ReconciliationGroup::PendingTooLong => Payment::query()->with('order.application')
                ->whereIn('status', [PaymentStatus::Created, PaymentStatus::Pending])
                ->where('created_at', '<', now()->subMinutes((int) config('platform.reconciliation.pending_too_long_minutes'))),
            ReconciliationGroup::PaidInconsistent => $this->paidInconsistent(),
        };
    }

    /** @return Builder<Order> */
    private function ordersWithoutAttempt(): Builder
    {
        return Order::query()->with(['application', 'latestPayment'])
            ->where('status', OrderStatus::PendingPayment)
            ->where('created_at', '<', now()->subMinutes((int) config('platform.reconciliation.no_attempt_after_minutes')))
            ->whereDoesntHave('payments');
    }

    /** @return Builder<Order> */
    private function ordersWithoutSuccess(): Builder
    {
        return Order::query()->with(['application', 'latestPayment'])
            ->where('status', OrderStatus::PendingPayment)
            ->where('created_at', '<', now()->subMinutes((int) config('platform.reconciliation.pending_too_long_minutes')))
            ->whereHas('payments')
            ->whereDoesntHave('payments', fn (Builder $q) => $q->where('status', PaymentStatus::Paid));
    }

    /**
     * Orders whose two statuses disagree: paid but not confirmed, confirmed without a paid
     * payment, or paid more than once. Grouped on the order so each problem appears once.
     *
     * @return Builder<Order>
     */
    private function paidInconsistent(): Builder
    {
        return Order::query()->with(['application', 'latestPayment'])->where(fn (Builder $q) => $q
            ->where(fn (Builder $paidNotConfirmed) => $paidNotConfirmed
                ->where('status', '!=', OrderStatus::Confirmed)
                ->whereHas('payments', fn (Builder $p) => $p->where('status', PaymentStatus::Paid)))
            ->orWhere(fn (Builder $confirmedNotPaid) => $confirmedNotPaid
                ->where('status', OrderStatus::Confirmed)
                ->whereDoesntHave('payments', fn (Builder $p) => $p->where('status', PaymentStatus::Paid)))
            ->orWhereHas('payments', fn (Builder $p) => $p->where('status', PaymentStatus::Paid), '>', 1));
    }

    /**
     * @param  list<WebhookRejection>  $reasons
     * @return Builder<WebhookEvent>
     */
    private function webhooksRejectedFor(array $reasons): Builder
    {
        return WebhookEvent::query()->with('payment.order.application')
            ->where('status', WebhookStatus::Rejected)
            ->whereIn('rejection_reason', array_map(fn (WebhookRejection $r): string => $r->value, $reasons));
    }
}
