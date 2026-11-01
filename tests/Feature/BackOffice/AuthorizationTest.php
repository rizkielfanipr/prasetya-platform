<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Enums\Role;
use App\Domain\Orders\Models\AdminNote;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Integrations\Doku\DokuPayload;
use Inertia\Testing\AssertableInertia;

it('applies the role matrix to pages', function (Role $role, string $uri, int $status): void {
    $this->actingAs(admin($role))->get($uri)->assertStatus($status);
})->with([
    [Role::SuperAdmin, '/dashboard', 200], [Role::SuperAdmin, '/transactions', 200], [Role::SuperAdmin, '/payment-events', 200],
    [Role::SuperAdmin, '/reconciliation', 200], [Role::SuperAdmin, '/audit-log', 200], [Role::SuperAdmin, '/settings', 200],
    [Role::Finance, '/dashboard', 200], [Role::Finance, '/transactions', 200], [Role::Finance, '/payment-events', 200],
    [Role::Finance, '/reconciliation', 200], [Role::Finance, '/audit-log', 200], [Role::Finance, '/settings', 200],
    [Role::Operations, '/dashboard', 200], [Role::Operations, '/transactions', 200], [Role::Operations, '/payment-events', 403],
    [Role::Operations, '/reconciliation', 403], [Role::Operations, '/audit-log', 403], [Role::Operations, '/settings', 200],
]);

it('renders permission denied as a designed page', function (): void {
    $this->actingAs(admin(Role::Operations))->get('/audit-log')
        ->assertForbidden()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('errors/ErrorPage')->where('status', 403));
});

it('withholds evidence and audit data from operations on the server', function (): void {
    $payment = pendingPayment();

    $this->actingAs(admin(Role::Operations))->get("/transactions/{$payment->order->public_id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('webhooks', null)->where('audit', null));

    $this->actingAs(admin(Role::Finance))->get("/transactions/{$payment->order->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->has('webhooks')->has('audit'));
});

it('withholds admin settings from non super admins on the server', function (): void {
    $this->actingAs(admin(Role::Finance))->get('/settings')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('admin', null));
});

it('refuses a gateway check from operations and audits one from finance', function (): void {
    $payment = pendingPayment();

    $this->actingAs(admin(Role::Operations))->post("/reconciliation/payments/{$payment->public_id}/check-status")->assertForbidden();
    expect(AuditLog::query()->where('action', 'payment.gateway_checked')->count())->toBe(0);

    $this->actingAs(admin(Role::Finance))->post("/reconciliation/payments/{$payment->public_id}/check-status", ['reason' => 'cek manual'])->assertRedirect();

    $log = AuditLog::query()->where('action', 'payment.gateway_checked')->sole();
    expect($log->reason)->toBe('cek manual')->and($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('only moves a payment to paid on verified gateway evidence from a check', function (): void {
    $payment = pendingPayment();
    fakeGateway()->statusResult = DokuPayload::evidence(notificationBody($payment->gateway_invoice_number, $payment->amount));

    $this->actingAs(admin(Role::Finance))->post("/reconciliation/payments/{$payment->public_id}/check-status")->assertSessionHas('success');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid);
});

it('refuses gateway evidence with the wrong amount from a check', function (): void {
    $payment = pendingPayment();
    fakeGateway()->statusResult = DokuPayload::evidence(notificationBody($payment->gateway_invoice_number, $payment->amount - 1));

    $this->actingAs(admin(Role::Finance))->post("/reconciliation/payments/{$payment->public_id}/check-status");

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('has no route that sets a payment status directly', function (): void {
    $uris = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [])
        ->map(fn ($route) => $route->uri());

    expect($uris->filter(fn (string $uri) => preg_match('/mark|paid|status$/', $uri) === 1 && ! str_ends_with($uri, 'check-status'))->values()->all())->toBe([]);
});

it('adds an audited note', function (): void {
    $payment = pendingPayment();
    $user = admin(Role::Operations);

    $this->actingAs($user)->post("/transactions/{$payment->order->public_id}/notes", ['body' => ''])->assertSessionHasErrors('body');
    $this->post("/transactions/{$payment->order->public_id}/notes", ['body' => 'Pelanggan menelepon'])->assertSessionHas('success');

    expect(AdminNote::query()->sole()->author_user_id)->toBe($user->id)
        ->and(AuditLog::query()->where('action', 'order.note_added')->count())->toBe(1);
});
