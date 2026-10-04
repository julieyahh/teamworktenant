<?php

/*
|--------------------------------------------------------------------------
| Tenant routes  —  middleware: ['auth']
|--------------------------------------------------------------------------
| Sections, in order:
|   1. Dashboard
|   2. Application
|   3. Leases
|   4. Payments
|   5. Maintenance requests
|   6. Renovation requests
|   7. Feedback
|   8. Notifications
|
| Note: Tenant\TenantController@payments and @requests are no longer routed.
| /tenant/payments and /tenant/requests used to be registered twice, and the
| second registration (the dedicated PaymentController / TenantRequestController)
| silently replaced the first. Only the live one is listed here.
*/

use App\Http\Controllers\Tenant\FeedbackController;
use App\Http\Controllers\Tenant\LeaseController;
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\PaymentController;
use App\Http\Controllers\Tenant\RenovationRequestController;
use App\Http\Controllers\Tenant\TenantApplicationController;
use App\Http\Controllers\Tenant\TenantController;
use App\Http\Controllers\Tenant\TenantRequestController;
use App\Http\Controllers\TenantLeaseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {

    // -- 1. Dashboard -------------------------------------------------------
    Route::get('/tenant/home', [TenantController::class, 'dashboard'])->name('tenant.home');

    // -- 2. Application -----------------------------------------------------
    // The form itself is a modal; show() returns the tenant's existing application as JSON.
    Route::get('/tenant/application',         [TenantApplicationController::class, 'show'])->name('tenant.application');
    Route::post('/tenant/application/submit', [TenantApplicationController::class, 'submit'])->name('tenant.application.submit');

    // -- 3. Leases ----------------------------------------------------------
    Route::get('/tenant/leases',        [TenantLeaseController::class, 'index'])->name('tenant.leases');
    Route::post('/tenant/leases/apply', [TenantLeaseController::class, 'store'])->name('tenant.leases.store');
    Route::post('/tenant/leases/{id}/request-move-out', [TenantLeaseController::class, 'requestMoveOut'])->name('tenant.leases.requestMoveOut');

    // v19: renewal starts with the tenant. The manager's Renew action stays
    // locked until this has been asked.
    Route::post('/tenant/leases/{id}/request-renewal',  [TenantLeaseController::class, 'requestRenewal'])->name('tenant.leases.requestRenewal');
    Route::patch('/tenant/leases/{id}/cancel-renewal',  [TenantLeaseController::class, 'cancelRenewal'])->name('tenant.leases.cancelRenewal');

    Route::patch('/tenant/cancel-move-out/{leaseId}', [LeaseController::class, 'cancelMoveOut'])->name('tenant.cancelMoveOut');
    Route::patch('/tenant/cancel-add-unit/{leaseId}', [LeaseController::class, 'cancelAddUnit'])->name('tenant.cancelAddUnit');

    // -- 4. Payments --------------------------------------------------------
    Route::get('/tenant/payments',        [PaymentController::class, 'index'])->name('tenant.payments');
    Route::post('/tenant/payments/store', [PaymentController::class, 'store'])->name('tenant.payments.store');
    // v19: invoice for an accepted payment (PDF). Scoped to the signed-in tenant.
    Route::get('/tenant/payments/{payment}/invoice', [PaymentController::class, 'invoice'])->name('tenant.payments.invoice');

    // -- 5. Maintenance requests -------------------------------------------
    Route::get('/tenant/requests',              [TenantRequestController::class, 'index'])->name('tenant.requests');
    Route::post('/tenant/requests/store',       [TenantRequestController::class, 'store'])->name('tenant.requests.store');
    // Detail view — the tenant's side of everything the office records about
    // a repair. Scoped to the signed-in tenant inside the controller.
    Route::get('/tenant/requests/{id}',         [TenantRequestController::class, 'show'])->name('tenant.requests.show');
    Route::patch('/tenant/requests/{id}/cancel',[TenantRequestController::class, 'cancel'])->name('tenant.requests.cancel');

    // -- 6. Renovation requests --------------------------------------------
    Route::get('/tenant/renovations',               [RenovationRequestController::class, 'index'])->name('tenant.renovations');
    Route::post('/tenant/renovations/store',        [RenovationRequestController::class, 'store'])->name('tenant.renovations.store');
    Route::patch('/tenant/renovations/{id}/cancel', [RenovationRequestController::class, 'cancel'])->name('tenant.renovations.cancel');

    // -- 7. Feedback ----------------------------------------------------------
    Route::get('/tenant/feedback',        [FeedbackController::class, 'index'])->name('tenant.feedback');
    Route::post('/tenant/feedback/store', [FeedbackController::class, 'store'])->name('tenant.feedback.store');

    // -- 8. Notifications ---------------------------------------------------
    Route::get('/tenant/notifications', [NotificationController::class, 'index'])->name('tenant.notifications');
});
