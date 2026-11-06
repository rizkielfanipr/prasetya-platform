<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\DuitkuWebhookController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PackageController;
use App\Http\Middleware\EnforceApiOrigin;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', HealthController::class)->middleware('throttle:api')->name('api.health');

    Route::middleware('throttle:api')->group(function (): void {
        Route::get('packages', PackageController::class)->name('api.packages');
        Route::get('orders/{order:public_id}', [OrderController::class, 'show'])->name('api.orders.show');
        Route::get('orders/{order:public_id}/payment-status', [OrderController::class, 'paymentStatus'])->name('api.orders.payment-status');
    });

    Route::middleware([EnforceApiOrigin::class, 'throttle:api-mutations'])->group(function (): void {
        Route::post('orders', [OrderController::class, 'store'])->name('api.orders.store');
        Route::post('orders/{order:public_id}/checkout', CheckoutController::class)->name('api.orders.checkout');
    });

    Route::post('webhooks/duitku', DuitkuWebhookController::class)->middleware('throttle:webhooks')->name('api.webhooks.duitku');
});
