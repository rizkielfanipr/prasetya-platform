<?php

declare(strict_types=1);

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderStatusHistory;
use App\Domain\Packages\Actions\SetPackageAvailability;
use App\Domain\Payments\Enums\PaymentEventType;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\WebhookStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use App\Domain\Payments\Models\WebhookEvent;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\ResponseSequence;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/*
 * Satamu end to end on the shared core: order API, Duitku checkout, callback, transaction check, and status,
 * through the real Duitku adapter with HTTP faked. Webworks behaviour is covered by the generic tests.
 */

const SATAMU_ORIGIN = 'https://satamu.prasetyawebworks.web.id';
const INVOICE_URL = 'api-sandbox.duitku.com/api/merchant/createInvoice';
const SATAMU_PAY_URL = 'https://app-sandbox.duitku.com/redirect_checkout?reference=DSATREF1';

beforeEach(function (): void {
    useDuitkuSandbox();
    activateSatamuPackages();
});

function satamuOrder(array $overrides = [], string $key = 'sat-order-1'): TestResponse
{
    return test()->postJson('/api/v1/orders', satamuOrderPayload($overrides), ['Idempotency-Key' => $key, 'Origin' => SATAMU_ORIGIN]);
}

function satamuCheckout(string $reference, string $key = 'sat-checkout-1'): TestResponse
{
    return test()->postJson("/api/v1/orders/{$reference}/checkout", [], ['Idempotency-Key' => $key, 'Origin' => SATAMU_ORIGIN]);
}

function satamuStatus(string $reference, string $query = ''): TestResponse
{
    return test()->getJson("/api/v1/orders/{$reference}/payment-status{$query}", ['Origin' => SATAMU_ORIGIN]);
}

/** Fakes Duitku: a working createInvoice unless told otherwise, and the given transaction check answer(s). */
function fakeDuitkuForSatamu(PromiseInterface|ResponseSequence|null $check = null, PromiseInterface|ResponseSequence|null $invoice = null): void
{
    Http::fake([
        INVOICE_URL => $invoice ?? Http::response(['statusCode' => '00', 'reference' => 'DSATREF1', 'paymentUrl' => SATAMU_PAY_URL]),
        STATUS_URL => $check ?? Http::response(['statusCode' => '00', 'amount' => '79000']),
    ]);
}

/** A Satamu order with an open Duitku attempt, ready for callbacks. */
function openSatamuAttempt(): Payment
{
    $reference = satamuOrder()->assertCreated()->json('data.reference');
    satamuCheckout($reference)->assertCreated();

    return Payment::query()->sole();
}

it('creates a Satamu order priced by the catalogue, whatever the browser sends', function (string $code, int $price): void {
    $response = satamuOrder([
        'package_code' => $code,
        'price' => 1, 'amount' => 1, 'total' => 1, 'total_amount' => 1, 'currency' => 'USD', 'status' => 'CONFIRMED', 'payment_status' => 'PAID',
    ])->assertCreated()
        ->assertJsonPath('data.application_code', 'SATAMU')
        ->assertJsonPath('data.status', 'PENDING_PAYMENT')
        ->assertJsonPath('data.payment_status', null)
        ->assertJsonPath('data.total.amount', $price)
        ->assertJsonPath('data.total.currency', 'IDR');

    $order = Order::query()->with('items')->sole();

    expect($response->json('data.invoice_number'))->toMatch('/^SAT-\d{6}-\d{6}$/')
        ->and($order->total_amount)->toBe($price)
        ->and($order->items->sole()->unit_amount)->toBe($price)
        ->and($order->items->sole()->package_code)->toBe($code);
})->with([
    ['SAT-SEDERHANA', 49000],
    ['SAT-SPESIAL', 79000],
    ['SAT-ISTIMEWA', 149000],
]);

it('refuses packages a Satamu order cannot buy', function (string $code, string $error): void {
    app(SetPackageAvailability::class)('SAT-ISTIMEWA', false, 'Test');

    satamuOrder(['package_code' => $code])->assertStatus(422)->assertJsonPath('error.code', $error);

    expect(Order::query()->count())->toBe(0);
})->with([
    'Webworks package' => ['PW-LANDING-STARTER', 'PACKAGE_APPLICATION_MISMATCH'],
    'inactive' => ['SAT-ISTIMEWA', 'PACKAGE_INACTIVE'],
    'unknown' => ['SAT-PREMIUM', 'PACKAGE_NOT_FOUND'],
]);

it('refuses a Satamu package on a Webworks order even when it is active', function (): void {
    $this->postJson('/api/v1/orders', orderPayload(['package_code' => 'SAT-SPESIAL']), ['Idempotency-Key' => 'pw-1'])
        ->assertStatus(422)->assertJsonPath('error.code', 'PACKAGE_APPLICATION_MISMATCH');

    expect(Order::query()->count())->toBe(0);
});

