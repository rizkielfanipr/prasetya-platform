<?php

declare(strict_types=1);

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\TransitionSource;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentEventType;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\ReconciliationGroup;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use App\Http\Workspace\AttentionQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/* ADR-0010 and ADR-0016: Transaksi is one workspace with Semua and Perlu Ditinjau; the webhook log is Pengaturan → Log Gateway. */

/** @return array<string, mixed> */
function workspace(string $query = ''): array
{
    return test()->actingAs(admin())->get("/transactions{$query}")->assertOk()->viewData('page')['props'];
}

/** @return array<string, mixed> */
function gatewayLog(string $query = ''): array
{
    return test()->actingAs(admin())->get("/settings/gateway-log{$query}")->assertOk()->viewData('page')['props'];
}

it('renders each view with its own props', function (string $query, string $view, array $present, array $absent): void {
    $props = workspace($query);

    expect($props['view'])->toBe($view);
    foreach ($present as $key) {
        expect($props)->toHaveKey($key);
    }
    foreach ($absent as $key) {
        expect($props)->not->toHaveKey($key);
    }
})->with([
    'no view' => ['', 'all', ['transactions', 'filters', 'packages'], ['sections', 'events', 'counts']],
    'all' => ['?view=all', 'all', ['transactions', 'filters'], ['sections', 'events']],
    'attention' => ['?view=attention', 'attention', ['sections', 'counts', 'queue', 'queues', 'thresholds'], ['transactions', 'events']],
    'unknown view' => ['?view=../../etc', 'all', ['transactions'], ['sections', 'events']],
    'array view' => ['?view[]=events', 'all', ['transactions'], ['events']],
]);

it('renders the workspace and the Log Gateway page components', function (): void {
    $this->actingAs(admin())->get('/transactions?view=attention')->assertInertia(fn (AssertableInertia $page) => $page->component('transactions/TransactionWorkspacePage'));
    $this->get('/settings/gateway-log')->assertInertia(fn (AssertableInertia $page) => $page->component('settings/GatewayLogPage'));
});

it('sends the retired events view to the Log Gateway with its filters', function (): void {
    $this->actingAs(admin())
        ->get('/transactions?view=events&search=PW-1&status=REJECTED&brand=ignored')
        ->assertRedirect('/settings/gateway-log?search=PW-1&status=REJECTED');
});

it('validates each view\'s filters', function (string $query, string $field): void {
    $this->actingAs(admin())->get("/transactions{$query}")->assertSessionHasErrors($field);
})->with([
    'all: payment status' => ['?view=all&payment_status=NOPE', 'payment_status'],
    'attention: queue' => ['?view=attention&queue=NOPE', 'queue'],
]);

it('validates the Log Gateway filters', function (string $query, string $field): void {
    $this->actingAs(admin())->get("/settings/gateway-log{$query}")->assertSessionHasErrors($field);
})->with([
    'status' => ['?status=NOPE', 'status'],
    'date order' => ['?date_from=2026-11-07&date_to=2026-11-01', 'date_to'],
]);

it('never applies one view\'s filters to another', function (): void {
    $payment = pendingPayment();
    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount))->assertOk();

    // Transaksi filters on the Log Gateway, and a log filter on Semua, are ignored rather than applied.
    $events = gatewayLog('?payment_status=EXPIRED&brand=SATAMU');
    expect($events['filters'])->not->toHaveKey('payment_status')->not->toHaveKey('brand')
        ->and($events['events']['meta']['total'])->toBe(1);

    $all = workspace('?view=all&status=REJECTED&queue=unmatched');
    expect($all['filters'])->not->toHaveKey('status')->not->toHaveKey('queue')
        ->and($all['transactions']['meta']['total'])->toBe(1);
});

it('keeps Semua sorting, search, and pagination', function (): void {
    Order::factory()->count(17)->create();
    Order::factory()->create(['customer_name' => 'Pelanggan Unik', 'invoice_number' => 'PW-202611-000999', 'total_amount' => 7]);

    expect(workspace('?view=all')['transactions']['meta']['total'])->toBe(18)
        ->and(workspace('?view=all&page=2')['transactions']['data'])->toHaveCount(3)
        ->and(workspace('?view=all&search=Unik')['transactions']['data'][0]['invoice'])->toBe('PW-202611-000999')
        ->and(workspace('?view=all&sort=total_amount&direction=asc')['transactions']['data'][0]['amount'])->toBe(7);
});

