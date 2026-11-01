<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia;

const TEST_PASSWORD = 'password-for-tests-1';

it('renders the login page', function (): void {
    $this->get('/login')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('auth/LoginPage'));
});

it('logs in, regenerates the session, and audits', function (): void {
    $user = User::factory()->create();
    $this->startSession();
    $before = session()->getId();

    $this->post('/login', ['email' => $user->email, 'password' => TEST_PASSWORD])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($before)
        ->and(AuditLog::query()->where('action', 'auth.login')->where('actor_user_id', $user->id)->exists())->toBeTrue()
        ->and($user->refresh()->last_login_at)->not->toBeNull();
});

it('rejects a wrong password without saying which field was wrong', function (): void {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rejects a deactivated account', function (): void {
    $user = User::factory()->create(['is_active' => false]);

    $this->post('/login', ['email' => $user->email, 'password' => TEST_PASSWORD])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out a user deactivated mid-session', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->update(['is_active' => false]);

    $this->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

it('rate limits login attempts', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 5) as $ignored) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => TEST_PASSWORD])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('sets a remember token when asked', function (): void {
    $user = User::factory()->create(['remember_token' => null]);

    $this->post('/login', ['email' => $user->email, 'password' => TEST_PASSWORD, 'remember' => true]);

    expect($user->refresh()->remember_token)->not->toBeNull();
});

it('logs out', function (): void {
    $this->actingAs(admin())->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});

it('protects every back-office route', function (string $uri): void {
    $this->get($uri)->assertRedirect('/login');
})->with(['/dashboard', '/transactions', '/payment-events', '/reconciliation', '/audit-log', '/settings']);

it('sends a reset link without revealing whether the account exists', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
    $this->post('/forgot-password', ['email' => 'nobody@example.test'])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertCount(1);
});

it('resets a password with a valid token and rejects weak ones', function (): void {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');

    $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'new-password-long-1', 'password_confirmation' => 'new-password-long-1'])->assertRedirect('/login');

    expect(Hash::check('new-password-long-1', $user->refresh()->password))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'auth.password_reset')->exists())->toBeTrue();
});

it('updates the profile and password with an audit trail', function (): void {
    $user = admin(Role::Operations);

    $this->actingAs($user)->put('/settings/profile', ['name' => 'Baru', 'email' => 'baru@example.test'])->assertSessionHasNoErrors();
    $this->put('/settings/password', ['current_password' => 'wrong', 'password' => 'another-password-1', 'password_confirmation' => 'another-password-1'])->assertSessionHasErrors('current_password');
    $this->put('/settings/password', ['current_password' => TEST_PASSWORD, 'password' => 'another-password-1', 'password_confirmation' => 'another-password-1'])->assertSessionHasNoErrors();

    expect($user->refresh()->name)->toBe('Baru')
        ->and(Hash::check('another-password-1', $user->password))->toBeTrue()
        ->and(AuditLog::query()->whereIn('action', ['user.profile_updated', 'user.password_changed'])->count())->toBe(2);
});

it('has no public registration', function (): void {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});
