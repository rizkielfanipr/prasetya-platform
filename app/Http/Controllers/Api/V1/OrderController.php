<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Orders\Actions\CreateOrder;
use App\Domain\Orders\Models\Order;
use App\Http\Requests\Api\StoreOrderRequest;
use App\Http\Resources\Api\OrderResource;
use App\Http\Resources\Api\PaymentStatusResource;
use App\Support\Api\ApiResponse;
use App\Support\Idempotency\IdempotencyGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrderController
{
    public function store(StoreOrderRequest $request, CreateOrder $createOrder, IdempotencyGate $gate): JsonResponse
    {
        $result = $gate->run('orders.create', $request->header('Idempotency-Key'), $request->fingerprint(), static function () use ($request, $createOrder): array {
            return ['id' => $createOrder($request->toData())->public_id, 'status' => 201];
        });

        $order = Order::query()->with(['application', 'items', 'latestPayment'])->where('public_id', $result['id'])->firstOrFail();

        return ApiResponse::success(OrderResource::make($order)->resolve($request), $result['status'], ['idempotentReplay' => $result['replayed']]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $order->load(['application', 'items', 'latestPayment']);

        return ApiResponse::success(OrderResource::make($order)->resolve($request));
    }

    public function paymentStatus(Request $request, Order $order): JsonResponse
    {
        $order->load('latestPayment');

        return ApiResponse::success(PaymentStatusResource::make($order)->resolve($request));
    }
}
