<?php

declare(strict_types=1);

namespace App\Integrations\Duitku;

/** Typed view over config/duitku.php. The API key stays in this object; it is never serialised, logged, or shared. */
final readonly class DuitkuConfig
{
    /** @param list<string> $callbackIps */
    public function __construct(
        public string $environment,
        public string $apiUrl,
        public string $statusUrl,
        public string $merchantCode,
        #[\SensitiveParameter] public string $apiKey,
        public string $callbackUrl,
        public ?string $paymentMethod,
        public bool $enforceCallbackIp,
        public array $callbackIps,
        public int $timeoutSeconds,
        public int $connectTimeoutSeconds,
        public int $connectRetries,
    ) {}

    public static function fromConfig(): self
    {
        $environment = config('duitku.environment') === 'production' ? 'production' : 'sandbox';
        $callbackUrl = (string) config('duitku.callback_url');
        $paymentMethod = (string) config('duitku.payment_method');

        /** @var list<string> $callbackIps */
        $callbackIps = (array) config("duitku.callback_ips.{$environment}");

        return new self(
            environment: $environment,
            apiUrl: (string) config("duitku.api_urls.{$environment}"),
            statusUrl: (string) config("duitku.status_urls.{$environment}"),
            merchantCode: (string) config('duitku.merchant_code'),
            apiKey: (string) config('duitku.api_key'),
            callbackUrl: $callbackUrl !== '' ? $callbackUrl : route('api.webhooks.duitku'),
            paymentMethod: $paymentMethod !== '' ? $paymentMethod : null,
            enforceCallbackIp: (bool) config('duitku.enforce_callback_ip'),
            callbackIps: $callbackIps,
            timeoutSeconds: (int) config('duitku.timeout_seconds'),
            connectTimeoutSeconds: (int) config('duitku.connect_timeout_seconds'),
            connectRetries: (int) config('duitku.connect_retries'),
        );
    }

    /** Throwaway values for local signing, so simulated payments need no setup. Never used in production. */
    public static function forFakeGateway(): self
    {
        return new self('sandbox', '', '', 'FAKE-MERCHANT', 'fake-key-not-real', route('api.webhooks.duitku'), null, false, [], 1, 1, 0);
    }

    public function isComplete(): bool
    {
        return $this->merchantCode !== '' && $this->apiKey !== '';
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['environment' => $this->environment, 'apiUrl' => $this->apiUrl];
    }
}
