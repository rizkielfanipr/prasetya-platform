<?php

declare(strict_types=1);

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Models\User;
use App\Domain\Payments\Actions\ExpireStaleRecords;
use App\Integrations\Doku\DokuConfig;
use App\Integrations\Doku\DokuSignature;
use App\Support\Idempotency\IdempotencyKey;
use App\Support\Logging\OperationLog;
use Illuminate\Http\Request;
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

Artisan::command('doku:simulate-notification {invoice} {amount} {--status=SUCCESS} {--channel=VIRTUAL_ACCOUNT_BCA}', function (DokuConfig $config): int {
    if (app()->isProduction() || config('platform.payment_gateway') !== 'fake') {
        $this->error('Hanya tersedia dengan gateway fake di luar production.');

        return 1;
    }

    if (! $config->isComplete()) {
        $this->error('Isi DOKU_CLIENT_ID dan DOKU_SECRET_KEY dengan nilai lokal apa pun untuk menandatangani simulasi.');

        return 1;
    }

    $body = (string) json_encode([
        'order' => ['invoice_number' => $this->argument('invoice'), 'amount' => (int) $this->argument('amount')],
        'transaction' => ['status' => $this->option('status'), 'date' => gmdate('Y-m-d\TH:i:s\Z'), 'original_request_id' => (string) Str::uuid()],
        'channel' => ['id' => $this->option('channel')],
    ]);
    $requestId = (string) Str::uuid();
    $timestamp = gmdate('Y-m-d\TH:i:s\Z');

    $request = Request::create($config->notificationPath, 'POST', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_CLIENT_ID' => $config->clientId,
        'HTTP_REQUEST_ID' => $requestId,
        'HTTP_REQUEST_TIMESTAMP' => $timestamp,
        'HTTP_SIGNATURE' => DokuSignature::sign($config->secretKey, $config->clientId, $requestId, $timestamp, $config->notificationPath, $body),
    ], content: $body);

    $response = app()->handle($request);
    $this->line("HTTP {$response->getStatusCode()} {$response->getContent()}");

    return $response->isSuccessful() ? 0 : 1;
})->purpose('Send a correctly signed local notification through the real webhook path');

Schedule::command('platform:expire-stale')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('platform:prune-idempotency')->daily();
