<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

const PROMPT = 'Kata sandi (minimal 12 karakter, huruf dan angka)';
const REPEAT = 'Ulangi kata sandi';

it('creates a user with a normalised username and audits it without the password', function (): void {
    $this->artisan('platform:create-admin Sari.Lestari "Sari Lestari"')
        ->expectsQuestion(PROMPT, 'strong-password-1')
        ->expectsQuestion(REPEAT, 'strong-password-1')
        ->assertSuccessful();

    $user = User::query()->sole();
    $audit = AuditLog::query()->where('action', 'user.created')->sole();

    expect($user->username)->toBe('sari.lestari')
        ->and($user->is_active)->toBeTrue()
        ->and(Hash::check('strong-password-1', $user->password))->toBeTrue()
        ->and($audit->actor_label)->toBe('Sistem')
        ->and(json_encode($audit->getAttributes()))->not->toContain('strong-password-1')->not->toContain($user->password);
});

it('refuses invalid or taken usernames before asking for a password', function (string $username): void {
    User::factory()->create(['username' => 'taken']);

    $this->artisan('platform:create-admin', ['username' => $username, 'name' => 'Nama'])->assertFailed();

    expect(User::query()->count())->toBe(1);
})->with(['ab', 'bad name', '-dash', 'TAKEN']);

it('refuses a weak or mismatched password', function (string $first, string $second): void {
    $this->artisan('platform:create-admin sari Sari')
        ->expectsQuestion(PROMPT, $first)
        ->expectsQuestion(REPEAT, $second)
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
})->with([
    'too short' => ['short-1', 'short-1'],
    'no digit' => ['onlyletterspassword', 'onlyletterspassword'],
    'mismatch' => ['strong-password-1', 'strong-password-2'],
]);

it('resets a password from the command line and ends that user\'s sessions', function (): void {
    $user = User::factory()->create(['username' => 'sari', 'remember_token' => 'remember-me']);
    DB::table('sessions')->insert(['id' => 'session-1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->artisan('platform:reset-password SARI')
        ->expectsQuestion(PROMPT, 'brand-new-password-1')
        ->expectsQuestion(REPEAT, 'brand-new-password-1')
        ->assertSuccessful();

    $user->refresh();
    expect(Hash::check('brand-new-password-1', $user->password))->toBeTrue()
        ->and($user->remember_token)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'user.password_reset')->where('entity_id', $user->public_id)->sole()->actor_label)->toBe('Sistem');
});

it('refuses to reset an unknown username', function (): void {
    $this->artisan('platform:reset-password nobody')->assertFailed();
});

it('lists users without emails or passwords', function (): void {
    User::factory()->create(['username' => 'sari', 'name' => 'Sari Lestari']);
    User::factory()->create(['username' => 'budi', 'name' => 'Budi', 'is_active' => false]);

    $this->artisan('platform:list-users')
        ->expectsTable(['Username', 'Nama', 'Status', 'Login terakhir'], [
            ['budi', 'Budi', 'Nonaktif', '-'],
            ['sari', 'Sari Lestari', 'Aktif', '-'],
        ])
        ->assertSuccessful();
});
