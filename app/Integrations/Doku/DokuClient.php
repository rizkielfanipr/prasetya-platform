<?php

declare(strict_types=1);

namespace App\Integrations\Doku;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Data\CheckoutRequest;
use App\Domain\Payments\Data\CheckoutSession;
use App\Domain\Payments\Data\GatewayEvidence;
use App\Integrations\Doku\Exceptions\DokuException;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class DokuClient implements PaymentGateway
{
    private const string CHECKOUT_PATH = '/checkout/v1/payment';

    private const string STATUS_PATH = '/orders/v1/status/';

    public function __construct(private readonly DokuConfig $config) {}

    public function name(): string
    {
        return 'DOKU';
    }

    public function isConfigured(): bool
    {
        return $this->config->isComplete();
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $this->assertConfigured();

        $body = json_encode($this->checkoutBody($request), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $response = $this->send('POST', self::CHECKOUT_PATH, $request->requestId, $body);

        if (! $response->successful()) {
            throw new DokuException("DOKU checkout returned HTTP {$response->status()}.", 'DOKU_HTTP_'.$response->status());
        }

        $url = $response->json('response.payment.url');
        $token = $response->json('response.payment.token_id');

        if (! is_string($url) || ! str_starts_with($url, 'https://') || ! is_string($token) || $token === '') {
            throw new DokuException('DOKU checkout response is missing payment.url or payment.token_id.', 'DOKU_INVALID_RESPONSE');
        }

        return new CheckoutSession($url, $token, $this->parseExpiry($response->json('response.payment.expired_date')));
    }

    public function checkStatus(string $gatewayInvoiceNumber): ?GatewayEvidence
    {
        $this->assertConfigured();

        $response = $this->send('GET', self::STATUS_PATH.rawurlencode($gatewayInvoiceNumber), (string) Str::uuid(), null);

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new DokuException("DOKU status check returned HTTP {$response->status()}.", 'DOKU_HTTP_'.$response->status());
        }

        $status = $response->json('transaction.status');

        if (! is_string($status)) {
            return null;
        }

        return DokuPayload::evidence($response->json() ?? []);
    }

    /** @return array<string, mixed> */
    private function checkoutBody(CheckoutRequest $request): array
    {
        return [
            'order' => [
                'amount' => $request->amount->amount,
                'invoice_number' => $request->invoiceNumber,
                'currency' => $request->amount->currency,
                'callback_url' => $request->callbackUrl,
                'auto_redirect' => true,
                'line_items' => [[
                    'id' => $request->itemCode,
                    'name' => $request->itemName,
                    'quantity' => 1,
                    'price' => $request->amount->amount,
                ]],
            ],
            'payment' => ['payment_due_date' => $request->dueInMinutes],
            'customer' => array_filter([
                'name' => $request->customerName,
                'email' => $request->customerEmail,
                'phone' => $request->customerPhone,
            ], static fn (?string $value): bool => $value !== null),
        ];
    }

    private function send(string $method, string $path, string $requestId, ?string $body): Response
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        try {
            $request = $this->http()->withHeaders([
                'Client-Id' => $this->config->clientId,
                'Request-Id' => $requestId,
                'Request-Timestamp' => $timestamp,
                'Signature' => DokuSignature::sign($this->config->secretKey, $this->config->clientId, $requestId, $timestamp, $path, $body),
            ]);

            return $body === null
                ? $request->get($this->config->baseUrl.$path)
                : $request->withBody($body, 'application/json')->post($this->config->baseUrl.$path);
        } catch (ConnectionException) {
            // The exception text can carry the URL and is deliberately not forwarded.
            throw new DokuException('DOKU could not be reached.', 'DOKU_UNREACHABLE');
        }
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout($this->config->timeoutSeconds)
            ->connectTimeout($this->config->connectTimeoutSeconds)
            // Connection failures only: a request that reached DOKU is never re-sent automatically.
            ->retry(
                $this->config->connectRetries + 1,
                200,
                static fn (Throwable $e): bool => $e instanceof ConnectionException && preg_match('/cURL error (6|7):/', $e->getMessage()) === 1,
                throw: false,
            );
    }

    private function parseExpiry(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value)) {
            return null;
        }

        // DOKU documents expired_date as yyyyMMddHHmmss in UTC+7.
        $parsed = DateTimeImmutable::createFromFormat('YmdHis', $value, new DateTimeZone('+07:00'));

        return $parsed === false ? null : $parsed;
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new DokuException('DOKU credentials are not configured.', 'DOKU_NOT_CONFIGURED');
        }
    }
}
