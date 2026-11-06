/** Mirrors routes/web.php. The one place a back-office URL is written on the client. */
export const routes = {
    login: '/login',
    logout: '/logout',
    dashboard: '/dashboard',
    transactions: '/transactions',
    transaction: (id: string) => `/transactions/${id}`,
    transactionNotes: (id: string) => `/transactions/${id}/notes`,
    paymentEvents: '/payment-events',
    reconciliation: '/reconciliation',
    gatewayCheck: (paymentId: string) => `/reconciliation/payments/${paymentId}/check-status`,
    auditLog: '/audit-log',
    settings: '/settings',
    settingsProfile: '/settings/profile',
    settingsPassword: '/settings/password',
} as const;
