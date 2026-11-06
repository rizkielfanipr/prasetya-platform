<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Brands\Enums\ApplicationCode;
use App\Domain\Brands\Models\Application;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Queries\DashboardMetrics;
use App\Domain\Payments\Queries\ReconciliationReport;
use App\Support\DateRange;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

function satamuId(): int
{
    return (int) Application::query()->where('code', ApplicationCode::Satamu)->value('id');
}

function paidOrder(int $amount, ?int $applicationId = null, ?Carbon $paidAt = null): Payment
{
    $order = Order::factory()->create(array_filter(['total_amount' => $amount, 'status' => OrderStatus::Confirmed, 'application_id' => $applicationId]));

    return Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Paid, 'paid_at' => $paidAt ?? now(), 'amount' => $amount]);
}

function reconciliationCounts(): array
{
    return app(ReconciliationReport::class)->counts();
}

it('aggregates the dashboard in SQL per brand and period', function (): void {
    paidOrder(100000);
    paidOrder(200000, satamuId());
    paidOrder(999000, null, now()->subDays(40));
    pendingPayment();

    $range = DateRange::lastDays(30);
    $metrics = app(DashboardMetrics::class);
    $summary = $metrics->summary($range, null);

    expect($summary['totalPaid']['value'])->toBe(300000)
        ->and($summary['totalPaid']['previous'])->toBe(999000)
        ->and($summary['successful']['value'])->toBe(2)
        ->and($summary['pending']['value'])->toBe(1)
        ->and($metrics->summary($range, 'SATAMU')['totalPaid']['value'])->toBe(200000);
});

it('fills revenue gaps and groups by business day', function (): void {
    Carbon::setTestNow('2026-11-10 12:00:00');
    // 18:00 UTC on the 8th is 01:00 on the 9th in Jakarta.
    paidOrder(100000, null, Carbon::parse('2026-11-08 18:00:00', 'UTC'));

    $series = app(DashboardMetrics::class)->revenueSeries(DateRange::lastDays(3), null);
    Carbon::setTestNow();

    expect(array_column($series, 'date'))->toBe(['2026-11-08', '2026-11-09', '2026-11-10'])
        ->and($series[1]['WEBWORKS'])->toBe(100000)
        ->and($series[0]['WEBWORKS'])->toBe(0)
        ->and($series[1]['SATAMU'])->toBe(0);
});

it('renders the dashboard with a deferred revenue prop', function (): void {
    $this->actingAs(admin())->get('/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('dashboard/DashboardPage')
        ->has('summary.totalPaid.value')
        ->has('brands', 2)
        ->missing('revenue'));
});

it('filters, searches, sorts, and paginates transactions on the server', function (): void {
    Order::factory()->count(17)->create();
    Order::factory()->create(['customer_name' => 'Pelanggan Unik', 'invoice_number' => 'SAT-202611-000777', 'application_id' => satamuId(), 'total_amount' => 7]);

    $this->actingAs(admin())->get('/transactions')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('transactions.data', 15)->where('transactions.meta.total', 18)->where('transactions.meta.lastPage', 2));

    $this->get('/transactions?search=Unik')->assertInertia(fn (AssertableInertia $page) => $page->has('transactions.data', 1)->where('transactions.data.0.invoice', 'SAT-202611-000777'));
    $this->get('/transactions?search=SAT-202611')->assertInertia(fn (AssertableInertia $page) => $page->has('transactions.data', 1));
    $this->get('/transactions?brand=SATAMU')->assertInertia(fn (AssertableInertia $page) => $page->where('transactions.meta.total', 1)->where('transactions.data.0.brand', 'SATAMU'));
    $this->get('/transactions?order_status=CONFIRMED')->assertInertia(fn (AssertableInertia $page) => $page->where('transactions.meta.total', 0));
    $this->get('/transactions?sort=total_amount&direction=asc')->assertInertia(fn (AssertableInertia $page) => $page->where('transactions.data.0.amount', 7));
    $this->get('/transactions?page=2')->assertInertia(fn (AssertableInertia $page) => $page->has('transactions.data', 3));
});

it('treats LIKE wildcards in search literally', function (): void {
    Order::factory()->count(3)->create();

    $this->actingAs(admin())->get('/transactions?search=%25')->assertInertia(fn (AssertableInertia $page) => $page->where('transactions.meta.total', 0));
});

it('filters by date range in the business timezone', function (): void {
    Order::factory()->create()->forceFill(['created_at' => '2026-10-01 18:00:00'])->save(); // 2 Oct 01:00 WIB.

    $this->actingAs(admin())->get('/transactions?date_from=2026-10-02&date_to=2026-10-02')->assertInertia(fn (AssertableInertia $page) => $page->where('transactions.meta.total', 1));
    $this->get('/transactions?date_from=2026-10-01&date_to=2026-10-01')->assertInertia(fn (AssertableInertia $page) => $page->where('transactions.meta.total', 0));
});

it('validates filter input', function (): void {
    $this->actingAs(admin())->get('/transactions?brand=NOPE&sort=password')->assertSessionHasErrors(['brand', 'sort']);
});

it('filters by latest payment status', function (): void {
    pendingPayment();
    paidOrder(1000);

    $this->actingAs(admin())->get('/transactions?payment_status=PAID')->assertInertia(fn (AssertableInertia $page) => $page->where('transactions.meta.total', 1));
});