it('pages, searches, and filters the Log Gateway, including unknown invoices', function (): void {
    foreach (range(1, 22) as $n) {
        sendNotification(notificationBody(sprintf('PW-209901-%06d', $n), 1000))->assertOk(); // No order matches these.
    }

    $first = gatewayLog();
    expect($first['events']['meta']['total'])->toBe(22)
        ->and($first['events']['data'])->toHaveCount(20)
        ->and($first['events']['data'][0]['orderId'])->toBeNull()
        ->and(gatewayLog('?page=2')['events']['data'])->toHaveCount(2)
        ->and(gatewayLog('?search=PW-209901-000007')['events']['meta']['total'])->toBe(1)
        ->and(gatewayLog('?status=REJECTED')['events']['meta']['total'])->toBe(22)
        ->and(gatewayLog('?status=PROCESSED')['events']['meta']['total'])->toBe(0);
});

it('maps each review queue to its fixed reconciliation groups', function (): void {
    $groups = fn (AttentionQueue $queue): array => array_map(fn (ReconciliationGroup $g): string => $g->value, $queue->groups());

    expect($groups(AttentionQueue::Unmatched))->toBe(['unmatched_payments'])
        ->and($groups(AttentionQueue::AmountMismatch))->toBe(['amount_mismatch'])
        ->and($groups(AttentionQueue::Waiting))->toBe(['orders_without_success'])
        ->and($groups(AttentionQueue::System))->toBe(['paid_inconsistent', 'invalid_transitions', 'rejected_webhooks']);

    // Exactly the groups that need action are queued; the informational three live elsewhere.
    $queued = array_merge(...array_map($groups, AttentionQueue::cases()));
    $needing = array_map(fn (ReconciliationGroup $g): string => $g->value, array_filter(ReconciliationGroup::cases(), fn (ReconciliationGroup $g): bool => $g->needsAction()));
    expect($queued)->toEqualCanonicalizing(array_values($needing))
        ->and(array_values(array_diff(array_map(fn (ReconciliationGroup $g): string => $g->value, ReconciliationGroup::cases()), $queued)))
        ->toEqualCanonicalizing(['orders_without_attempt', 'pending_too_long', 'duplicate_webhooks']);
});

it('offers the four queues, hides an empty system queue, and opens the first with items', function (): void {
    Order::factory()->create(['status' => OrderStatus::PendingPayment, 'created_at' => now()->subHours(2)]); // No attempt: information only.
    $waiting = pendingPayment();
    $waiting->forceFill(['created_at' => now()->subHours(3)])->save();
    $waiting->order->forceFill(['created_at' => now()->subHours(3)])->save();

    $props = workspace('?view=attention');

    expect($props['queues'])->toBe(['unmatched', 'amount_mismatch', 'waiting'])
        ->and($props['counts'])->toBe(['unmatched' => 0, 'amount_mismatch' => 0, 'waiting' => 1, 'system' => 0])
        ->and($props['queue'])->toBe('waiting')
        ->and($props['sections'])->toHaveCount(1)
        ->and($props['sections'][0]['group'])->toBe('orders_without_success')
        ->and(workspace('?view=attention&queue=system')['queues'])->toContain('system');
});

it('shows each non-empty group of the system queue as its own paginated section', function (): void {
    sendNotification(notificationBody('PW-209901-000001', 1000), secret: 'wrong-key'); // Fails verification: a rejected webhook.

    $props = workspace('?view=attention&queue=system');

    expect($props['queues'])->toContain('system')
        ->and($props['sections'])->toHaveCount(1)
        ->and($props['sections'][0])->toMatchArray(['group' => 'rejected_webhooks', 'pageName' => 'page_rejected_webhooks'])
        ->and($props['sections'][0]['issues']['meta']['total'])->toBe(1);
});

it('lists an unmatched payment under Perlu Ditinjau without a transaction link', function (): void {
    sendNotification(notificationBody('PW-209901-000001', 1000))->assertOk();

    $issues = workspace('?view=attention&queue=unmatched')['sections'][0]['issues']['data'];

    expect($issues)->toHaveCount(1)
        ->and($issues[0])->toMatchArray(['invoice' => 'PW-209901-000001', 'orderId' => null, 'checkablePaymentId' => null]);
});

