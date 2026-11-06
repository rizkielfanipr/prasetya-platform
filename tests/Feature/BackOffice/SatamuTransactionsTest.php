<?php

declare(strict_types=1);

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Integrations\Duitku\DuitkuSignature;
use Inertia\Testing\AssertableInertia;

/** Creates Satamu and Webworks orders through the public API, as the frontends do. */
function seedBrandOrders(): void
{
    activateSatamuPackages();

    $orders = [
        satamuOrderPayload(),
        satamuOrderPayload(['package_code' => 'SAT-ISTIMEWA', 'details' => ['theme_code' => 'T02', 'event_type' => 'engagement', 'event_date' => '2027-03-01']]),
        satamuOrderPayload(['package_code' => 'SAT-SEDERHANA', 'details' => ['theme_code' => 'T02']]),
        orderPayload(),
    ];

    foreach ($orders as $index => $payload) {
        test()->postJson('/api/v1/orders', $payload, ['Idempotency-Key' => "seed-{$index}"])->assertCreated();
    }
}

function transactionsTotal(string $query): int
{
    $total = null;
    test()->get("/transactions?{$query}")->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$total): void {
            $total = $page->toArray()['props']['transactions']['meta']['total'];
        });

    return (int) $total;
}

it('filters transactions by Satamu package, theme, event type, and event date', function (string $query, int $expected): void {
    seedBrandOrders();
    $this->actingAs(admin());

    expect(transactionsTotal($query))->toBe($expected);
})->with([
    'brand' => ['brand=SATAMU', 3],
    'package' => ['package=SAT-ISTIMEWA', 1],
    'theme' => ['theme_code=T02', 2],
    'event type' => ['event_type=engagement', 1],
    'event date' => ['event_date=2027-02-20', 2],
    'combined' => ['theme_code=T02&event_date=2027-03-01&package=SAT-ISTIMEWA', 1],
    'theme on Webworks' => ['brand=WEBWORKS&theme_code=T01', 0],
    'Webworks package' => ['package=PW-LANDING-STARTER', 1],
]);

it('offers Satamu packages and stored detail values as filter options', function (): void {
    seedBrandOrders();

    $this->actingAs(admin())->get('/transactions')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('themeCodes', ['T01', 'T02'])
        ->where('eventTypes', ['engagement', 'wedding'])
        ->where('packages', fn ($packages): bool => collect($packages)->contains(fn (array $p): bool => $p['value'] === 'SAT-SPESIAL' && $p['brand'] === 'SATAMU' && $p['label'] === 'Spesial'))
        ->where('filters.package', null)
        ->where('filters.event_date', null));
});

it('validates Satamu filter input', function (): void {
    $this->actingAs(admin())->get('/transactions?event_date=20-02-2027&theme_code='.str_repeat('x', 65))
        ->assertSessionHasErrors(['event_date', 'theme_code']);
});

it('shows Satamu details, attempts, and sanitised evidence on a transaction', function (): void {
    activateSatamuPackages();
    $reference = $this->postJson('/api/v1/orders', satamuOrderPayload(['details' => ['notes' => 'Adat Sunda']]), ['Idempotency-Key' => 'detail'])->json('data.reference');
    $this->postJson("/api/v1/orders/{$reference}/checkout", [], ['Idempotency-Key' => 'detail-checkout'])->assertCreated();
    $payment = Payment::query()->sole();
    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount, 'SUCCESS', ['customerName' => 'Sari Lestari', 'maskedNumber' => '411111******1111']))->assertOk();

    $response = $this->actingAs(admin())->get("/transactions/{$reference}")->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('transaction.brand', 'SATAMU')
        ->where('transaction.invoice', Order::query()->sole()->invoice_number)
        ->where('transaction.items.0.packageCode', 'SAT-SPESIAL')
        ->where('transaction.brandDetails', [
            ['label' => 'Tema', 'value' => 'T01'],
            ['label' => 'Jenis acara', 'value' => 'wedding'],
            ['label' => 'Tanggal acara', 'value' => '2027-02-20'],
            ['label' => 'Catatan pelanggan', 'value' => 'Adat Sunda'],
        ])
        ->where('transaction.paymentStatus', 'PAID')
        ->where('transaction.orderStatus', 'CONFIRMED')
        ->where('transaction.payments.0.attempt', 1)
        ->has('webhooks', 1)
        ->where('webhooks.0.status', 'PROCESSED'));

    $webhooks = (string) json_encode($response->viewData('page')['props']['webhooks']);

    $signature = DuitkuSignature::sign('fake-key-not-real', 'FAKE-MERCHANT', (string) $payment->amount, $payment->gateway_invoice_number);

    expect($webhooks)->not->toContain('411111')->not->toContain('Sari Lestari')->not->toContain($signature)->not->toContain('fake-key-not-real');
});
