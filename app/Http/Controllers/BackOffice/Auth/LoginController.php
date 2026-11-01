<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Auth;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Http\Requests\BackOffice\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class LoginController
{
    public function create(): Response
    {
        return Inertia::render('auth/LoginPage', ['status' => session('status')]);
    }

    public function store(LoginRequest $request, RecordAudit $audit): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        $audit('auth.login', 'user', $user->public_id, $user);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, RecordAudit $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $audit('auth.logout', 'user', $user->public_id, $user);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
