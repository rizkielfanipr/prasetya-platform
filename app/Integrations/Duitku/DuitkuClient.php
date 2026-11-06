<?php

declare(strict_types=1);

namespace App\Integrations\Duitku;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Data\CheckoutRequest;
use App\Domain\Payments\Data\CheckoutSession;
use App\Domain\Payments\Data\GatewayEvidence;
use App\Integrations\Duitku\Exceptions\DuitkuException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class DuitkuClient implements PaymentGateway
{
    private const string INVOICE_PATH = '/api/merchant/createInvoice';

    private const string STATUS_PATH = '/webapi/api/merchant/transactionStatus';

    public function __construct(private readonly DuitkuConfig $config) {}

    public function name(): string
    {
        return 'DUITKU';
    }

    public function isConfigured(): bool
    {
        return $this->config->isComplete();
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $this->assertConfigured();

        $timestamp = (string) (int) round(microtime(true) * 1000);

        $response = $this->post($this->config->apiUrl.self::INVOICE_PATH, $this->invoiceBody($request), [
            'x-duitku-merchantcode' => $this->config->merchantCode,
            'x-duitku-timestamp' => $timestamp,
            'x-duitku-signature' => DuitkuSignature::sign($this->config->apiKey, $this->config->merchantCode, $timestamp),
        ]);

        if (! $response->successful()) {
            throw new DuitkuException("Duitku create invoice returned HTTP {$response->status()}.", 'DUITKU_HTTP_'.$response->status());
        }

        $url = $response->json('paymentUrl');
        $reference = $response->json('reference');

        if ($response->json('statusCode') !== '00' || ! is_string($url) || ! str_starts_with($url, 'https://') || ! is_string($reference) || $reference === '') {
            throw new DuitkuException('Duitku create invoice response is missing paymentUrl or reference.', 'DUITKU_INVALID_RESPONSE');
        }

        // Duitku returns no expiry. StartCheckout falls back to the requested window.
        return new CheckoutSession($url, $reference, null);
    }

    public function checkStatus(string $gatewayInvoiceNumber): ?GatewayEvidence
    {
        $this->assertConfigured();

        $response = $this->post($this->config->statusUrl.self::STATUS_PATH, [
            'merchantCode' => $this->config->merchantCode,
            'merchantOrderId' => $gatewayInvoiceNumber,
            'signature' => DuitkuSignature::sign($this->config->apiKey, $this->config->merchantCode, $gatewayInvoiceNumber),
        ]);

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new DuitkuException("Duitku status check returned HTTP {$response->status()}.", 'DUITKU_HTTP_'.$response->status());
        }

        $status = $response->json('statusCode');

        if (! is_string($status) || $status === '') {
            return null;
        }

        return new GatewayEvidence(
            status: DuitkuStatusMapper::fromCheck($status),
            rawStatus: mb_substr($status, 0, 32),
            amount: DuitkuPayload::amount($response->json('amount')),
            channel: null,
            occurredAt: null,
        );
    }

    /** @return array<string, mixed> */
    private function invoiceBody(CheckoutRequest $request): array
    {
        $contact = array_filter([
            'email' => $request->customerEmail,
            'phoneNumber' => $request->customerPhone,
        ], static fn (?string $value): bool => $value !== null);

        return array_filter([
            'paymentAmount' => $request->amount->amount,
            'merchantOrderId' => $request->invoiceNumber,
            'productDetails' => Str::limit($request->itemName, 255, ''),
            'customerVaName' => Str::limit($request->customerName, 20, ''),
            ...$contact,
            'itemDetails' => [[
                'name' => Str::limit($request->itemName, 50, ''),
                'price' => $request->amount->amount,
                'quantity' => 1,
            ]],
            'customerDetail' => ['firstName' => Str::limit($request->customerName, 50, ''), ...$contact],
            'callbackUrl' => $this->config->callbackUrl,
            'returnUrl' => $request->returnUrl,
            'expiryPeriod' => $request->dueInMinutes,
            'paymentMethod' => $this->config->paymentMethod,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    private function post(string $url, array $body, array $headers = []): Response
    {
        try {
            return $this->http()->withHeaders($headers)->post($url, $body);
        } catch (ConnectionException) {
            // The exception text can carry the URL and is deliberately not forwarded.
            throw new DuitkuException('Duitku could not be reached.', 'DUITKU_UNREACHABLE');
        }
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout($this->config->timeoutSeconds)
            ->connectTimeout($this->config->connectTimeoutSeconds)
            // Connection failures only: a request that reached Duitku is never re-sent automatically.
            ->retry(
                $this->config->connectRetries + 1,
                200,
                static fn (Throwable $e): bool => $e instanceof ConnectionException && preg_match('/cURL error (6|7):/', $e->getMessage()) === 1,
                throw: false,
            );
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new DuitkuException('Duitku credentials are not configured.', 'DUITKU_NOT_CONFIGURED');
        }
    }
}
