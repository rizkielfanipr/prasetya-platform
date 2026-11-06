<?php

declare(strict_types=1);

use App\Domain\Payments\Data\CheckoutRequest;
use App\Domain\Payments\Enums\GatewayStatus;
use App\Domain\Payments\Exceptions\GatewayException;
use App\Integrations\Duitku\DuitkuClient;
use App\Integrations\Duitku\DuitkuConfig;
use App\Integrations\Duitku\DuitkuSignature;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function duitkuConfig(string $code = 'DTEST', string $key = 'unit-key', ?string $method = null): DuitkuConfig
{
    return new DuitkuConfig('sandbox', 'https://api-sandbox.duitku.com', 'https://sandbox.duitku.com', $code, $key, 'https://platform.test/api/v1/webhooks/duitku', $method, true, ['182.23.85.11'], 5, 2, 1);
}

function duitkuCheckoutRequest(string $name = 'Budi Santoso', ?string $phone = '081200000000'): CheckoutRequest
{
    return new CheckoutRequest('req-1', 'PW-202611-000001', new Money(499000), 60, 'https://site.test/order/status/?order=abc', $name, 'budi@example.test', $phone, 'Landing Starter', 'PW-LANDING-STARTER');
}

// Shape taken from Duitku's documented sample response.
function documentedInvoiceResponse(): array
{
    return [
        'merchantCode' => 'DTEST',
        'reference' => 'DTESTS875LXXXX32IJZ7',
        'paymentUrl' => 'https://app-sandbox.duitku.com/redirect_checkout?reference=DTESTS875LXXXX32IJZ7',
        'statusCode' => '00',
        'statusMessage' => 'SUCCESS',
    ];
}

it('creates an invoice with the documented headers and body', function (): void {
    Http::fake(['api-sandbox.duitku.com/api/merchant/createInvoice' => Http::response(documentedInvoiceResponse())]);

    $session = (new DuitkuClient(duitkuConfig()))->createCheckout(duitkuCheckoutRequest());

    expect($session->reference)->toBe('DTESTS875LXXXX32IJZ7')
        ->and($session->url)->toStartWith('https://app-sandbox.duitku.com/')
        ->and($session->expiresAt)->toBeNull();

    Http::assertSent(function (Request $request): bool {
        $timestamp = $request->header('x-duitku-timestamp')[0];

        return $request->method() === 'POST'
            && $request->header('x-duitku-merchantcode')[0] === 'DTEST'
            && preg_match('/^\d{13}$/', $timestamp) === 1
            && $request->header('x-duitku-signature')[0] === hash_hmac('sha256', 'DTEST'.$timestamp, 'unit-key')
            && $request['paymentAmount'] === 499000
            && $request['merchantOrderId'] === 'PW-202611-000001'
            && $request['email'] === 'budi@example.test'
            && $request['phoneNumber'] === '081200000000'
            && $request['itemDetails'] === [['name' => 'Landing Starter', 'price' => 499000, 'quantity' => 1]]
            && $request['callbackUrl'] === 'https://platform.test/api/v1/webhooks/duitku'
            && $request['returnUrl'] === 'https://site.test/order/status/?order=abc'
            && $request['expiryPeriod'] === 60
            && ! isset($request['paymentMethod']);
    });
});

it('limits checkout to one method only when configured', function (): void {
    Http::fake(['*' => Http::response(documentedInvoiceResponse())]);

    (new DuitkuClient(duitkuConfig(method: 'SP')))->createCheckout(duitkuCheckoutRequest());

    Http::assertSent(fn (Request $request): bool => $request['paymentMethod'] === 'SP');
});

it('respects the documented field limits and omits a missing phone', function (): void {
    Http::fake(['*' => Http::response(documentedInvoiceResponse())]);

    (new DuitkuClient(duitkuConfig()))->createCheckout(duitkuCheckoutRequest(str_repeat('Nama Panjang ', 10), null));

    Http::assertSent(fn (Request $request): bool => mb_strlen($request['customerVaName']) <= 20 && ! isset($request['phoneNumber']));
});

