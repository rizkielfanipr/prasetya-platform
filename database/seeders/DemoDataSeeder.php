<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Models\User;
use App\Domain\Brands\Enums\ApplicationCode;
use App\Domain\Orders\Actions\CreateOrder;
use App\Domain\Orders\Data\CreateOrderData;
use App\Domain\Orders\Models\Order;
use App\Domain\Packages\Models\Package;
use App\Domain\Payments\Actions\ProcessGatewayNotification;
use App\Domain\Payments\Actions\StartCheckout;
use App\Domain\Payments\Contracts\NotificationVerifier;
use App\Domain\Payments\Data\InboundNotification;
use App\Domain\Payments\Models\Payment;
use App\Integrations\Doku\DokuConfig;
use App\Integrations\Doku\DokuSignature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local and test data, built through the real actions and the real webhook path so the demo cannot show a state
 * the application could not reach. Deterministic: fixed seed and synthetic @example.test addresses only.
 */
class DemoDataSeeder extends Seeder
{
    public const string PASSWORD = 'demo-password-only-1';

    public function run(CreateOrder $createOrder, StartCheckout $startCheckout): void
    {
        if (! app()->environment('local', 'testing', 'e2e')) {
            throw new RuntimeException('Demo data is for local and testing environments only.');
        }

        if (Order::query()->exists()) {
            return;
        }

        config(['doku.client_id' => 'DEMO-CLIENT', 'doku.secret_key' => 'demo-secret-not-real']);
        app()->forgetInstance(DokuConfig::class);
        app()->forgetInstance(NotificationVerifier::class);
        $config = app(DokuConfig::class);
        $process = app(ProcessGatewayNotification::class);

        foreach ([['demo.admin', 'Admin Demo', Role::SuperAdmin], ['demo.finance', 'Finance Demo', Role::Finance], ['demo.ops', 'Operasional Demo', Role::Operations]] as [$local, $name, $role]) {
            User::query()->firstOrCreate(['email' => "{$local}@example.test"], ['name' => $name, 'password' => self::PASSWORD, 'role' => $role]);
        }

        mt_srand(20261101);
        $names = ['Budi Santoso', 'Sari Indah Lestari', 'PT Maju Bersama', 'Ahmad Ridwan', 'CV Kreasi Digital', 'Dewi Anggraini', 'Rizky Pratama', 'Nadia Putri'];
        $packages = Package::query()->with('application')->get();

        for ($i = 0; $i < 36; $i++) {
            $package = $packages[$i % $packages->count()];
            $application = $package->application->code;
            $name = $names[$i % count($names)];
            Carbon::setTestNow(now()->subDays(mt_rand(0, 27))->subMinutes(mt_rand(0, 600)));

            $order = $createOrder(new CreateOrderData(
                application: $application,
                packageCode: $package->code,
                customerName: $name,
                customerEmail: Str::slug($name)."{$i}@example.test",
                customerPhone: '0812000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                details: $application === ApplicationCode::Webworks
                    ? ['template_code' => 'TPL-'.($i + 1), 'business_name' => $name]
                    : ['theme_code' => 'THEME-'.($i + 1), 'event_type' => 'wedding'],
            ));

            // Some orders never reach checkout, so "no attempt" has something to show.
            if ($i % 9 === 8) {
                Carbon::setTestNow();

                continue;
            }

            $payment = $startCheckout($order);
            $send = fn (string $status, ?int $amount = null, string $channel = 'VIRTUAL_ACCOUNT_BCA') => $this->notify($process, $config, $payment, $status, $amount ?? $payment->amount, $channel);

            match ($i % 6) {
                0, 1, 3 => $send('SUCCESS', null, ['VIRTUAL_ACCOUNT_BCA', 'QRIS', 'EMONEY_SHOPEEPAY'][$i % 3]),
                2 => null, // Left pending.
                4 => $send('EXPIRED'),
                5 => $send('SUCCESS', $payment->amount - 1000), // Amount mismatch evidence.
            };

            if ($i === 0) {
                $send('SUCCESS'); // Same content again: a duplicate delivery.
            }

            Carbon::setTestNow();
        }

        $process(new InboundNotification('DEMO-CLIENT', (string) Str::uuid(), gmdate('Y-m-d\TH:i:s\Z'), 'HMACSHA256=bm90LXZhbGlk', '{"order":{"invoice_number":"PW-000000-000000"}}'));
        $this->notifyRaw($process, $config, 'PW-199901-000001', 750000, 'SUCCESS', 'QRIS');
    }

    private function notify(ProcessGatewayNotification $process, DokuConfig $config, Payment $payment, string $status, int $amount, string $channel): void
    {
        $this->notifyRaw($process, $config, $payment->gateway_invoice_number, $amount, $status, $channel, $payment->gateway_request_id);
    }

    private function notifyRaw(ProcessGatewayNotification $process, DokuConfig $config, string $invoice, int $amount, string $status, string $channel, ?string $originalRequestId = null): void
    {
        $body = (string) json_encode([
            'order' => ['invoice_number' => $invoice, 'amount' => $amount],
            'transaction' => ['status' => $status, 'date' => now()->format('Y-m-d\TH:i:s\Z'), 'original_request_id' => $originalRequestId ?? 'demo-unmatched'],
            'channel' => ['id' => $channel],
        ]);
        $requestId = (string) Str::uuid();
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        $process(new InboundNotification($config->clientId, $requestId, $timestamp, DokuSignature::sign($config->secretKey, $config->clientId, $requestId, $timestamp, $config->notificationPath, $body), $body));
    }
}