it('stores only the Satamu detail allow-list', function (): void {
    satamuOrder(['details' => ['business_name' => 'Toko', 'template_code' => 'TPL-1', 'domain_preference' => 'x.id', 'guest_count' => 300, 'notes' => 'Adat Jawa']])
        ->assertCreated();

    $order = Order::query()->sole();
    $detail = $order->invitationDetail;

    expect($order->webworksDetail)->toBeNull()
        ->and($detail->only(['theme_code', 'event_type', 'notes']))->toBe(['theme_code' => 'T01', 'event_type' => 'wedding', 'notes' => 'Adat Jawa'])
        ->and($detail->event_date?->format('Y-m-d'))->toBe('2027-02-20')
        ->and(array_keys($detail->getAttributes()))->not->toContain('business_name')->not->toContain('guest_count');
});

it('validates Satamu details', function (array $details, string $field): void {
    satamuOrder(['details' => $details])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['error' => ['fields' => [$field]]]);
})->with([
    'date not Y-m-d' => [['event_date' => '20-02-2027'], 'details.event_date'],
    'impossible date' => [['event_date' => '2027-02-30'], 'details.event_date'],
    'theme too long' => [['theme_code' => str_repeat('T', 65)], 'details.theme_code'],
    'notes too long' => [['notes' => str_repeat('a', 2001)], 'details.notes'],
]);

it('replays a Satamu order instead of creating a second one', function (): void {
    $first = satamuOrder()->assertCreated()->json('data.reference');

    satamuOrder()->assertJsonPath('meta.idempotentReplay', true)->assertJsonPath('data.reference', $first);
    satamuOrder(['package_code' => 'SAT-ISTIMEWA'])->assertStatus(422)->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');

    expect(Order::query()->count())->toBe(1);
});

it('opens one Duitku checkout for repeat clicks and returns to the Satamu status page', function (): void {
    fakeDuitkuForSatamu();
    $reference = satamuOrder()->json('data.reference');

    $first = satamuCheckout($reference, 'a')->assertCreated()->assertJsonPath('data.checkout_url', SATAMU_PAY_URL)->json('data.payment_reference');
    satamuCheckout($reference, 'a')->assertJsonPath('meta.idempotentReplay', true)->assertJsonPath('data.payment_reference', $first);
    satamuCheckout($reference, 'b')->assertJsonPath('data.payment_reference', $first);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['returnUrl'] === config('platform.return_urls.SATAMU')."?order={$reference}"
        && str_starts_with((string) $request['merchantOrderId'], 'SAT-')
        && $request['paymentAmount'] === 79000
        && $request['itemDetails'] === [['name' => 'Spesial', 'price' => 79000, 'quantity' => 1]]);
});

it('uses the configured Satamu return URL, not the Webworks one', function (): void {
    config(['platform.return_urls.SATAMU' => 'https://staging.satamu.test/order/status/']);
    fakeDuitkuForSatamu();

    $reference = satamuOrder()->json('data.reference');
    satamuCheckout($reference)->assertCreated();

    Http::assertSent(fn (Request $request): bool => $request['returnUrl'] === "https://staging.satamu.test/order/status/?order={$reference}");
});

it('reports a checkout that is still being created', function (): void {
    $reference = satamuOrder()->json('data.reference');
    Payment::factory()->create(['order_id' => Order::query()->sole()->id, 'status' => PaymentStatus::Created, 'checkout_url' => null]);

    satamuCheckout($reference)->assertStatus(409)->assertJsonPath('error.code', 'CHECKOUT_IN_PROGRESS');
});

it('retries a failed checkout with a new key as a new attempt', function (): void {
    fakeDuitkuForSatamu(invoice: Http::sequence()
        ->push(['statusCode' => '99'], 500)
        ->push(['statusCode' => '00', 'reference' => 'DSATREF2', 'paymentUrl' => SATAMU_PAY_URL]));
    $reference = satamuOrder()->json('data.reference');
    $invoice = Order::query()->sole()->invoice_number;

    satamuCheckout($reference, 'first')->assertStatus(502)->assertJsonPath('error.code', 'GATEWAY_UNAVAILABLE');
    satamuStatus($reference)->assertJsonPath('data.payment_status', 'FAILED')->assertJsonPath('data.order_status', 'PENDING_PAYMENT')->assertJsonPath('data.checkout_url', null);

    satamuCheckout($reference, 'retry')->assertCreated()->assertJsonPath('data.checkout_url', SATAMU_PAY_URL);

    expect(Payment::query()->orderBy('attempt_number')->pluck('gateway_invoice_number')->all())->toBe([$invoice, "{$invoice}-2"]);
});