it('rejects malformed responses and HTTP errors', function (array $response, int $status, string $code): void {
    Http::fake(['*' => Http::response($response, $status)]);

    try {
        (new DuitkuClient(duitkuConfig()))->createCheckout(duitkuCheckoutRequest());
        $this->fail('Expected a GatewayException');
    } catch (GatewayException $e) {
        expect($e->failureCode)->toBe($code);
    }
})->with([
    'missing url' => [['statusCode' => '00', 'reference' => 'x'], 200, 'DUITKU_INVALID_RESPONSE'],
    'insecure url' => [['statusCode' => '00', 'reference' => 'x', 'paymentUrl' => 'http://x.test'], 200, 'DUITKU_INVALID_RESPONSE'],
    'not successful' => [['statusCode' => '01', 'reference' => 'x', 'paymentUrl' => 'https://x.test'], 200, 'DUITKU_INVALID_RESPONSE'],
    'amount differs' => [['Message' => 'Amount is different please try again later.'], 400, 'DUITKU_HTTP_400'],
    'in progress' => [['Message' => 'The transaction is still in progress.'], 409, 'DUITKU_HTTP_409'],
    'server error' => [[], 500, 'DUITKU_HTTP_500'],
]);

it('maps connection failures without leaking detail and does not re-send a timeout', function (): void {
    $calls = 0;
    Http::fake(function () use (&$calls): never {
        $calls++;

        throw new ConnectionException('cURL error 28: timed out for https://api-sandbox.duitku.com');
    });

    expect(fn () => (new DuitkuClient(duitkuConfig()))->createCheckout(duitkuCheckoutRequest()))
        ->toThrow(fn (GatewayException $e) => expect($e->getMessage())->not->toContain('api-sandbox') && expect($e->failureCode)->toBe('DUITKU_UNREACHABLE'));

    expect($calls)->toBe(1);
});

it('refuses to call Duitku without credentials', function (): void {
    Http::fake();

    expect(fn () => (new DuitkuClient(duitkuConfig('', '')))->checkStatus('PW-1'))
        ->toThrow(fn (GatewayException $e) => expect($e->failureCode)->toBe('DUITKU_NOT_CONFIGURED'));

    Http::assertNothingSent();
});

it('checks a transaction with the documented signature', function (): void {
    Http::fake(['sandbox.duitku.com/webapi/api/merchant/transactionStatus' => Http::response([
        'merchantOrderId' => 'PW-1', 'reference' => 'R', 'amount' => '100000', 'fee' => '0.00', 'statusCode' => '00', 'statusMessage' => 'SUCCESS',
    ])]);

    $evidence = (new DuitkuClient(duitkuConfig()))->checkStatus('PW-1');

    expect($evidence->status)->toBe(GatewayStatus::Success)
        ->and($evidence->rawStatus)->toBe('00')
        ->and($evidence->amount)->toBe(100000);

    Http::assertSent(fn (Request $request): bool => $request['merchantCode'] === 'DTEST'
        && $request['merchantOrderId'] === 'PW-1'
        && $request['signature'] === DuitkuSignature::sign('unit-key', 'DTEST', 'PW-1'));
});

it('maps check status codes', function (string $code, GatewayStatus $expected): void {
    Http::fake(['*' => Http::response(['statusCode' => $code, 'amount' => '1000'])]);

    expect((new DuitkuClient(duitkuConfig()))->checkStatus('PW-1')->status)->toBe($expected);
})->with([
    'success' => ['00', GatewayStatus::Success],
    'pending' => ['01', GatewayStatus::Pending],
    'canceled is ignored' => ['02', GatewayStatus::Failed],
    'unknown' => ['99', GatewayStatus::Unsupported],
]);

it('treats 404 and an empty answer as no record', function (array $body, int $status): void {
    Http::fake(['*' => Http::response($body, $status)]);

    expect((new DuitkuClient(duitkuConfig()))->checkStatus('PW-1'))->toBeNull();
})->with([
    '404' => [[], 404],
    'no status code' => [['Message' => 'x'], 200],
]);

it('never exposes the API key through debug output', function (): void {
    expect(print_r(duitkuConfig(key: 'super-secret-value'), true))->not->toContain('super-secret-value');
});