it('counts only issues needing action on the badge: no duplicates, no abandoned checkouts, no double count', function (): void {
    $payment = pendingPayment();
    $body = notificationBody($payment->gateway_invoice_number, $payment->amount);
    sendNotification($body)->assertOk();
    sendNotification($body)->assertOk(); // A duplicate delivery.
    Order::factory()->create(['status' => OrderStatus::PendingPayment, 'created_at' => now()->subHours(2)]); // No attempt.
    $waiting = pendingPayment(); // Open past the threshold: one waiting order, not also a pending-too-long payment.
    $waiting->forceFill(['created_at' => now()->subHours(3)])->save();
    $waiting->order->forceFill(['created_at' => now()->subHours(3)])->save();
    Cache::forget('reconciliation.open-issues');

    $props = workspace('?view=attention');

    expect($props['openIssueCount'])->toBe(1)
        ->and(array_sum($props['counts']))->toBe(1);
});

it('lists orders without an attempt in Semua with the No Payment filter', function (): void {
    Order::factory()->create(['invoice_number' => 'PW-202611-000501']);
    pendingPayment();

    $rows = workspace('?view=all&payment_status=NONE')['transactions']['data'];

    expect($rows)->toHaveCount(1)->and($rows[0]['invoice'])->toBe('PW-202611-000501');
});

it('redirects legacy workspace routes while preserving compatible filters', function (): void {
    $this->actingAs(admin())
        ->get('/payment-events?search=SAT-1&status=REJECTED&date_from=2026-11-01&date_to=2026-11-07&page=2&group=ignored')
        ->assertRedirect('/settings/gateway-log?search=SAT-1&status=REJECTED&date_from=2026-11-01&date_to=2026-11-07&page=2');

    // Old group links land on the queue that holds the group, or on the new home of an informational group.
    $this->get('/reconciliation?group=unmatched_payments&page=3')->assertRedirect('/transactions?view=attention&queue=unmatched');
    $this->get('/reconciliation?group=rejected_webhooks')->assertRedirect('/transactions?view=attention&queue=system');
    $this->get('/reconciliation?group=pending_too_long')->assertRedirect('/transactions?view=attention&queue=waiting');
    $this->get('/reconciliation?group=orders_without_attempt')->assertRedirect('/transactions?payment_status=NONE');
    $this->get('/reconciliation?group=duplicate_webhooks')->assertRedirect('/settings/gateway-log?status=DUPLICATE');
    $this->get('/transactions?view=attention&group=amount_mismatch')->assertRedirect('/transactions?view=attention&queue=amount_mismatch');
    $this->get('/reconciliation')->assertRedirect('/transactions?view=attention');

    $this->get('/audit-log?search=payment.paid&date_from=2026-11-01&date_to=2026-11-07&page=4&status=ignored')
        ->assertRedirect('/settings/activity?scope=all&search=payment.paid&date_from=2026-11-01&date_to=2026-11-07&page=4');
});

/** Queries for one GET, after a warm-up request so caches and lazy boot do not count. */
function queryCount(string $uri): int
{
    $user = admin();
    test()->actingAs($user)->get($uri)->assertOk();
    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });
    test()->get($uri)->assertOk();

    return $count;
}

function orderWithAttempts(int $attempts): Order
{
    $order = Order::factory()->create();
    foreach (range(1, $attempts) as $n) {
        $payment = Payment::factory()->create(['order_id' => $order->id, 'attempt_number' => $n, 'gateway_invoice_number' => "{$order->invoice_number}-{$n}", 'status' => PaymentStatus::Failed]);
        foreach (range(1, 3) as $ignored) {
            PaymentEvent::query()->create(['payment_id' => $payment->id, 'type' => PaymentEventType::StatusChanged, 'source' => TransitionSource::Api, 'occurred_at' => now()]);
        }
        sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount, 'FAILED'))->assertOk();
    }

    return $order;
}

it('loads transaction detail with a fixed number of queries, however many attempts and events', function (): void {
    $small = orderWithAttempts(2);
    $large = orderWithAttempts(6);

    expect(queryCount("/transactions/{$large->public_id}"))->toBe(queryCount("/transactions/{$small->public_id}"));
});

it('loads each workspace view with a fixed number of queries, however many rows', function (string $view): void {
    orderWithAttempts(2);
    $few = queryCount("/transactions?view={$view}");
    foreach (range(1, 4) as $ignored) {
        orderWithAttempts(2);
    }

    expect(queryCount("/transactions?view={$view}"))->toBe($few);
})->with(['all', 'attention']);
