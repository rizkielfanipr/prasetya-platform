<?php

declare(strict_types=1);

namespace App\Integrations\Duitku;

use App\Domain\Orders\Enums\TransitionSource;
use App\Domain\Payments\Actions\ApplyPaymentEvidence;
use App\Domain\Payments\Actions\ProcessGatewayNotification;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Data\GatewayEvidence;
use App\Domain\Payments\Data\InboundNotification;
use App\Domain\Payments\Enums\GatewayStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\WebhookEvent;

/** Local only. Signs a callback like Duitku does and sends it through the real verification and evidence path. */
final class FakeNotificationSender
{
    public function __construct(
        private readonly DuitkuConfig $config,
        private readonly ProcessGatewayNotification $process,
        private readonly PaymentGateway $gateway,
        private readonly ApplyPaymentEvidence $applyEvidence,
    ) {}

    public function pay(Payment $payment, string $channel = 'SP', ?int $amount = null): WebhookEvent
    {
        return $this->send($payment->gateway_invoice_number, $amount ?? $payment->amount, '00', $channel, $payment->gateway_reference ?? 'FAKE');
    }

    /** Duitku sends no expiry callback, so a simulated expiry is evidence from a status check. */
    public function expire(Payment $payment): void
    {
        ($this->applyEvidence)($payment->id, new GatewayEvidence(GatewayStatus::Expired, 'EXPIRED', $payment->amount, null, null), TransitionSource::GatewayCheck);
    }

    public function send(string $invoice, int $amount, string $resultCode, string $channel, string $reference): WebhookEvent
    {
        $fields = [
            'merchantCode' => $this->config->merchantCode,
            'amount' => (string) $amount,
            'merchantOrderId' => $invoice,
            'paymentCode' => $channel,
            'resultCode' => $resultCode,
            'reference' => $reference,
        ];
        $fields['signature'] = DuitkuSignature::sign($this->config->apiKey, $fields['merchantCode'], $fields['amount'], $invoice);

        // Stands in for the status check a real success callback triggers.
        if ($this->gateway instanceof FakeGateway) {
            $this->gateway->statusResult = new GatewayEvidence(GatewayStatus::Success, '00', $amount, null, null);
        }

        $event = ($this->process)(new InboundNotification($reference, http_build_query($fields)));

        if ($this->gateway instanceof FakeGateway) {
            $this->gateway->statusResult = null;
        }

        return $event;
    }
}
