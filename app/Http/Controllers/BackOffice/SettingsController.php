<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\Models\User;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Integrations\Duitku\DuitkuConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Queue\Failed\CountableFailedJobProvider;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController
{
    public function index(Request $request, PaymentGateway $gateway, DuitkuConfig $duitku, FailedJobProviderInterface $failedJobs): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/SettingsPage', [
            'admin' => $user->hasPermission(Permission::ManageSettings) ? [
                'users' => User::query()->orderBy('name')->get()->map(static fn (User $u): array => [
                    'id' => $u->public_id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role->value,
                    'isActive' => $u->is_active,
                    'lastLoginAt' => $u->last_login_at?->toIso8601ZuluString(),
                ])->all(),
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
            ] : null,
        ]);
    }

    public function updateProfile(Request $request, RecordAudit $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($validated);
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