it('shows a complete sanitised transaction detail', function (): void {
    $payment = pendingPayment();
    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount, 'SUCCESS', ['maskedNumber' => '411111******1111']))->assertOk();

    $response = $this->actingAs(admin())->get("/transactions/{$payment->order->public_id}")->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('transactions/TransactionDetailPage')
        ->where('transaction.paymentStatus', 'PAID')
        ->where('transaction.orderStatus', 'CONFIRMED')
        ->has('transaction.payments.0.events')
        ->has('transaction.statusHistory')
        ->has('webhooks', 1)
        ->has('audit', 1));

    expect($response->getContent())->not->toContain('411111')->not->toContain('fake-key-not-real');
});

it('returns 404 for numeric ids: orders are addressed by public id', function (): void {
    $order = Order::factory()->create();

    $this->actingAs(admin())->get("/transactions/{$order->id}")->assertNotFound();
});

it('lists payment events with verification and duplicate state', function (): void {
    $payment = pendingPayment();
    $body = notificationBody($payment->gateway_invoice_number, $payment->amount);
    sendNotification($body);
    sendNotification($body);
    sendNotification($body, secret: 'bad');

    $this->actingAs(admin())->get('/payment-events')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('payments/PaymentEventsPage')
        ->where('events.meta.total', 3)
        ->where('events.data.0.signatureValid', false)
        ->where('events.data.0.diagnostics', null)
        ->where('events.data.1.isDuplicate', true));

    $this->get('/payment-events?status=DUPLICATE')->assertInertia(fn (AssertableInertia $page) => $page->where('events.meta.total', 1));
    $this->get("/payment-events?search={$payment->gateway_invoice_number}")->assertInertia(fn (AssertableInertia $page) => $page->where('events.meta.total', 2));
});

it('finds orders with no attempt only after the threshold', function (): void {
    $order = Order::factory()->create();
    expect(reconciliationCounts()['orders_without_attempt'])->toBe(0);

    $order->forceFill(['created_at' => now()->subHour()])->save();
    expect(reconciliationCounts()['orders_without_attempt'])->toBe(1);
});

it('finds orders without success and payments pending too long', function (): void {
    $payment = pendingPayment();
    expect(reconciliationCounts()['pending_too_long'])->toBe(0);

    $payment->forceFill(['created_at' => now()->subHours(5)])->save();
    $payment->order->forceFill(['created_at' => now()->subHours(5)])->save();

    expect(reconciliationCounts()['pending_too_long'])->toBe(1)->and(reconciliationCounts()['orders_without_success'])->toBe(1);
});

it('classifies webhook problems into their own groups', function (): void {
    $payment = pendingPayment();
    sendNotification(notificationBody('PW-000000-000404', 1000));
    sendNotification(notificationBody($payment->gateway_invoice_number, 1));
    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount), secret: 'bad');
    $good = notificationBody($payment->gateway_invoice_number, $payment->amount);
    sendNotification($good);
    sendNotification($good);
    $cancelled = pendingPayment([], ['status' => PaymentStatus::Cancelled]);
    sendNotification(notificationBody($cancelled->gateway_invoice_number, $cancelled->amount));

    expect(reconciliationCounts())->toMatchArray([
        'unmatched_payments' => 1,
        'amount_mismatch' => 1,
        'rejected_webhooks' => 1,
        'duplicate_webhooks' => 1,
        'invalid_transitions' => 1,
    ]);
});

it('finds inconsistent paid states, one row per order', function (): void {
    $cancelled = pendingPayment(['status' => OrderStatus::Cancelled]);
    sendNotification(notificationBody($cancelled->gateway_invoice_number, $cancelled->amount));
    Order::factory()->create(['status' => OrderStatus::Confirmed]);

    $twice = paidOrder(1000);
    Payment::factory()->create(['order_id' => $twice->order_id, 'attempt_number' => 2, 'status' => PaymentStatus::Paid, 'paid_at' => now(), 'gateway_invoice_number' => 'PW-X-2', 'amount' => 1000]);

    expect(reconciliationCounts()['paid_inconsistent'])->toBe(3);
});

it('never changes data when viewed', function (): void {
    $cancelled = pendingPayment(['status' => OrderStatus::Cancelled]);
    sendNotification(notificationBody($cancelled->gateway_invoice_number, $cancelled->amount));
    $auditCount = AuditLog::query()->count();

    $this->actingAs(admin())->get('/reconciliation?group=paid_inconsistent')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('reconciliation/ReconciliationPage')
        ->where('group', 'paid_inconsistent')
        ->has('issues.data', 1));

    expect($cancelled->order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

it('never exposes a secret on the settings page', function (): void {
    config(['duitku.api_key' => 'never-show-this-value']);

    $content = $this->actingAs(admin())->get('/settings')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('admin.gateway.configured')->missing('admin.gateway.secret'))
        ->getContent();

    expect($content)->not->toContain('never-show-this-value')->not->toContain('fake-key-not-real');
});

it('lists audit entries with search', function (): void {
    $payment = pendingPayment();
    sendNotification(notificationBody($payment->gateway_invoice_number, $payment->amount));

    $this->actingAs(admin())->get('/audit-log?search=payment.paid')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('audit/AuditLogPage')
        ->where('logs.meta.total', 1)
        ->where('logs.data.0.actor', 'Sistem')
        ->where('logs.data.0.newState.payment_status', 'PAID'));
});
