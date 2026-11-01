<?php

declare(strict_types=1);

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Models\User;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Models\Payment;
use App\Integrations\Doku\DokuSignature;
use App\Integrations\Doku\FakeGateway;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function fakeGateway(): FakeGateway
{
    /** @var FakeGateway */
    return app(PaymentGateway::class);
}

function admin(Role $role = Role::SuperAdmin): User
{
    return User::factory()->role($role)->create();
}

/** @return array<string, mixed> */
function orderPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'application_code' => 'WEBWORKS',
        'package_code' => 'PW-LANDING-STARTER',
        'customer' => ['name' => 'Budi Santoso', 'email' => 'budi@example.test', 'phone' => '081200000000'],
        'details' => ['template_code' => 'TPL-1', 'business_name' => 'Toko Budi'],
    ], $overrides);
}

function pendingPayment(array $orderAttributes = [], array $paymentAttributes = []): Payment
{
    $order = Order::factory()->create($orderAttributes);

    return Payment::factory()->create(['order_id' => $order->id, ...$paymentAttributes]);
}

/** @return array<string, mixed> */
function notificationBody(string $invoice, int $amount, string $status = 'SUCCESS', array $extra = []): array
{
    return array_replace_recursive([
        'service' => ['id' => 'VIRTUAL_ACCOUNT'],
        'acquirer' => ['id' => 'BCA'],
        'channel' => ['id' => 'VIRTUAL_ACCOUNT_BCA'],
        'transaction' => ['status' => $status, 'date' => '2026-11-01T03:24:23Z', 'original_request_id' => 'orig-1'],
        'order' => ['invoice_number' => $invoice, 'amount' => $amount],
    ], $extra);
}

/** Sends a notification signed exactly as DOKU signs it, over the raw JSON body. */
function sendNotification(array $body, ?string $secret = null, ?string $clientId = null, ?string $timestamp = null, ?string $requestId = null): TestResponse
{
    $raw = (string) json_encode($body);
    $clientId ??= 'TEST-CLIENT';
    $requestId ??= (string) Str::uuid();
    $timestamp ??= gmdate('Y-m-d\TH:i:s\Z');

    return test()->call('POST', '/api/v1/webhooks/doku', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_CLIENT_ID' => $clientId,
        'HTTP_REQUEST_ID' => $requestId,
        'HTTP_REQUEST_TIMESTAMP' => $timestamp,
        'HTTP_SIGNATURE' => DokuSignature::sign($secret ?? 'test-secret-key', $clientId, $requestId, $timestamp, '/api/v1/webhooks/doku', $raw),
    ], content: $raw);
}
