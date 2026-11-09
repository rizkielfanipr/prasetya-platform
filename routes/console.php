<?php

declare(strict_types=1);

use App\Domain\Auth\Actions\CreateUser;
use App\Domain\Auth\Actions\ResetUserPassword;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Username;
use App\Domain\Content\Actions\AddContentFact;
use App\Domain\Content\Actions\SavePillar;
use App\Domain\Content\Actions\UpdateBrandProfile;
use App\Domain\Content\Ai\CopyBudget;
use App\Domain\Content\Ai\CopyEvaluation;
use App\Domain\Content\Ai\CopyModels;
use App\Domain\Content\Ai\CopyPromptBuilder;
use App\Domain\Content\Contracts\CopyGenerator;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Migration\ContentStatusAudit;
use App\Domain\Content\Migration\LegacyStatusMap;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentFact;
use App\Domain\Content\Models\ContentPillar;
use App\Domain\Packages\Actions\SetPackageAvailability;
use App\Domain\Packages\Models\Package;
use App\Domain\Payments\Actions\ExpireStaleRecords;
use App\Integrations\Duitku\FakeNotificationSender;
use App\Support\Idempotency\IdempotencyKey;
use App\Support\Logging\OperationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
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

// Content Studio (ADR-0011, phase 2): apply a reviewed brand file (rules, pillars, facts) through the audited actions.
// Re-running is safe: unchanged rules, existing pillars, and facts already in force are skipped.
Artisan::command('content:import-brand {code} {file} {--actor= : Username recorded in the audit}', function (UpdateBrandProfile $rules, SavePillar $pillars, AddContentFact $facts): int {
    $brand = ContentBrand::query()->where('code', (string) $this->argument('code'))->first();
    $actor = User::query()->where('username', Username::normalize((string) $this->option('actor')))->where('is_active', true)->first();
    $data = json_decode((string) @file_get_contents(base_path((string) $this->argument('file'))), true);
    if ($brand === null || $actor === null || ! is_array($data)) {
        $this->error('Brand, file, atau --actor (username aktif) tidak ditemukan.');

        return Command::FAILURE;
    }

    try {
        $profile = $rules($brand, (array) ($data['rules'] ?? []), (string) ($data['change_note'] ?? 'Impor aturan brand'), $actor);
        $this->info("Aturan brand disimpan sebagai versi {$profile->version}.");
    } catch (ContentActionRefused $refused) {
        $this->line($refused->getMessage());
    }
    foreach ((array) ($data['pillars'] ?? []) as $pillar) {
        if (ContentPillar::query()->where('content_brand_id', $brand->id)->where('name', $pillar['name'])->exists()) {
            $this->line("Pilar {$pillar['name']} sudah ada, dilewati.");

            continue;
        }
        try {
            $pillars($brand, null, (string) $pillar['name'], $pillar['description'] ?? null, (int) $pillar['target_share'], true, $actor);
            $this->info("Pilar {$pillar['name']} ditambahkan.");
        } catch (ContentActionRefused $refused) {
            $this->warn("Pilar {$pillar['name']}: {$refused->getMessage()}");
        }
    }
    foreach ((array) ($data['facts'] ?? []) as $fact) {
        if (ContentFact::query()->where('content_brand_id', $brand->id)->whereNull('retired_at')->where('statement', $fact['statement'])->exists()) {
            continue;
        }
        $facts($brand, null, (string) $fact['statement'], (string) $fact['source'], null, $actor);
        $this->info('Fakta ditambahkan: '.Str::limit((string) $fact['statement'], 70));
    }

    return Command::SUCCESS;
})->purpose('Apply a reviewed Content Studio brand file through the audited actions');

