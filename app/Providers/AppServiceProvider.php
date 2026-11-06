<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\Models\User;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Contracts\NotificationVerifier;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\WebhookEvent;
use App\Integrations\Duitku\DuitkuClient;
use App\Integrations\Duitku\DuitkuConfig;
use App\Integrations\Duitku\DuitkuWebhookVerifier;
use App\Integrations\Duitku\FakeGateway;
use App\Policies\AuditLogPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\WebhookEventPolicy;
use App\Support\RequestContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Password::defaults(static fn () => Password::min(12)->letters()->numbers());
        Factory::guessFactoryNamesUsing(static fn (string $model): string => 'Database\\Factories\\'.class_basename($model).'Factory');

        ResetPassword::createUrlUsing(static fn (User $user, string $token): string => route('password.reset', ['token' => $token, 'email' => $user->email]));

        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(WebhookEvent::class, WebhookEventPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::define('view-reconciliation', static fn (User $user): bool => $user->hasPermission(Permission::ViewReconciliation));
        Gate::define('manage-settings', static fn (User $user): bool => $user->hasPermission(Permission::ManageSettings));

        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->ip()));
        RateLimiter::for('api-mutations', static fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->ip()));
        RateLimiter::for('webhooks', static fn (Request $request): Limit => Limit::perMinute(120)->by((string) $request->ip()));
        RateLimiter::for('password-reset', static fn (Request $request): Limit => Limit::perMinute(5)->by((string) $request->ip()));
    }
}
