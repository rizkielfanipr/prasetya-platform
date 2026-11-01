<?php

declare(strict_types=1);

use App\Domain\Payments\Data\CheckoutRequest;
use App\Domain\Payments\Enums\GatewayStatus;
use App\Domain\Payments\Exceptions\GatewayException;
use App\Integrations\Doku\DokuClient;
use App\Integrations\Doku\DokuConfig;
use App\Integrations\Doku\DokuSignature;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function dokuClient(string $secret = 'unit-secret', string $client = 'MCH-TEST'): DokuClient
{
    return new DokuClient(new DokuConfig('sandbox', 'https://api-sandbox.doku.com', $client, $secret, '/api/v1/webhooks/doku', 0, 5, 2, 1));
}

function checkoutRequest(): CheckoutRequest
{
    return new CheckoutRequest('req-1', 'PW-202611-000001', new Money(499000), 60, 'https://site.test/status?order=x', 'Budi', 'budi@example.test', null, 'Landing Starter', 'PW-LANDING-STARTER');
}

// Shape taken from DOKU's documented sample response.
function documentedCheckoutResponse(): array
{
    return ['message' => ['SUCCESS'], 'response' => ['payment' => [
        'payment_due_date' => 60,
        'token_id' => '2ebffd22d23e436895ce5c38f7ddcf8620244712094712362',
        'url' => 'https://sandbox.doku.com/checkout-link-v2/2ebffd22d23e436895ce5c38f7ddcf8620244712094712362',
        'expired_date' => '20240712104711',
    ]]];
}

it('sends the documented request with a signature over the exact body', function (): void {
    Http::fake(['api-sandbox.doku.com/checkout/v1/payment' => Http::response(documentedCheckoutResponse())]);

    $session = dokuClient()->createCheckout(checkoutRequest());

    expect($session->reference)->toBe('2ebffd22d23e436895ce5c38f7ddcf8620244712094712362')
        ->and($session->expiresAt?->format(DATE_ATOM))->toBe('2024-07-12T10:47:11+07:00');

    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);
        $expected = DokuSignature::sign('unit-secret', 'MCH-TEST', 'req-1', $request->header('Request-Timestamp')[0], '/checkout/v1/payment', $request->body());

        return $request->url() === 'https://api-sandbox.doku.com/checkout/v1/payment'
            && $request->method() === 'POST'
            && $request->header('Client-Id')[0] === 'MCH-TEST'
            && $request->header('Request-Id')[0] === 'req-1'
            && preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $request->header('Request-Timestamp')[0]) === 1
            && $request->header('Signature')[0] === $expected
            && $body['order']['amount'] === 499000
            && $body['order']['invoice_number'] === 'PW-202611-000001'
            && $body['order']['auto_redirect'] === true
            && $body['payment']['payment_due_date'] === 60;
    });
});

it('rejects malformed responses and HTTP errors', function (array $response, int $status, string $code): void {
    Http::fake(['*' => Http::response($response, $status)]);

    try {
        dokuClient()->createCheckout(checkoutRequest());
        $this->fail('Expected a GatewayException');
    } catch (GatewayException $e) {
        expect($e->failureCode)->toBe($code);
    }
})->with([
    'missing url' => [['response' => ['payment' => ['token_id' => 'x']]], 200, 'DOKU_INVALID_RESPONSE'],
    'insecure url' => [['response' => ['payment' => ['token_id' => 'x', 'url' => 'http://x.test']]], 200, 'DOKU_INVALID_RESPONSE'],
    'validation error' => [['error_messages' => ['order.invoice_number is required']], 400, 'DOKU_HTTP_400'],
    'server error' => [[], 500, 'DOKU_HTTP_500'],
]);

it('maps connection failures without leaking detail', function (): void {
    $calls = 0;
    Http::fake(function () use (&$calls): never {
        $calls++;

        throw new ConnectionException('cURL error 28: timed out for https://api-sandbox.doku.com');
    });

    expect(fn () => dokuClient()->createCheckout(checkoutRequest()))
        ->toThrow(fn (GatewayException $e) => expect($e->getMessage())->not->toContain('api-sandbox') && expect($e->failureCode)->toBe('DOKU_UNREACHABLE'));

    expect($calls)->toBe(1); // A timeout may have reached DOKU, so it is not re-sent.
});

it('refuses to call DOKU without credentials', function (): void {
    Http::fake();

    expect(fn () => dokuClient('', '')->createCheckout(checkoutRequest()))
        ->toThrow(fn (GatewayException $e) => expect($e->failureCode)->toBe('DOKU_NOT_CONFIGURED'));

    Http::assertNothingSent();
});

it('checks status with a GET signature that has no digest', function (): void {
    Http::fake(['*' => Http::response(notificationBody('PW-1', 1000))]);

    $evidence = dokuClient()->checkStatus('PW-1');

    expect($evidence->status)->toBe(GatewayStatus::Success)->and($evidence->amount)->toBe(1000);

    Http::assertSent(function (Request $request): bool {
        $expected = DokuSignature::sign('unit-secret', 'MCH-TEST', $request->header('Request-Id')[0], $request->header('Request-Timestamp')[0], '/orders/v1/status/PW-1', null);

        return $request->method() === 'GET' && $request->url() === 'https://api-sandbox.doku.com/orders/v1/status/PW-1' && $request->header('Signature')[0] === $expected;
    });
});

it('treats 404 as no record yet', function (): void {
    Http::fake(['*' => Http::response([], 404)]);
    expect(dokuClient()->checkStatus('PW-1'))->toBeNull();
});

it('never exposes the secret through debug output', function (): void {
    $config = new DokuConfig('sandbox', 'u', 'c', 'super-secret-value', '/p', 0, 1, 1, 0);
    expect(print_r($config, true))->not->toContain('super-secret-value');
});
