<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia;

const TEST_PASSWORD = 'password-for-tests-1';

it('renders the login page', function (): void {
    $this->get('/login')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('auth/LoginPage'));
});

it('logs in with a username, regenerates the session, and audits', function (): void {
    $user = User::factory()->create(['username' => 'sari']);
    $this->startSession();
    $before = session()->getId();

    $this->post('/login', ['username' => 'sari', 'password' => TEST_PASSWORD])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($before)
        ->and(AuditLog::query()->where('action', 'auth.login')->where('actor_user_id', $user->id)->exists())->toBeTrue()
        ->and($user->refresh()->last_login_at)->not->toBeNull();
});

it('matches the username case-insensitively and ignores surrounding spaces', function (string $typed): void {
    $user = User::factory()->create(['username' => 'sari.lestari']);

    $this->post('/login', ['username' => $typed, 'password' => TEST_PASSWORD])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
})->with(['Sari.Lestari', '  sari.lestari  ', 'SARI.LESTARI']);

it('stores usernames normalised', function (): void {
    expect(User::factory()->create(['username' => '  Budi_S  '])->username)->toBe('budi_s');
});

it('gives the same generic error for a wrong password and an unknown username', function (string $username, string $password): void {
    User::factory()->create(['username' => 'sari']);

    $this->post('/login', ['username' => $username, 'password' => $password])
        ->assertSessionHasErrors(['username' => 'Username atau kata sandi salah.']);

    $this->assertGuest();
})->with([
    'wrong password' => ['sari', 'wrong-password-1'],
    'unknown username' => ['nobody', TEST_PASSWORD],
]);

it('rejects a deactivated account', function (): void {
    User::factory()->create(['username' => 'sari', 'is_active' => false]);

    $this->post('/login', ['username' => 'sari', 'password' => TEST_PASSWORD])
        ->assertSessionHasErrors(['username' => 'Username atau kata sandi salah.']);

    $this->assertGuest();
});

it('does not sign in with an email any more', function (): void {
    $user = User::factory()->create(['username' => 'sari']);
    $user->forceFill(['email' => 'sari@example.test'])->save();

    $this->post('/login', ['email' => 'sari@example.test', 'password' => TEST_PASSWORD])->assertSessionHasErrors('username');
    $this->post('/login', ['username' => 'sari@example.test', 'password' => TEST_PASSWORD])->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('logs out a user deactivated mid-session', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->update(['is_active' => false]);

    $this->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

it('rate limits attempts on one account', function (): void {
    User::factory()->create(['username' => 'sari']);

    foreach (range(1, 5) as $ignored) {
        $this->post('/login', ['username' => 'sari', 'password' => 'wrong-password-1']);
    }

    $this->post('/login', ['username' => 'sari', 'password' => TEST_PASSWORD])->assertSessionHasErrors('username');
    $this->assertGuest();
});

it('rate limits one address trying many usernames', function (): void {
    User::factory()->create(['username' => 'sari']);

    foreach (range(1, 20) as $n) {
        $this->post('/login', ['username' => "guess-{$n}", 'password' => 'wrong-password-1']);
    }

    expect(RateLimiter::tooManyAttempts('login-ip:127.0.0.1', 20))->toBeTrue();
    $this->post('/login', ['username' => 'sari', 'password' => TEST_PASSWORD])->assertSessionHasErrors('username');
    $this->assertGuest();
});

it('sets a remember token when asked', function (): void {
    $user = User::factory()->create(['username' => 'sari', 'remember_token' => null]);

    $this->post('/login', ['username' => 'sari', 'password' => TEST_PASSWORD, 'remember' => true]);

    expect($user->refresh()->remember_token)->not->toBeNull();
});

it('logs out', function (): void {
    $this->actingAs(admin())->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});

it('protects every back-office route', function (string $uri): void {
    $this->get($uri)->assertRedirect('/login');
})->with(['/dashboard', '/transactions', '/payment-events', '/reconciliation', '/audit-log', '/settings']);

it('has no email password reset', function (string $method, string $uri): void {
    $this->call($method, $uri, ['email' => 'sari@example.test'])->assertNotFound();
})->with([
    ['GET', '/forgot-password'],
    ['POST', '/forgot-password'],
    ['GET', '/reset-password/some-token'],
    ['POST', '/reset-password'],
]);

it('changes the own password on the account page, with an audit trail (ADR-0015)', function (): void {
    $user = admin();

    $this->actingAs($user)->get('/account/password')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('account/AccountPasswordPage'));
    $this->put('/account/password', ['current_password' => 'wrong', 'password' => 'another-password-1', 'password_confirmation' => 'another-password-1'])->assertSessionHasErrors('current_password');
    $this->put('/account/password', ['current_password' => TEST_PASSWORD, 'password' => 'another-password-1', 'password_confirmation' => 'another-password-1'])->assertSessionHasNoErrors();

    expect(Hash::check('another-password-1', $user->refresh()->password))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'user.password_changed')->count())->toBe(1);
});

it('has no self-service profile or settings password routes (ADR-0015)', function (string $uri, array $data): void {
    $user = admin();
    $name = $user->name;

    $this->actingAs($user)->put($uri, $data)->assertNotFound();

    expect($user->refresh()->name)->toBe($name)
        ->and(AuditLog::query()->where('action', 'user.profile_updated')->exists())->toBeFalse();
})->with([
    ['/settings/profile', ['name' => 'Baru']],
    ['/settings/password', ['current_password' => 'password-for-tests-1', 'password' => 'another-password-1', 'password_confirmation' => 'another-password-1']],
]);

it('keeps the account page behind auth', function (): void {
    $this->get('/account/password')->assertRedirect('/login');
});

it('shares the username, not the email, with the pages', function (): void {
    $user = admin();
    $user->forceFill(['email' => 'hidden@example.test'])->save();

    $response = $this->actingAs($user)->get('/settings')->assertOk();

    $response->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.username', $user->username)->missing('auth.user.email'));
    expect($response->getContent())->not->toContain('hidden@example.test');
});

it('has no public registration', function (): void {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});
