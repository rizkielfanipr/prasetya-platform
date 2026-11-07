<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Orders\Models\AdminNote;
use App\Domain\Payments\Enums\PaymentStatus;
use Inertia\Testing\AssertableInertia;

/* One access level (ADR-0009): every active user can use every screen and action; guests and inactive users cannot. */

it('lets any active user open every screen', function (string $uri): void {
    $this->actingAs(admin())->get($uri)->assertOk();
})->with(['/dashboard', '/transactions', '/payment-events', '/reconciliation', '/audit-log', '/settings']);

it('renders a missing record as a designed page', function (): void {
    $this->actingAs(admin())->get('/transactions/01jzzzzzzzzzzzzzzzzzzzzzzz')
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('errors/ErrorPage')->where('status', 404));
});

it('shows evidence and audit to any active user', function (): void {
    $payment = pendingPayment();

    $this->actingAs(admin())->get("/transactions/{$payment->order->public_id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('webhooks')->has('audit'));
});

it('shows the gateway and application panels to any active user', function (): void {
    $this->actingAs(admin())->get('/settings')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('admin.gateway')->has('admin.application'));
});

it('lets any active user run an audited gateway check, and refuses guests', function (): void {
    $payment = pendingPayment();

    $this->post("/reconciliation/payments/{$payment->public_id}/check-status")->assertRedirect('/login');
    expect(AuditLog::query()->where('action', 'payment.gateway_checked')->count())->toBe(0);

    $this->actingAs(admin())->post("/reconciliation/payments/{$payment->public_id}/check-status", ['reason' => 'cek manual'])->assertRedirect();

    $log = AuditLog::query()->where('action', 'payment.gateway_checked')->sole();
    expect($log->reason)->toBe('cek manual')->and($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('refuses every action to a deactivated user', function (): void {
    $payment = pendingPayment();
    $user = admin();
    $user->update(['is_active' => false]);

    $this->actingAs($user)->post("/transactions/{$payment->order->public_id}/notes", ['body' => 'Tidak boleh'])->assertRedirect('/login');

    expect(AdminNote::query()->count())->toBe(0);
    $this->assertGuest();
});

it('only moves a payment to paid on verified gateway evidence from a check', function (): void {
    $payment = pendingPayment();
    fakeGateway()->statusResult = gatewayEvidence($payment->amount);

    $this->actingAs(admin())->post("/reconciliation/payments/{$payment->public_id}/check-status")->assertSessionHas('success');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid);
});

it('refuses gateway evidence with the wrong amount from a check', function (): void {
    $payment = pendingPayment();
    fakeGateway()->statusResult = gatewayEvidence($payment->amount - 1);

    $this->actingAs(admin())->post("/reconciliation/payments/{$payment->public_id}/check-status");

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
    $user = admin();

    $this->actingAs($user)->post("/transactions/{$payment->order->public_id}/notes", ['body' => ''])->assertSessionHasErrors('body');
    $this->post("/transactions/{$payment->order->public_id}/notes", ['body' => 'Pelanggan menelepon'])->assertSessionHas('success');

    expect(AdminNote::query()->sole()->author_user_id)->toBe($user->id)
        ->and(AuditLog::query()->where('action', 'order.note_added')->count())->toBe(1);
});
