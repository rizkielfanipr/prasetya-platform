<?php

declare(strict_types=1);

use App\Http\Controllers\BackOffice\AdminNoteController;
use App\Http\Controllers\BackOffice\AuditLogController;
use App\Http\Controllers\BackOffice\Auth\LoginController;
use App\Http\Controllers\BackOffice\Auth\PasswordResetController;
use App\Http\Controllers\BackOffice\DashboardController;
use App\Http\Controllers\BackOffice\PaymentEventController;
use App\Http\Controllers\BackOffice\ReconciliationController;
use App\Http\Controllers\BackOffice\SettingsController;
use App\Http\Controllers\BackOffice\TransactionController;
use App\Http\Controllers\Dev\FakeCheckoutController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
    Route::get('forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('transactions/{order}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::post('transactions/{order}/notes', [AdminNoteController::class, 'store'])->name('transactions.notes.store');

    Route::get('payment-events', PaymentEventController::class)->name('payment-events.index');

    Route::get('reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
    Route::post('reconciliation/payments/{payment}/check-status', [ReconciliationController::class, 'checkStatus'])->name('reconciliation.check-status');

    Route::get('audit-log', AuditLogController::class)->name('audit-log.index');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::put('settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
});

// Stand-in for Duitku's hosted page. Never registered in production or with the real gateway.
if (config('platform.payment_gateway') === 'fake' && ! app()->isProduction()) {
    Route::get('dev/fake-checkout/{payment:gateway_invoice_number}', [FakeCheckoutController::class, 'show'])->name('dev.fake-checkout.show');
    Route::post('dev/fake-checkout/{payment:gateway_invoice_number}', [FakeCheckoutController::class, 'pay'])->name('dev.fake-checkout.pay');
}
