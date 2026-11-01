<?php

declare(strict_types=1);

namespace App\Integrations\Doku;

/** Typed view over config/doku.php. Secret stays in this object; it is never serialised, logged, or shared. */
final readonly class DokuConfig
{
    public function __construct(
        public string $environment,
        public string $baseUrl,
        public string $clientId,
        #[\SensitiveParameter] public string $secretKey,
        public string $notificationPath,
        public int $notificationMaxAgeSeconds,
        public int $timeoutSeconds,
        public int $connectTimeoutSeconds,
        public int $connectRetries,
    ) {}

    public static function fromConfig(): self
    {
        $environment = (string) config('doku.environment');

        return new self(
            environment: $environment,
            baseUrl: (string) config("doku.base_urls.{$environment}", config('doku.base_urls.sandbox')),
            clientId: (string) config('doku.client_id'),
            secretKey: (string) config('doku.secret_key'),
            notificationPath: (string) config('doku.notification_path'),
            notificationMaxAgeSeconds: (int) config('doku.notification_max_age_seconds'),
            timeoutSeconds: (int) config('doku.timeout_seconds'),
            connectTimeoutSeconds: (int) config('doku.connect_timeout_seconds'),
            connectRetries: (int) config('doku.connect_retries'),
        );
    }

    public function isComplete(): bool
    {
        return $this->clientId !== '' && $this->secretKey !== '';
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['environment' => $this->environment, 'baseUrl' => $this->baseUrl];
    }
}
