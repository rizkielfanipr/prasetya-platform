<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Auth;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class PasswordResetController
{
    public function requestForm(): Response
    {
        return Inertia::render('auth/ForgotPasswordPage', ['status' => session('status')]);
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        // Same answer whether or not the account exists.
        return back()->with('status', 'Jika email terdaftar, tautan atur ulang kata sandi telah dikirim.');
    }

    public function resetForm(Request $request, string $token): Response
    {
        return Inertia::render('auth/ResetPasswordPage', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(Request $request, RecordAudit $audit): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            static function (User $user, string $password) use ($audit): void {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $audit('auth.password_reset', 'user', $user->public_id, $user);
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Tautan atur ulang tidak valid atau sudah kedaluwarsa.']);
        }

        return redirect()->route('login')->with('status', 'Kata sandi berhasil diperbarui. Silakan masuk.');
    }
}
