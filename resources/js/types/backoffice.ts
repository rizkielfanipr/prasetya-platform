// Mirrors App\Http\Presenters and the Inertia controllers. Backend is authoritative for every value here.

export type Brand = 'WEBWORKS' | 'SATAMU';
export type PaymentStatus = 'CREATED' | 'PENDING' | 'PAID' | 'FAILED' | 'EXPIRED' | 'CANCELLED';
export type OrderStatus = 'DRAFT' | 'PENDING_PAYMENT' | 'CONFIRMED' | 'CANCELLED' | 'EXPIRED';
export type WebhookStatus = 'RECEIVED' | 'VERIFIED' | 'PROCESSED' | 'IGNORED' | 'DUPLICATE' | 'REJECTED' | 'FAILED';
export type Role = 'SUPER_ADMIN' | 'FINANCE' | 'OPERATIONS';
export type Permission =
    | 'transactions.view'
    | 'orders.notes.add'
    | 'payment-events.view'
    | 'reconciliation.view'
    | 'reconciliation.gateway-check'
    | 'audit-log.view'
    | 'settings.manage';

export type ReconciliationGroup =
    | 'orders_without_attempt'
    | 'orders_without_success'
    | 'unmatched_payments'
    | 'amount_mismatch'
    | 'rejected_webhooks'
    | 'duplicate_webhooks'
    | 'pending_too_long'
    | 'paid_inconsistent'
    | 'invalid_transitions';

export interface PaginationMeta {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface Paginated<T> {
    data: T[];
    meta: PaginationMeta;
}

export interface AuthUser {
    id: string;
    name: string;
    email: string;
    role: Role;
}

export interface SharedProps {
    auth: { user: AuthUser | null; permissions: Permission[] };
    flash: { success: string | null; error: string | null };
    openIssueCount: number | null;
    requestId: string;
    [key: string]: unknown;
}

export interface TransactionRow {
    id: string;
    invoice: string;
    brand: Brand;
    customerName: string;
    packageName: string | null;
    amount: number;
    paymentStatus: PaymentStatus | null;
    orderStatus: OrderStatus;
    paymentMethod: string | null;
    createdAt: string;
    lastPaymentUpdate: string | null;
}

export interface PaymentEventEntry {
    id: number;
    type: string;
    source: string;
    from: PaymentStatus | null;
    to: PaymentStatus | null;
    at: string;
    detail: string | null;
}

export interface PaymentAttempt {
    id: string;
    attempt: number;
    provider: string;
    status: PaymentStatus;
    amount: number;
    gatewayInvoice: string;
    gatewayReference: string | null;
    paymentMethod: string | null;
    failureCode: string | null;
    expiresAt: string | null;
    paidAt: string | null;
    createdAt: string;
    events: PaymentEventEntry[];
}

export interface OrderHistoryEntry {
    id: number;
    from: OrderStatus | null;
    to: OrderStatus;
    source: string;
    actor: string | null;
    reason: string | null;
    at: string;
}

export interface AdminNote {
    id: number;
    body: string;
    author: string;
    at: string;
}

export interface TransactionDetail extends TransactionRow {
    expiresAt: string | null;
    confirmedAt: string | null;
    customer: { name: string; email: string; phone: string | null };
    items: { packageCode: string; packageName: string; quantity: number; unitAmount: number; lineAmount: number }[];
    brandDetails: { label: string; value: string }[];
    payments: PaymentAttempt[];
    statusHistory: OrderHistoryEntry[];
    notes: AdminNote[];
}

export interface WebhookEventRow {
    id: string;
    provider: string;
    invoice: string | null;
    orderId: string | null;
    eventType: string | null;
    channel: string | null;
    amount: number | null;
    signatureValid: boolean;
    status: WebhookStatus;
    isDuplicate: boolean;
    rejectionReason: string | null;
    requestId: string | null;
    receivedAt: string;
    processedAt: string | null;
    diagnostics: Record<string, Record<string, string | number | null>> | null;
}

export type JsonState = Record<string, string | number | boolean | null>;

export interface AuditEntry {
    id: number;
    actor: string;
    action: string;
    entityType: string;
    entityId: string | null;
    previousState: JsonState | null;
    newState: JsonState | null;
    reason: string | null;
    requestId: string | null;
    at: string;
}

export interface MetricPair {
    value: number;
    previous: number;
}

export interface DashboardSummary {
    totalPaid: MetricPair;
    successful: MetricPair;
    pending: MetricPair;
    failedOrExpired: MetricPair;
    activeOrders: MetricPair;
}

export interface BrandSummary {
    brand: Brand;
    paidAmount: number;
    paidCount: number;
    pendingCount: number;
    activeOrders: number;
}

export interface RevenuePoint {
    date: string;
    WEBWORKS: number;
    SATAMU: number;
}

export interface DashboardFilters {
    brand: Brand | null;
    period: string;
    date_from: string | null;
    date_to: string | null;
    payment_status: PaymentStatus | null;
}

export interface TransactionFilters {
    search: string | null;
    brand: Brand | null;
    payment_status: PaymentStatus | null;
    order_status: OrderStatus | null;
    payment_method: string | null;
    package: string | null;
    theme_code: string | null;
    event_type: string | null;
    event_date: string | null;
    date_from: string | null;
    date_to: string | null;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
}

export interface PackageOption {
    value: string;
    label: string;
    brand: Brand;
}

/** Filter choices the transaction list offers; built server-side from stored values. */
export interface TransactionFilterOptions {
    paymentMethods: string[];
    packages: PackageOption[];
    themeCodes: string[];
    eventTypes: string[];
}

export interface ReconciliationIssue {
    key: string;
    invoice: string | null;
    brand: Brand | null;
    amount: number | null;
    orderStatus: OrderStatus | null;
    paymentStatus: PaymentStatus | null;
    detail: string | null;
    orderId: string | null;
    checkablePaymentId: string | null;
    at: string;
}

export interface SettingsAdmin {
    users: { id: string; name: string; email: string; role: Role; isActive: boolean; lastLoginAt: string | null }[];
    gateway: { driver: string; environment: string; configured: boolean; notificationPath: string };
    application: { environment: string; timezone: string; allowedOrigins: string[]; pendingThresholdMinutes: number; failedJobs: number };
}
