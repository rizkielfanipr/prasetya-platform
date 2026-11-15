<?php

declare(strict_types=1);

use App\Http\Controllers\BackOffice\AccountController;
use App\Http\Controllers\BackOffice\AdminNoteController;
use App\Http\Controllers\BackOffice\Auth\LoginController;
use App\Http\Controllers\BackOffice\Content\ContentAiController;
use App\Http\Controllers\BackOffice\Content\ContentAssetController;
use App\Http\Controllers\BackOffice\Content\ContentBrandController;
use App\Http\Controllers\BackOffice\Content\ContentCalendarController;
use App\Http\Controllers\BackOffice\Content\ContentInsightController;
use App\Http\Controllers\BackOffice\Content\ContentItemController;
use App\Http\Controllers\BackOffice\Content\ContentItemEvidenceController;
use App\Http\Controllers\BackOffice\Content\ContentPostingController;
use App\Http\Controllers\BackOffice\Content\ContentResearchController;
use App\Http\Controllers\BackOffice\DashboardController;
use App\Http\Controllers\BackOffice\GatewayLogController;
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

    // Content Studio (ADR-0011). Manual workflow; nothing here posts to Instagram.
    Route::get('content', [ContentCalendarController::class, 'home'])->name('content.home');
    Route::get('content/calendar', ContentCalendarController::class)->name('content.calendar');
    Route::get('content/to-post', ContentPostingController::class)->name('content.to-post');
    Route::get('content/insights', [ContentInsightController::class, 'index'])->name('content.insights');
    Route::post('content/items/{item:public_id}/insights', [ContentInsightController::class, 'store'])->name('content.items.insights.store');
    Route::get('content/items', [ContentItemController::class, 'index'])->name('content.items.index');
    Route::post('content/items', [ContentItemController::class, 'store'])->name('content.items.store');
    Route::get('content/items/{item:public_id}', [ContentItemController::class, 'show'])->name('content.items.show');
    Route::put('content/items/{item:public_id}', [ContentItemController::class, 'update'])->name('content.items.update');
    Route::put('content/items/{item:public_id}/details', [ContentItemController::class, 'details'])->name('content.items.details');
    // Creator-owned lifecycle (ADR-0012): Draft <-> Ready -> Published, Draft|Ready -> Archived.
    Route::post('content/items/{item:public_id}/ready', [ContentItemController::class, 'ready'])->name('content.items.ready');
    Route::post('content/items/{item:public_id}/return-to-draft', [ContentItemController::class, 'returnToDraft'])->name('content.items.return-to-draft');
    Route::post('content/items/{item:public_id}/archive', [ContentItemController::class, 'archive'])->name('content.items.archive');
    // Retired editorial routes (ADR-0011) during the compatibility window: refuse safely, change nothing.
    Route::post('content/items/{item:public_id}/{retired}', [ContentItemController::class, 'retiredReview'])
        ->whereIn('retired', ['submit-review', 'request-revision', 'approve', 'reject'])->name('content.items.retired-review');
    Route::post('content/items/{item:public_id}/publication', [ContentItemController::class, 'markPublished'])->name('content.items.publication');
    Route::get('content/assets/{asset:public_id}', ContentAssetController::class)->name('content.assets.show');
    // Evidence sheets of the detail page (ADR-0013): read-only JSON, loaded when a person opens the sheet.
    Route::get('content/items/{item:public_id}/history', [ContentItemEvidenceController::class, 'history'])->name('content.items.history');
    Route::get('content/items/{item:public_id}/activity', [ContentItemEvidenceController::class, 'activity'])->name('content.items.activity');

    // AI copy drafts (ADR-0011, phase 3). Off unless configured; the result is always a draft revision.
    Route::post('content/items/{item:public_id}/generate', [ContentAiController::class, 'generate'])->middleware('throttle:20,1')->name('content.items.generate');
    // Pengaturan AI moved to Pengaturan → Model AI (ADR-0017).
    Route::redirect('content/settings', '/settings/ai')->name('content.settings');
    // Referensi (ADR-0017): links added by hand and cited by the item; web research is no longer offered.
    Route::post('content/items/{item:public_id}/sources', [ContentResearchController::class, 'addSource'])->middleware('throttle:20,1')->name('content.items.sources.store');
    // Any source of the item's brand may be cited; CiteSource checks the brand, so no implicit scoping to citations.
    Route::post('content/items/{item:public_id}/sources/{source:public_id}', [ContentResearchController::class, 'cite'])->withoutScopedBindings()->name('content.items.sources.cite');
    Route::delete('content/items/{item:public_id}/sources/{source:public_id}', [ContentResearchController::class, 'uncite'])->withoutScopedBindings()->name('content.items.sources.uncite');
    Route::post('content/generation-runs/{run:public_id}/feedback', [ContentAiController::class, 'feedback'])->name('content.generation-runs.feedback');

    // Brand Knowledge (ADR-0011). Campaign writes remain brand-scoped although their UI lives in Calendar planning.
    Route::get('content/brands', [ContentBrandController::class, 'index'])->name('content.brands.index');
    Route::scopeBindings()->prefix('content/brands/{brand:public_id}')->name('content.brands.')->group(function (): void {
        Route::get('/', [ContentBrandController::class, 'show'])->name('show');
        Route::put('knowledge', [ContentBrandController::class, 'updateKnowledge'])->name('knowledge');
        Route::post('knowledge/assist', [ContentBrandController::class, 'assistKnowledge'])->middleware('throttle:10,1')->name('knowledge.assist');
        Route::post('pillars', [ContentBrandController::class, 'storePillar'])->name('pillars.store');
        Route::put('pillars/{pillar:public_id}', [ContentBrandController::class, 'updatePillar'])->name('pillars.update');
        Route::post('campaigns', [ContentBrandController::class, 'storeCampaign'])->name('campaigns.store');
        Route::put('campaigns/{campaign:public_id}', [ContentBrandController::class, 'updateCampaign'])->name('campaigns.update');
    });

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('settings/activity', SystemActivityController::class)->name('settings.activity');
    Route::get('settings/gateway-log', GatewayLogController::class)->name('settings.gateway-log');
    Route::get('settings/ai', [ContentAiController::class, 'settings'])->name('settings.ai');
    Route::put('settings/ai/model', [ContentAiController::class, 'selectModel'])->name('settings.ai.model');

    // The signed-in person's own account: the password only. Names change in Pengguna (ADR-0015).
    Route::get('account/password', [AccountController::class, 'password'])->name('account.password');
    Route::put('account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
});

// Stand-in for Duitku's hosted page. Never registered in production or with the real gateway.
if (config('platform.payment_gateway') === 'fake' && ! app()->isProduction()) {
    Route::get('dev/fake-checkout/{payment:gateway_invoice_number}', [FakeCheckoutController::class, 'show'])->name('dev.fake-checkout.show');
    Route::post('dev/fake-checkout/{payment:gateway_invoice_number}', [FakeCheckoutController::class, 'pay'])->name('dev.fake-checkout.pay');
}
