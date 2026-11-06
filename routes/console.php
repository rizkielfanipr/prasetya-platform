<?php

declare(strict_types=1);

use App\Domain\Auth\Actions\CreateUser;
use App\Domain\Auth\Actions\ResetUserPassword;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Username;
use App\Domain\Packages\Actions\SetPackageAvailability;
use App\Domain\Packages\Models\Package;
use App\Domain\Payments\Actions\ExpireStaleRecords;
use App\Integrations\Duitku\FakeNotificationSender;
use App\Support\Idempotency\IdempotencyKey;
use App\Support\Logging\OperationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

Artisan::command('platform:expire-stale', function (ExpireStaleRecords $expire): void {
    $startedAt = microtime(true);
    $counts = $expire();
    OperationLog::record('payments.expire_stale', 'OK', $counts, $startedAt);
    $this->info("Expired {$counts['payments']} payments and {$counts['orders']} orders.");
})->purpose('Expire overdue payments and orders');

Artisan::command('platform:prune-idempotency {--days=7}', function (): void {
    $removed = IdempotencyKey::query()->where('created_at', '<', now()->subDays((int) $this->option('days')))->delete();
    $this->info("Removed {$removed} idempotency keys.");
})->purpose('Remove old idempotency keys');

/** Reads a password twice without echoing it and checks it against Password::defaults(). Null when refused. */
$askPassword = function (Command $command): ?string {
    $password = (string) $command->secret('Kata sandi (minimal 12 karakter, huruf dan angka)');

    if ($password !== (string) $command->secret('Ulangi kata sandi')) {
        $command->error('Kata sandi tidak sama.');

        return null;
    }

    $validator = Validator::make(['password' => $password], ['password' => ['required', Password::defaults()]]);

    if ($validator->fails()) {
        $command->error((string) $validator->errors()->first('password'));

        return null;
    }

    return $password;
};

Artisan::command('platform:create-admin {username} {name}', function (CreateUser $create) use ($askPassword): int {
    $username = Username::normalize((string) $this->argument('username'));
    $validator = Validator::make(['username' => $username, 'name' => (string) $this->argument('name')], [
        'username' => Username::rules(),
        'name' => ['required', 'string', 'max:255'],
    ]);

    if ($validator->fails()) {
        $this->error((string) $validator->errors()->first());

        return 1;
    }

    $password = $askPassword($this);

    if ($password === null) {
        return 1;
    }

    $create((string) $this->argument('name'), $username, $password);
    $this->info("Pengguna {$username} dibuat.");

    return 0;
})->purpose('Create a back-office user (username cannot be changed later)');

Artisan::command('platform:reset-password {username}', function (ResetUserPassword $reset) use ($askPassword): int {
    $user = User::query()->where('username', Username::normalize((string) $this->argument('username')))->first();

    if ($user === null) {
        $this->error('Username tidak ditemukan.');

        return 1;
    }

    $password = $askPassword($this);

    if ($password === null) {
        return 1;
    }

    $reset($user, $password);
    $this->info("Kata sandi {$user->username} diganti. Semua sesinya diakhiri.");

    return 0;
})->purpose('Set a new password for a user and sign them out everywhere');

Artisan::command('platform:list-users', function (): int {
    $timezone = (string) config('platform.business_timezone');

    $this->table(['Username', 'Nama', 'Status', 'Login terakhir'], User::query()->orderBy('username')->get()->map(static fn (User $user): array => [
        $user->username,
        $user->name,
        $user->is_active ? 'Aktif' : 'Nonaktif',
        $user->last_login_at?->setTimezone($timezone)->format('Y-m-d H:i') ?? '-',
    ])->all());

    return 0;
})->purpose('List back-office users');

foreach (['activate' => true, 'deactivate' => false] as $verb => $active) {
    Artisan::command("platform:package-{$verb} {code} {--reason= : Why, kept in the audit log} {--force : Skip the confirmation}", function (SetPackageAvailability $availability) use ($verb, $active): int {
        $code = (string) $this->argument('code');
        $reason = trim((string) $this->option('reason'));
        $package = Package::query()->with('application')->where('code', $code)->first();

        if ($package === null || $reason === '') {
            $this->error($package === null ? "Paket {$code} tidak ditemukan." : 'Isi --reason.');

            return 1;
        }

        $this->line("{$package->code} · {$package->application->code->value} · {$package->name} · {$package->currency} {$package->price_amount}");

        if (! $this->option('force') && ! $this->confirm("{$verb} paket ini?")) {
            $this->warn('Dibatalkan.');

            return 1;
        }

        $changed = $availability($code, $active, $reason);
        $this->info($changed ? ($active ? 'Paket aktif.' : 'Paket nonaktif. Pembayaran yang sudah dibuat tetap diproses.') : 'Tidak ada perubahan.');

        return 0;
    })->purpose($active ? 'Make a catalogue package purchasable' : 'Stop new orders for a catalogue package');
}

Artisan::command('platform:simulate-notification {invoice} {amount} {--result=00} {--channel=SP}', function (FakeNotificationSender $sender): int {
    if (app()->isProduction() || config('platform.payment_gateway') !== 'fake') {
        $this->error('Hanya tersedia dengan gateway fake di luar production.');

        return 1;
    }

    $event = $sender->send((string) $this->argument('invoice'), (int) $this->argument('amount'), (string) $this->option('result'), (string) $this->option('channel'), (string) Str::uuid());
    $this->line("{$event->status->value} {$event->rejection_reason?->value}");

    return 0;
})->purpose('Send a correctly signed local callback through the real verification path');

Schedule::command('platform:expire-stale')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('platform:prune-idempotency')->daily();
