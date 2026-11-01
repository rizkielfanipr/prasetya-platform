<?php

declare(strict_types=1);

use App\Domain\Auth\Models\User;
use App\Domain\Orders\Actions\TransitionOrderStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\TransitionSource;
use App\Domain\Orders\Exceptions\InvalidStateTransition;
use App\Domain\Orders\Models\Order;
use App\Domain\Packages\Models\Package;
use App\Domain\Payments\Actions\ExpireStaleRecords;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Support\Idempotency\IdempotencyKey;
use App\Support\Logging\OperationLog;
use App\Support\Logging\RedactSensitiveData;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PackageSeeder;
use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;

it('expires overdue payments and orders, then still accepts a late payment', function (): void {
    $payment = pendingPayment(['expires_at' => now()->subDay()], ['expires_at' => now()->subHour()]);
    $paymentGrace = pendingPayment([], ['expires_at' => now()->subMinute()]);

    expect(app(ExpireStaleRecords::class)())->toBe(['payments' => 1, 'orders' => 1]);
    expect($payment->refresh()->status)->toBe(PaymentStatus::Expired)
        ->and($payment->order->status)->toBe(OrderStatus::Expired)
        ->and($paymentGrace->refresh()->status)->toBe(PaymentStatus::Pending);

    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)->and($payment->order->status)->toBe(OrderStatus::Confirmed);
});

it('does not expire an order with a live attempt', function (): void {
    $payment = pendingPayment(['expires_at' => now()->subMinute()], ['expires_at' => now()->addHour()]);

    app(ExpireStaleRecords::class)();

    expect($payment->order->refresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('refuses arbitrary status changes through the domain', function (): void {
    $order = Order::factory()->create(['status' => OrderStatus::Confirmed]);

    expect(fn () => app(TransitionOrderStatus::class)($order, OrderStatus::PendingPayment, TransitionSource::Admin))
        ->toThrow(InvalidStateTransition::class);
});

it('prunes old idempotency keys', function (): void {
    IdempotencyKey::query()->create(['scope' => 's', 'idempotency_key' => 'old', 'request_fingerprint' => 'f', 'status' => 'COMPLETED'])->forceFill(['created_at' => now()->subDays(10)])->save();
    IdempotencyKey::query()->create(['scope' => 's', 'idempotency_key' => 'new', 'request_fingerprint' => 'f', 'status' => 'COMPLETED']);

    $this->artisan('platform:prune-idempotency')->assertSuccessful();

    expect(IdempotencyKey::query()->pluck('idempotency_key')->all())->toBe(['new']);
});

it('refuses to boot the fake gateway in production', function (): void {
    app()->detectEnvironment(fn () => 'production');
    app()->forgetInstance(PaymentGateway::class);

    expect(fn () => app(PaymentGateway::class))->toThrow(RuntimeException::class);
});

it('creates no admin without configured credentials', function (): void {
    $this->seed(AdminUserSeeder::class);
    expect(User::query()->count())->toBe(0);
});

it('keeps Satamu dummy packages inactive in production', function (): void {
    app()->detectEnvironment(fn () => 'production');
    (new PackageSeeder)->run();

    expect(Package::query()->where('code', 'SAT-SPESIAL')->value('is_active'))->toBeFalse()
        ->and(Package::query()->where('code', 'PW-COMPANY-PROFILE')->value('is_active'))->toBeTrue();
});

it('writes operational logs without sensitive values', function (): void {
    $path = storage_path('logs/test-ops-'.uniqid().'.log');
    config(['logging.channels.operations' => ['driver' => 'single', 'path' => $path, 'level' => 'info', 'formatter' => JsonFormatter::class, 'tap' => [RedactSensitiveData::class]]]);
    Log::forgetChannel('operations');

    $payment = pendingPayment();
    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount, 'SUCCESS', ['customer' => ['email' => 'hidden@example.test']]))->assertOk();
    OperationLog::record('test.op', 'OK', ['signature' => 'HMACSHA256=abc', 'customer_email' => 'x@example.test']);

    $log = (string) file_get_contents($path);
    unlink($path);

    expect($log)->toContain('payments.notification')->toContain('duration_ms')->toContain('request_id')
        ->not->toContain('HMACSHA256=abc')->not->toContain('hidden@example.test')->not->toContain('x@example.test')->not->toContain('test-secret-key');
});
