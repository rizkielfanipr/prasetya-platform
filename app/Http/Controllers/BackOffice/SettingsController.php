<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Integrations\Duitku\DuitkuConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Queue\Failed\CountableFailedJobProvider;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Validation\Rules\Password;
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

    public function updateProfile(Request $request, RecordAudit $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        // Name only: the username never changes and the email is hidden (ADR-0009).
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $user->update(['name' => $validated['name']]);
        $audit('user.profile_updated', 'user', $user->public_id, $user);

        return back()->with('success', 'Profil diperbarui.');
    }

    public function updatePassword(Request $request, RecordAudit $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update(['password' => $validated['password']]);
        $request->session()->regenerate();
        $audit('user.password_changed', 'user', $user->public_id, $user);

        return back()->with('success', 'Kata sandi diperbarui.');
    }
}
