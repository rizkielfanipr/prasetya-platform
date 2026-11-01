<?php

declare(strict_types=1);

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\Data\CheckoutRequest;
use App\Domain\Payments\Data\CheckoutSession;
use App\Domain\Payments\Data\GatewayEvidence;
use App\Domain\Payments\Exceptions\GatewayException;

interface PaymentGateway
{
    public function name(): string;

    /** @throws GatewayException */
    public function createCheckout(CheckoutRequest $request): CheckoutSession;

    /**
     * Verified server-to-server status check; null when the gateway has no record yet.
     *
     * @throws GatewayException
     */
    public function checkStatus(string $gatewayInvoiceNumber): ?GatewayEvidence;

    /** Whether real credentials are configured. Never exposes them. */
    public function isConfigured(): bool;
}
