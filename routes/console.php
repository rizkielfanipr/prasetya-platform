<?php

declare(strict_types=1);

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Models\User;
use App\Domain\Payments\Actions\ExpireStaleRecords;
use App\Integrations\Duitku\FakeNotificationSender;
use App\Support\Idempotency\IdempotencyKey;
use App\Support\Logging\OperationLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

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

Artisan::command('platform:create-admin {email} {name} {--role=SUPER_ADMIN}', function (): int {
    $password = (string) $this->secret('Kata sandi (minimal 12 karakter, huruf dan angka)');

    if (strlen($password) < 12) {
        $this->error('Kata sandi terlalu pendek.');

        return 1;
    }

    User::query()->create([
        'email' => (string) $this->argument('email'),
        'name' => (string) $this->argument('name'),
        'role' => Role::from((string) $this->option('role')),
        'password' => $password,
    ]);
    $this->info('Admin dibuat.');

    return 0;
})->purpose('Create a back-office user');

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
