<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Models\User;
use App\Domain\Brands\Enums\ApplicationCode;
use App\Domain\Orders\Actions\CreateOrder;
use App\Domain\Orders\Data\CreateOrderData;
use App\Domain\Orders\Models\Order;
use App\Domain\Packages\Actions\SetPackageAvailability;
use App\Domain\Packages\Models\Package;
use App\Domain\Payments\Actions\ProcessGatewayNotification;
use App\Domain\Payments\Actions\StartCheckout;
use App\Domain\Payments\Data\InboundNotification;
use App\Integrations\Duitku\FakeNotificationSender;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local and test data, built through the real actions and the real callback path so the demo cannot show a state
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

        // Checkouts below would otherwise call the real gateway.
        if (config('platform.payment_gateway') !== 'fake') {
            $this->command?->warn('Demo data dilewati: butuh PAYMENT_GATEWAY=fake.');

            return;
        }

        if (Order::query()->exists()) {
            return;
        }

        $sender = app(FakeNotificationSender::class);

        // Packages start inactive until approved. Local demo orders need them, so they are switched on through the
        // same audited action an operator uses; this seeder never runs outside local and testing (checked above).
        foreach (Package::query()->where('is_active', false)->pluck('code') as $code) {
            app(SetPackageAvailability::class)($code, true, 'Data demo lokal');
        }

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

            match ($i % 6) {
                0, 1, 3 => $sender->pay($payment, ['SP', 'NQ', 'SQ'][$i % 3]),
                2 => null, // Left pending.
                4 => $sender->expire($payment),
                5 => $sender->pay($payment, 'SP', $payment->amount - 1000), // Amount mismatch evidence.
            };

            if ($i === 0) {
                $sender->pay($payment, 'SP'); // Same content again: a duplicate delivery.
            }

            Carbon::setTestNow();
        }

        app(ProcessGatewayNotification::class)(new InboundNotification((string) Str::uuid(), 'merchantCode=FAKE-MERCHANT&amount=1000&merchantOrderId=PW-000000-000000&signature=not-valid'));
        $sender->send('PW-199901-000001', 750000, '00', 'NQ', 'demo-unmatched');
    }
}