it('confirms a paid Satamu order exactly once', function (): void {
    fakeDuitkuForSatamu();
    $payment = openSatamuAttempt();
    $reference = $payment->order->public_id;

    sendDuitkuCallback(duitkuCallback($payment))->assertOk();
    sendDuitkuCallback(duitkuCallback($payment))->assertOk();

    $order = $payment->order->refresh();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($order->status)->toBe(OrderStatus::Confirmed)
        ->and(WebhookEvent::query()->orderBy('id')->pluck('status')->all())->toBe([WebhookStatus::Processed, WebhookStatus::Duplicate])
        ->and(PaymentEvent::query()->where('type', PaymentEventType::StatusChanged)->where('to_status', PaymentStatus::Paid)->count())->toBe(1)
        ->and(OrderStatusHistory::query()->where('order_id', $order->id)->where('to_status', OrderStatus::Confirmed)->count())->toBe(1);

    // One check for the new success callback; the duplicate makes no call.
    Http::assertSentCount(2);

    satamuStatus($reference)->assertOk()
        ->assertJsonPath('data.payment_status', 'PAID')
        ->assertJsonPath('data.order_status', 'CONFIRMED')
        ->assertJsonPath('data.checkout_url', null);
    satamuCheckout($reference, 'after-paid')->assertStatus(409)->assertJsonPath('error.code', 'ORDER_ALREADY_PAID');
});

it('never pays a Satamu order without gateway confirmation', function (Closure $callback, ?Closure $check, int $status, WebhookStatus $stored): void {
    fakeDuitkuForSatamu(check: $check?->__invoke());
    $payment = openSatamuAttempt();

    sendDuitkuCallback($callback($payment))->assertStatus($status);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->order->refresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and(WebhookEvent::query()->sole()->status)->toBe($stored);
    satamuStatus($payment->order->public_id)->assertJsonPath('data.payment_status', 'PENDING');
})->with([
    'forged signature' => [fn (Payment $p) => duitkuCallback($p, key: 'stolen-key'), null, 401, WebhookStatus::Rejected],
    'unknown invoice' => [fn (Payment $p) => duitkuCallback($p, ['merchantOrderId' => 'SAT-209901-999999']), null, 200, WebhookStatus::Rejected],
    'amount mismatch' => [fn (Payment $p) => duitkuCallback($p), fn () => Http::response(['statusCode' => '00', 'amount' => '49000']), 200, WebhookStatus::Rejected],
    'signed success, check pending' => [fn (Payment $p) => duitkuCallback($p), fn () => Http::response(['statusCode' => '01', 'amount' => '79000']), 500, WebhookStatus::Failed],
    'gateway outage on check' => [fn (Payment $p) => duitkuCallback($p), fn () => Http::response([], 503), 500, WebhookStatus::Failed],
    'failure result' => [fn (Payment $p) => duitkuCallback($p, ['resultCode' => '01']), null, 200, WebhookStatus::Ignored],
]);

it('recovers a delayed confirmation when Duitku retries', function (): void {
    fakeDuitkuForSatamu(check: Http::sequence()
        ->push(['statusCode' => '01', 'amount' => '79000'])
        ->push(['statusCode' => '00', 'amount' => '79000']));
    $payment = openSatamuAttempt();

    sendDuitkuCallback(duitkuCallback($payment))->assertStatus(500);
    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);

    sendDuitkuCallback(duitkuCallback($payment))->assertOk();
    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->order->refresh()->status)->toBe(OrderStatus::Confirmed);
});

it('treats the browser return as no evidence', function (): void {
    fakeDuitkuForSatamu();
    $payment = openSatamuAttempt();
    $invoice = $payment->gateway_invoice_number;

    satamuStatus($payment->order->public_id, "?resultCode=00&merchantOrderId={$invoice}&reference=DSATREF1")
        ->assertOk()->assertJsonPath('data.payment_status', 'PENDING')->assertJsonPath('data.checkout_url', SATAMU_PAY_URL);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and(WebhookEvent::query()->count())->toBe(0);
});

describe('CORS', function (): void {
    function preflight(string $origin): TestResponse
    {
        return test()->withHeaders(['Origin' => $origin, 'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'content-type,idempotency-key'])
            ->options('/api/v1/orders');
    }

    it('allows the approved Satamu origin', function (): void {
        preflight(SATAMU_ORIGIN)->assertHeader('Access-Control-Allow-Origin', SATAMU_ORIGIN);
        satamuOrder()->assertCreated()->assertHeader('Access-Control-Allow-Origin', SATAMU_ORIGIN);
    });

    it('rejects look-alike and unlisted origins', function (string $origin): void {
        preflight($origin)->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->postJson('/api/v1/orders', satamuOrderPayload(), ['Idempotency-Key' => 'x', 'Origin' => $origin])
            ->assertForbidden()->assertJsonPath('error.code', 'ORIGIN_NOT_ALLOWED');

        expect(Order::query()->count())->toBe(0);
    })->with(['https://satamu.prasetyawebworks.web.id.evil.example', 'http://satamu.prasetyawebworks.web.id', 'https://evil.example', 'http://localhost:4322']);

    it('allows a development origin only when it is configured explicitly', function (): void {
        config(['cors.allowed_origins' => [...config('cors.allowed_origins'), 'http://localhost:4322']]);

        preflight('http://localhost:4322')->assertHeader('Access-Control-Allow-Origin', 'http://localhost:4322');
        $this->postJson('/api/v1/orders', satamuOrderPayload(), ['Idempotency-Key' => 'dev', 'Origin' => 'http://localhost:4322'])->assertCreated();
    });
});
