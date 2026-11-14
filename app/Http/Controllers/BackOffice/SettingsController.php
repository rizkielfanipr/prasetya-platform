<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Integrations\Duitku\DuitkuConfig;
use Illuminate\Queue\Failed\CountableFailedJobProvider;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController
{
    public function index(PaymentGateway $gateway, DuitkuConfig $duitku, FailedJobProviderInterface $failedJobs): Response
    {
        return Inertia::render('settings/SettingsPage', [
            'admin' => [
                // Whether and where, never what: no credential value is read into this array.
                'gateway' => [
                    'driver' => (string) config('platform.payment_gateway'),
                    'environment' => $duitku->environment,
                    'configured' => $gateway->isConfigured(),
                    'notificationPath' => (string) parse_url(route('api.webhooks.duitku'), PHP_URL_PATH),
                ],
                'application' => [
                    'environment' => (string) config('app.env'),
                    'timezone' => (string) config('platform.business_timezone'),
                    'allowedOrigins' => (array) config('cors.allowed_origins'),
                    'pendingThresholdMinutes' => (int) config('platform.reconciliation.pending_too_long_minutes'),
                    'failedJobs' => $failedJobs instanceof CountableFailedJobProvider ? $failedJobs->count() : 0,
                ],
            ],
        ]);
    }
}
