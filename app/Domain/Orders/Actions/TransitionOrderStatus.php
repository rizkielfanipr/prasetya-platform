<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\TransitionSource;
use App\Domain\Orders\Exceptions\InvalidStateTransition;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderStatusHistory;
use App\Support\RequestContext;

/** The only way an order's status changes. Callers own the surrounding transaction. */
final class TransitionOrderStatus
{
    public function __construct(private readonly RequestContext $context) {}

    public function __invoke(Order $order, OrderStatus $to, TransitionSource $source, ?int $actorUserId = null, ?string $reason = null): void
    {
        $from = $order->status;

        if (! $from->canTransitionTo($to)) {
            throw InvalidStateTransition::between('Order', $from->value, $to->value);
        }

        $order->status = $to;

        if ($to === OrderStatus::Confirmed) {
            $order->confirmed_at = now();
        }

        $order->save();

        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'source' => $source,
            'actor_user_id' => $actorUserId,
            'reason' => $reason,
            'request_id' => $this->context->requestId(),
        ]);
    }
}
