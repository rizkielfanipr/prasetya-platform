<?php

declare(strict_types=1);

use App\Http\Controllers\BackOffice\AdminNoteController;
use App\Http\Controllers\BackOffice\Auth\LoginController;
use App\Http\Controllers\BackOffice\DashboardController;
use App\Http\Controllers\BackOffice\LegacyWorkspaceRedirectController;
use App\Http\Controllers\BackOffice\ReconciliationController;
use App\Http\Controllers\BackOffice\SettingsController;
use App\Http\Controllers\BackOffice\SystemActivityController;
use App\Http\Controllers\BackOffice\TransactionController;
use App\Http\Controllers\BackOffice\UserController;
use App\Http\Controllers\Dev\FakeCheckoutController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('transactions/{order}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::post('transactions/{order}/notes', [AdminNoteController::class, 'store'])->name('transactions.notes.store');

    Route::get('payment-events', [LegacyWorkspaceRedirectController::class, 'paymentEvents'])->name('payment-events.index');

    Route::get('reconciliation', [LegacyWorkspaceRedirectController::class, 'reconciliation'])->name('reconciliation.index');
    Route::post('reconciliation/payments/{payment}/check-status', [ReconciliationController::class, 'checkStatus'])->name('reconciliation.check-status');

    Route::get('audit-log', [LegacyWorkspaceRedirectController::class, 'auditLog'])->name('audit-log.index');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user:public_id}', [UserController::class, 'show'])->name('users.show');
    Route::put('users/{user:public_id}', [UserController::class, 'update'])->name('users.update');
    Route::post('users/{user:public_id}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::post('users/{user:public_id}/activate', [UserController::class, 'activate'])->name('users.activate');
    Route::put('users/{user:public_id}/password', [UserController::class, 'password'])->name('users.password');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('settings/activity', SystemActivityController::class)->name('settings.activity');
    Route::put('settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::put('settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
});

// Stand-in for Duitku's hosted page. Never registered in production or with the real gateway.
if (config('platform.payment_gateway') === 'fake' && ! app()->isProduction()) {
    Route::get('dev/fake-checkout/{payment:gateway_invoice_number}', [FakeCheckoutController::class, 'show'])->name('dev.fake-checkout.show');
    Route::post('dev/fake-checkout/{payment:gateway_invoice_number}', [FakeCheckoutController::class, 'pay'])->name('dev.fake-checkout.pay');
}
