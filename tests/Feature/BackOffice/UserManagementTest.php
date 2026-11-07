<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Actions\SetUserActive;
use App\Domain\Auth\Exceptions\UserChangeRefused;
use App\Domain\Auth\Models\User;
use App\Http\Presenters\UserPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

const NEW_PASSWORD = 'colleague-password-1';

/** @return array<string, string> */
function newUser(array $overrides = []): array
{
    return [...['name' => 'Rekan Baru', 'username' => 'rekan', 'password' => NEW_PASSWORD, 'password_confirmation' => NEW_PASSWORD], ...$overrides];
}

function giveSession(User $user, string $id): void
{
    DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
}

it('lists users with search, status filter, and no email', function (): void {
    $viewer = User::factory()->create(['name' => 'Admin Satu', 'username' => 'admin1']);
    $viewer->forceFill(['email' => 'hidden@example.test'])->save();
    User::factory()->create(['name' => 'Sari Lestari', 'username' => 'sari']);
    User::factory()->create(['name' => 'Budi', 'username' => 'budi', 'is_active' => false]);

    $response = $this->actingAs($viewer)->get('/users')->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('users/UserListPage')
        ->where('users.meta.total', 3)
        ->where('users.data.0.isSelf', true)
        ->where('users.data.0.deactivateBlockedReason', 'Anda tidak dapat menonaktifkan akun sendiri.')
        ->missing('users.data.0.email'));
    expect($response->getContent())->not->toContain('hidden@example.test');

    $this->get('/users?search=SAR')->assertInertia(fn (AssertableInertia $page) => $page->where('users.meta.total', 1)->where('users.data.0.username', 'sari'));
    $this->get('/users?status=inactive')->assertInertia(fn (AssertableInertia $page) => $page->where('users.meta.total', 1)->where('users.data.0.username', 'budi'));
    $this->get('/users?status=nope')->assertSessionHasErrors('status');
});

it('creates a user who can then sign in, audited without the password', function (): void {
    $admin = admin();

    $this->actingAs($admin)->post('/users', newUser(['username' => '  Rekan.Baru  ']))->assertSessionHas('success');

    $user = User::query()->where('username', 'rekan.baru')->sole();
    $audit = AuditLog::query()->where('action', 'user.created')->sole();
    expect($user->is_active)->toBeTrue()
        ->and(Hash::check(NEW_PASSWORD, $user->password))->toBeTrue()
        ->and($audit->actor_user_id)->toBe($admin->id)
        ->and($audit->entity_id)->toBe($user->public_id)
        ->and(json_encode($audit->getAttributes()))->not->toContain(NEW_PASSWORD)->not->toContain($user->password);

    auth()->logout();
    $this->post('/login', ['username' => 'rekan.baru', 'password' => NEW_PASSWORD])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

it('validates a new user', function (array $input, string $field): void {
    User::factory()->create(['username' => 'taken']);

    $this->actingAs(admin())->post('/users', newUser($input))->assertSessionHasErrors($field);

    expect(User::query()->where('name', 'Rekan Baru')->exists())->toBeFalse();
})->with([
    'name missing' => [['name' => ''], 'name'],
    'username missing' => [['username' => ''], 'username'],
    'username too short' => [['username' => 'ab'], 'username'],
    'username too long' => [['username' => str_repeat('a', 33)], 'username'],
    'username bad characters' => [['username' => 'rekan baru'], 'username'],
    'username bad edge' => [['username' => '-rekan'], 'username'],
    'username taken, other case' => [['username' => 'TAKEN'], 'username'],
    'password too short' => [['password' => 'short-1', 'password_confirmation' => 'short-1'], 'password'],
    'password without digits' => [['password' => 'onlyletterspassword', 'password_confirmation' => 'onlyletterspassword'], 'password'],
    'password not confirmed' => [['password_confirmation' => 'different-password-1'], 'password'],
]);

it('changes the name only and ignores a username in the request', function (): void {
    $user = User::factory()->create(['name' => 'Lama', 'username' => 'tetap']);

    $this->actingAs(admin())->put("/users/{$user->public_id}", ['name' => 'Nama Baru', 'username' => 'diganti'])->assertSessionHas('success');

    $user->refresh();
    expect($user->name)->toBe('Nama Baru')
        ->and($user->username)->toBe('tetap')
        ->and(AuditLog::query()->where('action', 'user.updated')->sole()->new_state)->toBe(['name' => 'Nama Baru']);
});

it('deactivates a user, ends their sessions at once, and can activate them again', function (): void {
    $admin = admin();
    $user = User::factory()->create(['remember_token' => 'remember-me']);
    giveSession($user, 'their-session');

    $this->actingAs($admin)->post("/users/{$user->public_id}/deactivate")->assertSessionHas('success');

    $user->refresh();
    expect($user->is_active)->toBeFalse()
        ->and($user->remember_token)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'user.deactivated')->where('entity_id', $user->public_id)->exists())->toBeTrue();

    $this->post("/users/{$user->public_id}/activate")->assertSessionHas('success');
    expect($user->refresh()->is_active)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'user.activated')->exists())->toBeTrue();
});

