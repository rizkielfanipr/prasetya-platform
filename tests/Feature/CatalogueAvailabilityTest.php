<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Packages\Actions\SetPackageAvailability;
use App\Domain\Packages\Models\Package;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\WebhookStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\WebhookEvent;

function satamuActive(string $code = 'SAT-SPESIAL'): bool
{
    return (bool) Package::query()->where('code', $code)->value('is_active');
}

it('keeps Satamu out of the public catalogue until activated', function (): void {
    $codes = fn () => collect($this->getJson('/api/v1/packages?application_code=SATAMU')->assertOk()->json('data'))->pluck('code')->all();

    expect($codes())->toBe([]);

    activateSatamuPackages();

    expect($codes())->toBe(['SAT-SEDERHANA', 'SAT-SPESIAL', 'SAT-ISTIMEWA']);
});

it('activates and deactivates a package with an audit entry', function (): void {
    $action = app(SetPackageAvailability::class);

    expect($action('SAT-SPESIAL', true, 'Harga disetujui'))->toBeTrue()
        ->and(satamuActive())->toBeTrue()
        ->and($action('SAT-SPESIAL', true, 'Lagi'))->toBeFalse()
        ->and($action('SAT-SPESIAL', false, 'Rollback'))->toBeTrue()
        ->and(satamuActive())->toBeFalse();

    $entries = AuditLog::query()->where('entity_type', 'package')->where('entity_id', 'SAT-SPESIAL')->orderBy('id')->get();

    expect($entries->pluck('action')->all())->toBe(['package.activated', 'package.deactivated'])
        ->and($entries->first()->reason)->toBe('Harga disetujui')
        ->and($entries->first()->new_state)->toMatchArray(['is_active' => true, 'price_amount' => 79000]);
});

it('refuses an unknown code or a missing reason', function (string $code, string $reason): void {
    expect(fn () => app(SetPackageAvailability::class)($code, true, $reason))->toThrow(InvalidArgumentException::class)
        ->and(satamuActive())->toBeFalse();
})->with([
    'unknown code' => ['SAT-NOPE', 'Alasan'],
    'blank reason' => ['SAT-SPESIAL', '  '],
]);

it('changes availability from the command line only with a reason and confirmation', function (): void {
    $this->artisan('platform:package-activate SAT-SPESIAL')->assertFailed();
    $this->artisan('platform:package-activate SAT-NOPE --reason=x --force')->assertFailed();
    $this->artisan('platform:package-activate SAT-SPESIAL --reason=Disetujui')
        ->expectsConfirmation('activate paket ini?', 'no')
        ->assertFailed();

    expect(satamuActive())->toBeFalse();

    $this->artisan('platform:package-activate SAT-SPESIAL --reason=Disetujui --force')->assertSuccessful();
    expect(satamuActive())->toBeTrue();

    $this->artisan('platform:package-deactivate SAT-SPESIAL --reason=Rollback --force')->assertSuccessful();
    expect(satamuActive())->toBeFalse();
});

it('still settles an issued attempt after its package is deactivated', function (): void {
    activateSatamuPackages();
    $reference = $this->postJson('/api/v1/orders', satamuOrderPayload(), ['Idempotency-Key' => 'sat-1'])->assertCreated()->json('data.reference');
    $this->postJson("/api/v1/orders/{$reference}/checkout", [], ['Idempotency-Key' => 'sat-checkout-1'])->assertCreated();

    app(SetPackageAvailability::class)('SAT-SPESIAL', false, 'Rollback');

    $payment = Payment::query()->sole();
    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and(WebhookEvent::query()->sole()->status)->toBe(WebhookStatus::Processed);
    $this->getJson("/api/v1/orders/{$reference}/payment-status")->assertOk()->assertJsonPath('data.payment_status', 'PAID');
});
