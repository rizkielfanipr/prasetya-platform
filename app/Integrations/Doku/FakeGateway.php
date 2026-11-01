<?php

declare(strict_types=1);

namespace App\Integrations\Doku;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Data\CheckoutRequest;
use App\Domain\Payments\Data\CheckoutSession;
use App\Domain\Payments\Data\GatewayEvidence;
use App\Domain\Payments\Exceptions\GatewayException;
use DateTimeImmutable;

/**
 * Deterministic gateway for local work and tests, no network. It reports as DOKU so that the
 * notification path is identical to production; AppServiceProvider refuses it in production.
 */
final class FakeGateway implements PaymentGateway
{
    /** @var list<CheckoutRequest> */
    public array $checkouts = [];

    public ?GatewayException $failNextWith = null;

    public ?GatewayEvidence $statusResult = null;

    public function name(): string
    {
        return 'DOKU';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        if ($this->failNextWith !== null) {
            $failure = $this->failNextWith;
            $this->failNextWith = null;

            throw $failure;
        }

        $this->checkouts[] = $request;

        return new CheckoutSession(
            url: 'https://fake-doku.invalid/checkout/'.$request->invoiceNumber,
            reference: 'FAKE-'.$request->requestId,
            expiresAt: new DateTimeImmutable("+{$request->dueInMinutes} minutes"),
        );
    }

    public function checkStatus(string $gatewayInvoiceNumber): ?GatewayEvidence
    {
        return $this->statusResult;
    }
}
