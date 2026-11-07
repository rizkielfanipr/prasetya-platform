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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/* ADR-0010, phase 2: Transaksi is one workspace with Semua, Perlu Ditinjau, and Event Pembayaran. */

/** @return array<string, mixed> */
function workspace(string $query = ''): array
{
    return test()->actingAs(admin())->get("/transactions{$query}")->assertOk()->viewData('page')['props'];
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
    'no view' => ['', 'all', ['transactions', 'filters', 'packages'], ['issues', 'events', 'counts']],
    'all' => ['?view=all', 'all', ['transactions', 'filters'], ['issues', 'events']],
    'attention' => ['?view=attention', 'attention', ['issues', 'counts', 'group', 'thresholds', 'needsAction'], ['transactions', 'events']],
    'events' => ['?view=events', 'events', ['events', 'filters'], ['transactions', 'issues']],
    'unknown view' => ['?view=../../etc', 'all', ['transactions'], ['issues', 'events']],
    'array view' => ['?view[]=events', 'all', ['transactions'], ['events']],
]);

it('renders the workspace page component', function (): void {
    $this->actingAs(admin())->get('/transactions?view=events')->assertInertia(fn (AssertableInertia $page) => $page->component('transactions/TransactionWorkspacePage'));
});

it('validates each view\'s filters', function (string $query, string $field): void {
    $this->actingAs(admin())->get("/transactions{$query}")->assertSessionHasErrors($field);
})->with([
    'all: payment status' => ['?view=all&payment_status=NOPE', 'payment_status'],
    'attention: group' => ['?view=attention&group=NOPE', 'group'],
    'events: status' => ['?view=events&status=NOPE', 'status'],
    'events: date order' => ['?view=events&date_from=2026-11-07&date_to=2026-11-01', 'date_to'],
]);

it('never applies one view\'s filters to another', function (): void {
    $payment = pendingPayment();
    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount))->assertOk();

    // Transaksi filters on the events view, and an events filter on Semua, are ignored rather than applied.
    $events = workspace('?view=events&payment_status=EXPIRED&brand=SATAMU');
    expect($events['filters'])->not->toHaveKey('payment_status')->not->toHaveKey('brand')
        ->and($events['events']['meta']['total'])->toBe(1);

    $all = workspace('?view=all&status=REJECTED&group=amount_mismatch');
    expect($all['filters'])->not->toHaveKey('status')->not->toHaveKey('group')
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

it('pages, searches, and filters payment events, including unknown invoices', function (): void {
    foreach (range(1, 22) as $n) {
        sendNotification(notificationBody(sprintf('PW-209901-%06d', $n), 1000))->assertOk(); // No order matches these.
    }

    $first = workspace('?view=events');
    expect($first['events']['meta']['total'])->toBe(22)
        ->and($first['events']['data'])->toHaveCount(20)
        ->and($first['events']['data'][0]['orderId'])->toBeNull()
        ->and(workspace('?view=events&page=2')['events']['data'])->toHaveCount(2)
        ->and(workspace('?view=events&search=PW-209901-000007')['events']['meta']['total'])->toBe(1)
        ->and(workspace('?view=events&status=REJECTED')['events']['meta']['total'])->toBe(22)
        ->and(workspace('?view=events&status=PROCESSED')['events']['meta']['total'])->toBe(0);
});

it('offers all nine reconciliation groups and opens the first that needs action', function (): void {
    Order::factory()->create(['status' => OrderStatus::PendingPayment, 'created_at' => now()->subHours(2)]);

    $props = workspace('?view=attention');

    expect(array_keys($props['counts']))->toEqualCanonicalizing(array_map(fn (ReconciliationGroup $g) => $g->value, ReconciliationGroup::cases()))
        ->and($props['group'])->toBe('orders_without_attempt')
        ->and($props['needsAction'])->not->toContain('duplicate_webhooks')->toHaveCount(8)
        ->and(workspace('?view=attention&group=unmatched_payments')['group'])->toBe('unmatched_payments');
});

it('lists an unmatched payment under Perlu Ditinjau without a transaction link', function (): void {
    sendNotification(notificationBody('PW-209901-000001', 1000))->assertOk();

    $issues = workspace('?view=attention&group=unmatched_payments')['issues']['data'];

    expect($issues)->toHaveCount(1)
        ->and($issues[0])->toMatchArray(['invoice' => 'PW-209901-000001', 'orderId' => null, 'checkablePaymentId' => null]);
});

it('counts only issues needing action on the badge, never duplicate deliveries', function (): void {
    $payment = pendingPayment();
    $body = notificationBody($payment->gateway_invoice_number, $payment->amount);
    sendNotification($body)->assertOk();
    sendNotification($body)->assertOk();
    Cache::forget('reconciliation.open-issues');

    $props = workspace('?view=attention&group=duplicate_webhooks');

    expect($props['counts']['duplicate_webhooks'])->toBe(1)
        ->and($props['issues']['meta']['total'])->toBe(1)
        ->and($props['openIssueCount'])->toBe(0);
});

it('keeps the old Event Pembayaran and Rekonsiliasi pages working on the same data', function (): void {
    sendNotification(notificationBody('PW-209901-000001', 1000))->assertOk();

    $this->actingAs(admin())->get('/payment-events?status=REJECTED')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('payments/PaymentEventsPage')->where('events.meta.total', 1)->where('filters.status', 'REJECTED'));
    $this->get('/reconciliation?group=unmatched_payments')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('reconciliation/ReconciliationPage')->where('group', 'unmatched_payments')->where('issues.meta.total', 1));
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
})->with(['all', 'attention', 'events']);