it('signs a deactivated user out on their next request', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/dashboard')->assertOk();

    $user->update(['is_active' => false]);

    $this->get('/users')->assertRedirect('/login');
    $this->assertGuest();
});

it('refuses to deactivate yourself', function (): void {
    $admin = admin();
    User::factory()->create();

    $this->actingAs($admin)->post("/users/{$admin->public_id}/deactivate")->assertSessionHas('error', 'Anda tidak dapat menonaktifkan akun sendiri.');

    expect($admin->refresh()->is_active)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'user.deactivated')->exists())->toBeFalse();
});

it('refuses to deactivate the last active user', function (): void {
    $last = User::factory()->create();
    User::factory()->create(['is_active' => false]);

    // Over HTTP the only active user is always the actor, so the self rule answers first; the domain still refuses.
    expect(fn () => app(SetUserActive::class)($last, false, null))
        ->toThrow(UserChangeRefused::class, 'Pengguna aktif terakhir tidak dapat dinonaktifkan.');

    expect($last->refresh()->is_active)->toBeTrue();
});

it('explains on the list why a user cannot be deactivated', function (): void {
    $viewer = User::factory()->create();
    $last = User::factory()->create();

    expect(UserPresenter::row($last, $viewer, 1)['deactivateBlockedReason'])->toBe('Pengguna aktif terakhir tidak dapat dinonaktifkan.')
        ->and(UserPresenter::row($viewer, $viewer, 2)['deactivateBlockedReason'])->toBe('Anda tidak dapat menonaktifkan akun sendiri.')
        ->and(UserPresenter::row($last, $viewer, 2)['deactivateBlockedReason'])->toBeNull();
});

it('sets another user\'s password and ends their sessions', function (): void {
    $user = User::factory()->create(['username' => 'sari', 'remember_token' => 'remember-me']);
    giveSession($user, 'their-session');

    $this->actingAs(admin())->put("/users/{$user->public_id}/password", ['password' => 'fresh-password-1', 'password_confirmation' => 'fresh-password-1'])
        ->assertSessionHas('success');

    $user->refresh();
    $audit = AuditLog::query()->where('action', 'user.password_reset')->sole();
    expect(Hash::check('fresh-password-1', $user->password))->toBeTrue()
        ->and($user->remember_token)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(json_encode($audit->getAttributes()))->not->toContain('fresh-password-1');
});

it('validates a new password and refuses to reset your own here', function (): void {
    $admin = admin();
    $user = User::factory()->create();

    $this->actingAs($admin)->put("/users/{$user->public_id}/password", ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    $this->put("/users/{$admin->public_id}/password", ['password' => 'fresh-password-1', 'password_confirmation' => 'fresh-password-1'])
        ->assertSessionHas('error', 'Ganti kata sandi Anda sendiri di Pengaturan, dengan kata sandi lama.');

    expect(Hash::check('fresh-password-1', $admin->refresh()->password))->toBeFalse();
});

it('addresses users by public id only', function (string $method, string $suffix): void {
    $user = User::factory()->create();

    $this->actingAs(admin())->call($method, "/users/{$user->id}{$suffix}", ['name' => 'X'])->assertNotFound();
})->with([
    ['PUT', ''],
    ['POST', '/deactivate'],
    ['POST', '/activate'],
    ['PUT', '/password'],
]);

it('keeps the users screen and actions behind sign-in', function (string $method, string $uri): void {
    $user = User::factory()->create();

    $this->call($method, str_replace('{id}', $user->public_id, $uri))->assertRedirect('/login');
})->with([
    ['GET', '/users'],
    ['POST', '/users'],
    ['PUT', '/users/{id}'],
    ['POST', '/users/{id}/deactivate'],
    ['PUT', '/users/{id}/password'],
]);