// Content Studio (ADR-0012, phase 0): read-only rehearsal of the status migration. Prints counts, the planned
// mapping, and invariant breaches; changes nothing. Run it on a production copy before migrating.
Artisan::command('content:status-audit {--json : Machine-readable output}', function (ContentStatusAudit $audit): int {
    $report = $audit->run();
    if ($this->option('json')) {
        $this->line((string) json_encode($report, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }
    $this->table(['Status lama', 'Jumlah', 'Status target'], array_map(
        static fn (string $status, int $count): array => [$status, $count, LegacyStatusMap::FORWARD[$status] ?? 'TIDAK DIKENAL'],
        array_keys($report['statuses']),
        $report['statuses'],
    ));
    $this->table(['Status target', 'Jumlah'], array_map(null, array_keys($report['target']), $report['target']));
    $this->line(sprintf('Item %d · tanpa revisi aktif %d · approved_revision_id terisi %d · approval %d · publikasi %d',
        $report['items']['total'], $report['items']['without_active_revision'], $report['items']['approved_revision_set'],
        $report['approvals']['total'], $report['publications']['total']));
    $breaches = array_filter($report['issues']);
    $this->table(['Pemeriksaan', 'Jumlah'], array_map(null, array_keys($report['issues']), $report['issues']));
    if ($breaches !== [] || $report['unknown'] !== []) {
        $this->warn('Ada data yang tidak sesuai invariant atau status tak dikenal. Jangan migrasi sebelum diputuskan.');

        return Command::FAILURE;
    }
    $this->info('Siap dimigrasi: semua status dikenal dan tidak ada pelanggaran invariant.');

    return Command::SUCCESS;
})->purpose('Read-only audit of Content Studio statuses before the ADR-0012 migration');

// Content Studio (ADR-0011, phase 3): check the configured AI provider against the evaluation scenarios before
// enabling or switching it. Calls the provider for real when one is configured; writes nothing to the database.
Artisan::command('content:eval-copy {--file=resources/content-eval/copy-v1.json} {--max-cost=0.50 : Stop when the estimated total passes this (USD)} {--model= : Evaluate another allowed model without changing the chosen one}', function (CopyEvaluation $evaluation, CopyBudget $budget, CopyGenerator $generator, CopyModels $models): int {
    $model = $this->option('model');
    if (is_string($model) && $model !== '' && ! $models->useForThisProcess($model)) {
        $this->error("Model {$model} tidak ada di CONTENT_TEXT_MODELS.");

        return Command::FAILURE;
    }
    $this->line('Provider '.$generator->provider().', model '.($generator->model() ?? '-').'.');
    $reason = $budget->unavailableReason();
    if ($reason !== null) {
        $this->error($reason);

        return Command::FAILURE;
    }
    $scenarios = json_decode((string) file_get_contents(base_path((string) $this->option('file'))), true)['scenarios'] ?? [];
    $rows = [];
    $results = [];
    $spent = 0.0;
    foreach ($scenarios as $scenario) {
        if ($spent >= (float) $this->option('max-cost')) {
            $this->warn('Batas biaya evaluasi tercapai; skenario sisanya dilewati.');
            break;
        }
        $result = $evaluation->run($scenario);
        $spent += $result['cost'];
        $results[] = $result;
        $rows[] = [$result['name'], $result['ok'] ? 'lulus' : 'GAGAL', count($result['errors']), count($result['warnings']), $result['tokens'], sprintf('$%.5f', $result['cost']), $result['latency_ms'].' ms'];
    }
    $this->table(['Skenario', 'Aturan keras', 'Pelanggaran', 'Peringatan', 'Token', 'Biaya', 'Durasi'], $rows);
    $path = 'content-eval/'.now()->format('Ymd-His').'.json';
    Storage::disk((string) config('content.disk'))->put($path, (string) json_encode(['provider' => $generator->provider(), 'model' => $generator->model(), 'prompt_version' => CopyPromptBuilder::VERSION, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $this->line("Hasil lengkap untuk dinilai manusia (suara brand, relevansi): storage/app/private/{$path}");
    $failed = count(array_filter($results, static fn (array $r): bool => ! $r['ok']));
    $this->line(sprintf('%d dari %d skenario lulus aturan keras; total biaya perkiraan $%.5f.', count($results) - $failed, count($results), $spent));

    return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
})->purpose('Evaluate the configured content AI provider against the copy scenarios');

Schedule::command('platform:expire-stale')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('platform:prune-idempotency')->daily();
