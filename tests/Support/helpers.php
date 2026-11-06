<?php

declare(strict_types=1);

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Models\User;
use App\Domain\Orders\Models\Order;
use App\Domain\Packages\Actions\SetPackageAvailability;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Data\GatewayEvidence;
use App\Domain\Payments\Enums\GatewayStatus;
use App\Domain\Payments\Models\Payment;
use App\Integrations\Duitku\DuitkuSignature;
use App\Integrations\Duitku\FakeGateway;
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

/** Satamu packages start inactive; tests switch them on the way an operator does. */
function activateSatamuPackages(): void
{
    foreach (['SAT-SEDERHANA', 'SAT-SPESIAL', 'SAT-ISTIMEWA'] as $code) {
        app(SetPackageAvailability::class)($code, true, 'Test');
    }
}

/** @return array<string, mixed> */
function satamuOrderPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'application_code' => 'SATAMU',
        'package_code' => 'SAT-SPESIAL',
        'customer' => ['name' => 'Sari Lestari', 'email' => 'sari@example.test', 'phone' => '628123456789'],
        'details' => ['theme_code' => 'T01', 'event_type' => 'wedding', 'event_date' => '2027-02-20', 'notes' => null],
    ], $overrides);
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

/** What a status check reports, or what a gateway check hands to the evidence path. */
function gatewayEvidence(?int $amount, GatewayStatus $status = GatewayStatus::Success): GatewayEvidence
{
    return new GatewayEvidence($status, $status->value, $amount, null, null);
}

/** @return array<string, string> */
function notificationBody(string $invoice, int $amount, string $status = 'SUCCESS', array $extra = []): array
{
    return [
        'merchantCode' => 'FAKE-MERCHANT',
        'amount' => (string) $amount,
        'merchantOrderId' => $invoice,
        'paymentCode' => 'SP',
        'resultCode' => $status === 'SUCCESS' ? '00' : '01',
        'reference' => 'REF-1',
        ...$extra,
    ];
}

/**
 * Sends a callback signed exactly as Duitku signs it. By default the status check agrees with its amount;
 * pass $checked for a different answer.
 */
function sendNotification(array $fields, ?string $secret = null, ?string $merchantCode = null, ?GatewayEvidence $checked = null): TestResponse
{
    $fields['merchantCode'] = $merchantCode ?? $fields['merchantCode'];
    $fields['signature'] ??= DuitkuSignature::sign($secret ?? 'fake-key-not-real', $fields['merchantCode'], $fields['amount'], $fields['merchantOrderId']);

    fakeGateway()->statusResult = $checked ?? gatewayEvidence((int) $fields['amount']);

    return test()->call('POST', '/api/v1/webhooks/duitku', server: [
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'HTTP_ACCEPT' => 'application/json',
    ], content: http_build_query($fields));
}
