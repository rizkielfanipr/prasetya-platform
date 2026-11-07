<?php

declare(strict_types=1);

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Models\User;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use Inertia\Testing\AssertableInertia;

/* ADR-0010, phase 3: every audit entry has a documented home. */

function audit(string $action, string $entityType, ?string $entityId, ?User $actor = null, ?string $reason = null): AuditLog
{
    return app(RecordAudit::class)($action, $entityType, $entityId, $actor, null, ['note' => 'test'], $reason);
}

/** @return list<string> */
function actionsOn(string $uri): array
{
    $props = test()->get($uri)->assertOk()->viewData('page')['props'];
    $rows = $props['logs']['data'] ?? $props['activity'];

    return collect($rows)->map(fn (array $row): string => $row['action'] ?? $row['data']['action'] ?? '')->filter()->values()->all();
}

it('shows a user only the audit whose entity is that user', function (): void {
    $admin = admin();
    $sari = User::factory()->create(['username' => 'sari']);
    $budi = User::factory()->create(['username' => 'budi']);
    audit('user.created', 'user', $sari->public_id, $admin);
    audit('auth.login', 'user', $sari->public_id, $sari);
    audit('user.created', 'user', $budi->public_id, $admin);
    audit('user.password_reset', 'user', $budi->public_id, $admin);

    $this->actingAs($admin);
    $activity = $this->get("/users/{$sari->public_id}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('users/UserDetailPage')->where('user.username', 'sari'))->viewData('page')['props']['activity'];

    expect(collect($activity)->pluck('action')->sort()->values()->all())->toBe(['auth.login', 'user.created'])
        ->and(collect($activity)->pluck('entityId')->unique()->all())->toBe([$sari->public_id]);
});

it('never sends a password, hash, or token with a user\'s activity', function (): void {
    $admin = admin();
    $sari = User::factory()->create(['username' => 'sari', 'remember_token' => 'remember-me-token']);
    $this->actingAs($admin)->put("/users/{$sari->public_id}/password", ['password' => 'secret-password-1', 'password_confirmation' => 'secret-password-1'])->assertSessionHas('success');

    $content = $this->get("/users/{$sari->public_id}")->assertOk()->getContent();

    expect($content)->toContain('user.password_reset')
        ->not->toContain('secret-password-1')
        ->not->toContain($sari->refresh()->password)
        ->not->toContain('remember-me-token');
});

it('addresses user details by public id and keeps them behind sign-in', function (): void {
    $user = User::factory()->create();

    $this->get("/users/{$user->public_id}")->assertRedirect('/login');
    $this->actingAs(admin())->get("/users/{$user->id}")->assertNotFound();
});

it('lists system activity and leaves out transactions and user management', function (): void {
    $admin = admin();
    $order = Order::factory()->create();
    audit('auth.login', 'user', $admin->public_id, $admin);
    audit('user.profile_updated', 'user', $admin->public_id, $admin);
    audit('package.activated', 'package', 'SAT-SPESIAL', null, 'Harga disetujui');
    audit('order.note_added', 'order', $order->public_id, $admin);
    audit('payment.gateway_checked', 'payment', '01payment', $admin);
    audit('user.created', 'user', $admin->public_id, $admin);

    $this->actingAs($admin);

    expect(actionsOn('/settings/activity'))->toEqualCanonicalizing(['auth.login', 'user.profile_updated', 'package.activated'])
        ->and(actionsOn('/settings/activity?scope=all'))->toContain('order.note_added', 'payment.gateway_checked', 'user.created', 'auth.login')
        ->and(actionsOn('/settings/activity?search=package'))->toBe(['package.activated'])
        ->and(actionsOn('/settings/activity?scope=all&search=SAT-SPESIAL'))->toBe(['package.activated']);
});

it('filters system activity by date and validates its filters', function (): void {
    $admin = admin();
    $this->travelTo(now()->subDays(10));
    audit('auth.login', 'user', $admin->public_id, $admin);
    $this->travelBack();
    audit('auth.logout', 'user', $admin->public_id, $admin);

    $this->actingAs($admin);
    $today = now()->setTimezone((string) config('platform.business_timezone'))->toDateString();

    expect(actionsOn("/settings/activity?date_from={$today}"))->toBe(['auth.logout']);
    $this->get('/settings/activity?scope=everything')->assertSessionHasErrors('scope');
    $this->get('/settings/activity?date_from=2026-11-07&date_to=2026-11-01')->assertSessionHasErrors('date_to');
});

it('never shows a secret in system activity', function (): void {
    $admin = admin();
    $this->actingAs($admin)->put('/settings/password', ['current_password' => 'password-for-tests-1', 'password' => 'another-password-1', 'password_confirmation' => 'another-password-1'])->assertSessionHasNoErrors();

    $content = $this->get('/settings/activity')->assertOk()->getContent();

    expect($content)->toContain('user.password_changed')->not->toContain('another-password-1')->not->toContain($admin->refresh()->password);
});

it('finds every kind of audit the platform writes in at least one documented place', function (): void {
    $admin = admin();
    $sari = User::factory()->create();
    $order = Order::factory()->create();
    $payment = Payment::factory()->create(['order_id' => $order->id]);
    contentSetUp();
    $item = draftItem($admin, complete: false);
    $content = array_map(fn (string $action): array => [$action, 'content_item', $item->public_id], ['content.created', 'content.revised', 'content.details_updated', 'content.submitted', 'content.revision_requested', 'content.approved', 'content.rejected', 'content.published']);

    $brand = webworks();
    $brandAudit = array_map(fn (string $action): array => [$action, 'content_brand', $brand->public_id], ['content.brand_rules_updated', 'content.pillar_created', 'content.pillar_updated', 'content.campaign_created', 'content.campaign_updated', 'content.fact_added', 'content.fact_retired']);

    $placed = [
        "/content/items/{$item->public_id}" => $content,
        "/content/brands/{$brand->public_id}?tab=activity" => $brandAudit,
        "/transactions/{$order->public_id}" => [['order.note_added', 'order', $order->public_id], ['payment.paid', 'payment', $payment->public_id], ['payment.gateway_checked', 'payment', $payment->public_id]],
        "/users/{$sari->public_id}" => [['user.created', 'user', $sari->public_id], ['user.updated', 'user', $sari->public_id], ['user.activated', 'user', $sari->public_id], ['user.deactivated', 'user', $sari->public_id], ['user.password_reset', 'user', $sari->public_id]],
        '/settings/activity' => [['auth.login', 'user', $admin->public_id], ['auth.logout', 'user', $admin->public_id], ['user.profile_updated', 'user', $admin->public_id], ['user.password_changed', 'user', $admin->public_id], ['package.activated', 'package', 'SAT-SPESIAL'], ['package.deactivated', 'package', 'SAT-SPESIAL'], ['future.unknown', 'something', null]],
    ];
    foreach ($placed as $entries) {
        foreach ($entries as [$action, $type, $id]) {
            audit($action, $type, $id, $admin);
        }
    }

    $this->actingAs($admin);
    foreach ($placed as $uri => $entries) {
        expect(actionsOn($uri))->toContain(...array_column($entries, 0));
    }
});

it('redirects the old Audit Log route to all system activity', function (): void {
    $this->actingAs(admin())->get('/audit-log?search=payment.paid&page=2')
        ->assertRedirect('/settings/activity?scope=all&search=payment.paid&page=2');
});
