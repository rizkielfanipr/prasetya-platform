<?php

declare(strict_types=1);

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\WebhookEvent;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

it('shows a clearly marked page, not a real checkout', function (): void {
    $payment = pendingPayment();

    $this->get("/dev/fake-checkout/{$payment->gateway_invoice_number}")->assertOk()->assertSee('SIMULASI');
});

it('pays through the real signed evidence path, then returns to the site', function (): void {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $payment = pendingPayment();
    config(['platform.return_urls.WEBWORKS' => 'http://localhost:4321/order/status/']);

    $this->post("/dev/fake-checkout/{$payment->gateway_invoice_number}", ['status' => 'SUCCESS'])
        ->assertRedirect("http://localhost:4321/order/status/?order={$payment->order->public_id}");

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->order->status)->toBe(OrderStatus::Confirmed)
        ->and(WebhookEvent::query()->sole()->signature_valid)->toBeTrue();
});

it('accepts only the simulated outcomes', function (): void {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $payment = pendingPayment();

    $this->post("/dev/fake-checkout/{$payment->gateway_invoice_number}", ['status' => 'PAID'])->assertSessionHasErrors('status');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('registers the route only with the fake gateway outside production', function (): void {
    $source = (string) file_get_contents(base_path('routes/web.php'));

    expect($source)->toContain("config('platform.payment_gateway') === 'fake' && ! app()->isProduction()");
});
