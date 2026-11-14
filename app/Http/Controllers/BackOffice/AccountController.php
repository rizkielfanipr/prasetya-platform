<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in person's own account (ADR-0015): only the password. Names change in Pengguna, never here.
 */
final class AccountController
{
    public function password(): Response
    {
        return Inertia::render('account/AccountPasswordPage');
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
