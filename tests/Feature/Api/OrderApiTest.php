<?php

declare(strict_types=1);

use App\Domain\Orders\Models\Order;
use App\Domain\Packages\Models\Package;
use App\Support\Api\ApiException;
use App\Support\Idempotency\IdempotencyGate;
use App\Support\Idempotency\IdempotencyKey;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

function postOrder(array $payload, ?string $key = 'key-1', array $headers = []): TestResponse
{
    return test()->postJson('/api/v1/orders', $payload, array_filter(['Idempotency-Key' => $key, ...$headers]));
}

it('creates a pending order priced by the catalogue', function (): void {
    $response = postOrder(orderPayload())->assertCreated()
        ->assertJsonPath('data.status', 'PENDING_PAYMENT')
        ->assertJsonPath('data.total.amount', 499000)
        ->assertJsonPath('data.application_code', 'WEBWORKS')
        ->assertJsonPath('data.payment_status', null);

    expect($response->json('data.invoice_number'))->toStartWith('PW-');

    $order = Order::query()->sole();
    expect($order->webworksDetail->business_name)->toBe('Toko Budi')
        ->and($order->statusHistories()->count())->toBe(1);
});

it('ignores tampered prices', function (): void {
    postOrder(orderPayload(['price' => 1, 'amount' => 1, 'total' => 1, 'total_amount' => 1, 'unit_amount' => 1]))
        ->assertCreated()->assertJsonPath('data.total.amount', 499000);

    expect(Order::query()->sole()->total_amount)->toBe(499000);
});

it('keeps the snapshot when the catalogue price later changes', function (): void {
    $reference = postOrder(orderPayload())->json('data.reference');
    Package::query()->where('code', 'PW-LANDING-STARTER')->update(['price_amount' => 999000, 'name' => 'Renamed']);

    $this->getJson("/api/v1/orders/{$reference}")
        ->assertJsonPath('data.total.amount', 499000)
        ->assertJsonPath('data.items.0.package_name', 'Landing Starter');
});

it('uses the SAT prefix and Satamu details for Satamu orders', function (): void {
    postOrder([
        'application_code' => 'SATAMU',
        'package_code' => 'SAT-SPESIAL',
        'customer' => ['name' => 'Sari', 'email' => 'sari@example.test'],
        'details' => ['theme_code' => 'T-1', 'event_type' => 'wedding', 'event_date' => '2027-01-02', 'business_name' => 'ignored'],
    ])->assertCreated()->assertJsonPath('data.total.amount', 79000);

    $order = Order::query()->sole();
    expect($order->invoice_number)->toStartWith('SAT-')
        ->and($order->invitationDetail->theme_code)->toBe('T-1')
        ->and($order->webworksDetail)->toBeNull();
});

it('rejects unknown, inactive, and wrong-brand packages', function (string $code, string $expected): void {
    Package::query()->where('code', 'PW-COMPANY-PROFILE')->update(['is_active' => false]);

    postOrder(orderPayload(['package_code' => $code]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', $expected)
        ->assertJsonStructure(['error' => ['fields' => ['package_code']]]);

    expect(Order::query()->count())->toBe(0);
})->with([
    'unknown' => ['NOPE', 'PACKAGE_NOT_FOUND'],
    'inactive' => ['PW-COMPANY-PROFILE', 'PACKAGE_INACTIVE'],
    'other brand' => ['SAT-SEDERHANA', 'PACKAGE_APPLICATION_MISMATCH'],
]);

it('validates input with field errors', function (): void {
    postOrder(orderPayload(['customer' => ['email' => 'not-an-email']]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['error' => ['fields' => ['customer.email']]]);
});

it('requires an idempotency key', function (): void {
    postOrder(orderPayload(), null)->assertStatus(400)->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');
});

it('replays a duplicate request without creating a second order', function (): void {
    $first = postOrder(orderPayload())->assertCreated();
    $second = postOrder(orderPayload())->assertCreated()->assertJsonPath('meta.idempotentReplay', true);

    expect($second->json('data.reference'))->toBe($first->json('data.reference'))
        ->and(Order::query()->count())->toBe(1);
});

it('refuses one key used for a different request', function (): void {
    postOrder(orderPayload())->assertCreated();

    postOrder(orderPayload(['package_code' => 'PW-COMPANY-PROFILE']))
        ->assertStatus(422)->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');

    expect(Order::query()->count())->toBe(1);
});

it('reports a concurrent identical request as in progress', function (): void {
    $fingerprint = hash('sha256', 'running');
    IdempotencyKey::query()->create(['scope' => 'test', 'idempotency_key' => 'k', 'request_fingerprint' => $fingerprint, 'status' => 'IN_PROGRESS', 'locked_until' => now()->addMinute()]);

    expect(fn () => app(IdempotencyGate::class)->run('test', 'k', $fingerprint, fn () => ['id' => 'x', 'status' => 201]))
        ->toThrow(ApiException::class);
});

it('lets a failed request retry with the same key', function (): void {
    postOrder(orderPayload(['package_code' => 'NOPE']))->assertStatus(422);
    postOrder(orderPayload())->assertCreated();
});

it('generates sequential unique invoices per brand and period', function (): void {
    $a = postOrder(orderPayload(), 'a')->json('data.invoice_number');
    $b = postOrder(orderPayload(), 'b')->json('data.invoice_number');

    expect((int) substr($b, -6))->toBe((int) substr($a, -6) + 1);
});

it('exposes no customer contact details or internal ids on public reads', function (): void {
    $reference = postOrder(orderPayload())->json('data.reference');

    $json = $this->getJson("/api/v1/orders/{$reference}")->assertOk()->getContent();

    expect($json)->not->toContain('budi@example.test')->not->toContain('081200000000')->not->toContain('"id"')->not->toContain('customer_id');
});

it('applies the CORS allowlist', function (): void {
    $this->withHeaders(['Origin' => 'https://prasetyawebworks.web.id', 'Access-Control-Request-Method' => 'POST'])
        ->options('/api/v1/orders')
        ->assertHeader('Access-Control-Allow-Origin', 'https://prasetyawebworks.web.id');

    $this->withHeaders(['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'POST'])
        ->options('/api/v1/orders')
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('refuses mutations from a disallowed origin', function (): void {
    postOrder(orderPayload(), 'k', ['Origin' => 'https://evil.example'])
        ->assertForbidden()->assertJsonPath('error.code', 'ORIGIN_NOT_ALLOWED');

    expect(Order::query()->count())->toBe(0);
});

it('rate limits mutations with the envelope', function (): void {
    RateLimiter::clear('api-mutations');

    foreach (range(1, 10) as $i) {
        postOrder(orderPayload(), "rate-{$i}");
    }

    postOrder(orderPayload(), 'rate-11')->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMITED');
});
