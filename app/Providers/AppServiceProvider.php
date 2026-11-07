<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Content\Contracts\CopyGenerator;
use App\Domain\Payments\Contracts\NotificationVerifier;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Integrations\ContentAi\FakeCopyGenerator;
use App\Integrations\ContentAi\OpenAiCompatibleCopyGenerator;
use App\Integrations\ContentAi\UnconfiguredCopyGenerator;
use App\Integrations\Duitku\DuitkuClient;
use App\Integrations\Duitku\DuitkuConfig;
use App\Integrations\Duitku\DuitkuWebhookVerifier;
use App\Integrations\Duitku\FakeGateway;
use App\Support\RequestContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RequestContext::class);
        $this->app->singleton(DuitkuConfig::class, static fn ($app): DuitkuConfig => config('platform.payment_gateway') === 'duitku' || $app->isProduction()
            ? DuitkuConfig::fromConfig()
            : DuitkuConfig::forFakeGateway());
        $this->app->singleton(NotificationVerifier::class, DuitkuWebhookVerifier::class);
        $this->app->singleton(PaymentGateway::class, function ($app): PaymentGateway {
            if (config('platform.payment_gateway') === 'duitku') {
                return $app->make(DuitkuClient::class);
            }

            if ($app->isProduction()) {
                throw new RuntimeException('The fake payment gateway cannot run in production. Set PAYMENT_GATEWAY=duitku.');
            }

            return new FakeGateway;
        });
        $this->app->singleton(CopyGenerator::class, static fn ($app): CopyGenerator => match (config('content.ai.copy.provider')) {
            'openai_compatible' => new OpenAiCompatibleCopyGenerator,
            'fake' => new FakeCopyGenerator,
            default => new UnconfiguredCopyGenerator,
        });
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Password::defaults(static fn () => Password::min(12)->letters()->numbers());
        Factory::guessFactoryNamesUsing(static fn (string $model): string => 'Database\\Factories\\'.class_basename($model).'Factory');

        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->ip()));
        RateLimiter::for('api-mutations', static fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->ip()));
        RateLimiter::for('webhooks', static fn (Request $request): Limit => Limit::perMinute(120)->by((string) $request->ip()));
    }
}
