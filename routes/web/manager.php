<?php

/*
|--------------------------------------------------------------------------
| Manager routes  —  middleware: ['auth', 'manager']
|--------------------------------------------------------------------------
| Sections, in order:
|   1. Dashboard & settings
|   2. Reports (on screen)
|   3. Reports (PDF export)
|   4. Payments
|   5. Tenant management
|   6. Lease lifecycle (move-out, renewal)
|   7. Maintenance & renovation requests
|   8. Units
|   9. Utilities (balances)
|  10. Utility bills (the ledger — partial payments, arrears, estimates)
|  11. Collections (arrears, demands, escalation flags)
*/

use App\Http\Controllers\Manager\CollectionsController;
use App\Http\Controllers\Manager\DashboardController;
use App\Http\Controllers\Manager\FeedbackController;
use App\Http\Controllers\Manager\LeaseBalanceController;
use App\Http\Controllers\Manager\MaintenanceCostController;
use App\Http\Controllers\Manager\MaintenanceRequestController;
use App\Http\Controllers\Manager\ManagerController;
use App\Http\Controllers\Manager\PaymentRecordController;
use App\Http\Controllers\Manager\RenovationRequestController;
use App\Http\Controllers\Manager\ReportExportController;
use App\Http\Controllers\Manager\ReportsController;
use App\Http\Controllers\Manager\UnitController;
use App\Http\Controllers\Manager\UtilityBillController;
use App\Http\Controllers\Manager\UtilityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'manager'])->group(function () {

    // -- 1. Dashboard & settings -------------------------------------------
    Route::get('/manager/dashboard', [DashboardController::class, 'dashboard'])->name('manager.dashboard');
    Route::get('/manager/settings',  [DashboardController::class, 'settings'])->name('manager.settings');

    // -- 2. Reports (on screen) --------------------------------------------
    // {report} is one of: active-tenants | payment-history | lease-summary | maintenance-requests
    Route::get('/manager/reports',           [ReportsController::class, 'index'])->name('manager.reports');
    Route::get('/manager/reports/{report}',  [ReportsController::class, 'show'])->name('manager.reports.show');

    // -- 3. Reports (PDF) ---------------------------------------------------
    // NOTE: /manager/reports/{report}/export and /manager/reports/export/{report}
    // are two different endpoints. The first downloads, the second previews inline.
    Route::get('/manager/reports/{report}/export', [ReportExportController::class, 'export'])->name('manager.reports.export');
    Route::get('/manager/reports/export/{report}', [ReportExportController::class, 'viewReportPdf'])->name('manager.reports.viewReportPdf');

    // -- 4. Payments --------------------------------------------------------
    Route::patch('/manager/payments/{payment}/status', [PaymentRecordController::class, 'updatePaymentStatus'])->name('manager.payments.updateStatus');
    Route::patch('/manager/payments',                  [PaymentRecordController::class, 'paymentStore'])->name('manager.payments.store');
    // v19: the same invoice the tenant downloads, for an accepted payment.
    Route::get('/manager/payments/{payment}/invoice',  [PaymentRecordController::class, 'invoice'])->name('manager.payments.invoice');

    // JSON feed for the "Record Payment" modal's lease dropdown.
    // The URI is hard-coded in resources/views/manager/reports/show.blade.php — do not rename it.
    Route::get('/manager/tenant/{tenant}/leases', [LeaseBalanceController::class, 'forTenant'])->name('manager.tenant.leases');

    // -- 5. Tenant management ----------------------------------------------
    // Filtering/searching is done with ?filter= and ?search= on this route.
    Route::get('/manager/tenants',                [ManagerController::class, 'tenants'])->name('manager.tenants');
    Route::get('/manager/tenants/export',         [ManagerController::class, 'exportTenantsPdf'])->name('manager.tenants.export');
    Route::get('/manager/tenants/{id}/view-ids',  [ManagerController::class, 'viewIds'])->name('manager.tenants.viewIds');
    Route::post('/manager/tenants/notify/{id}',   [ManagerController::class, 'notifyTenant'])->name('manager.tenants.notify');

    Route::post('/manager/tenant/approve/{id}', [ManagerController::class, 'approve'])->name('manager.tenant.approve');
    Route::post('/manager/tenant/reject',       [ManagerController::class, 'reject'])->name('manager.tenant.reject');

    // -- 6. Lease lifecycle -------------------------------------------------
    Route::post('/manager/leases/{lease}/move-out',        [ManagerController::class, 'moveOut'])->name('manager.leases.approveMoveOut');
    Route::post('/manager/leases/{lease}/reject-move-out', [ManagerController::class, 'rejectMoveOut'])->name('manager.leases.rejectMoveOut');
    Route::patch('/manager/tenant/{id}/force-move-out',    [ManagerController::class, 'forceMoveOut'])->name('manager.tenant.forceMoveOut');

    // Applies the 5% rent increase. Also runs automatically via `leases:renew` (routes/schedule.php).
    Route::post('/manager/leases/{lease}/renew', [ManagerController::class, 'renewLease'])->name('manager.leases.renew');

    // -- 7. Maintenance & renovation requests -------------------------------
    Route::get('/manager/requests/{id}',          [MaintenanceRequestController::class, 'show'])->name('manager.requests.show');
    Route::patch('/manager/requests/{id}/status', [MaintenanceRequestController::class, 'updateStatus'])->name('manager.requests.updateStatus');

    // Repair costs: work items, who pays, and billing the tenant's share.
    // These render inside manager/requests/show.blade.php.
    Route::post('/manager/requests/{id}/details',                [MaintenanceCostController::class, 'updateDetails'])->name('manager.requests.details');
    Route::post('/manager/requests/{id}/work-items',             [MaintenanceCostController::class, 'addWorkItem'])->name('manager.requests.workItems.store');
    Route::delete('/manager/requests/{id}/work-items/{item}',    [MaintenanceCostController::class, 'removeWorkItem'])->name('manager.requests.workItems.destroy');
    Route::post('/manager/requests/{id}/responsibility',         [MaintenanceCostController::class, 'setResponsibility'])->name('manager.requests.responsibility');
    Route::post('/manager/requests/{id}/charge',                 [MaintenanceCostController::class, 'charge'])->name('manager.requests.charge');

    Route::post('/manager/renovations/{id}/decide', [RenovationRequestController::class, 'decide'])->name('manager.renovations.decide');

    // Feedback — listing/filtering/export lives in the shared reports system
    // (report key "feedback"); this is the one write action, responding.
    Route::post('/manager/feedback/{id}/respond', [FeedbackController::class, 'respond'])->name('manager.feedback.respond');

    // -- 8. Units -----------------------------------------------------------
    Route::get('/manager/units',              [UnitController::class, 'index'])->name('manager.units.index');
    Route::post('/manager/units',             [UnitController::class, 'store'])->name('manager.units.store');
    Route::get('/manager/units/{unit}/edit',  [UnitController::class, 'edit'])->name('manager.units.edit');
    Route::put('/manager/units/{unit}',       [UnitController::class, 'update'])->name('manager.units.update');
    Route::delete('/manager/units/{unit}',    [UnitController::class, 'destroy'])->name('manager.units.destroy');

    // Approving / rejecting a tenant's application for an ADDITIONAL unit.
    Route::post('/manager/approve-unit/{user}/{unit}/{lease}', [UnitController::class, 'approveAdditionalUnit'])->name('manager.approve-unit');
    Route::post('/manager/reject-unit/{user}/{unit}/{lease}',  [UnitController::class, 'rejectAdditionalUnit'])->name('manager.reject-unit');

    // -- 9. Utilities (rolled-up balance per room) --------------------------
    Route::get('/manager/utilities',       [UtilityController::class, 'index'])->name('manager.utilities.index');
    Route::put('/manager/utilities/{id}',  [UtilityController::class, 'updateUtilityBalance'])->name('manager.utilities.update');

    // -- 10. Utility bills (the ledger) -------------------------------------
    // Individual bills, which is where partial payments, arrears and estimated
    // bills are visible. /manager/utilities above shows one number per room;
    // this shows the bills behind that number.
    Route::get('/manager/bills',                      [UtilityBillController::class, 'index'])->name('manager.bills.index');
    Route::post('/manager/bills',                     [UtilityBillController::class, 'store'])->name('manager.bills.store');
    Route::post('/manager/bills/shared',              [UtilityBillController::class, 'storeShared'])->name('manager.bills.storeShared');
    Route::get('/manager/bills/{bill}',               [UtilityBillController::class, 'show'])->name('manager.bills.show');
    Route::post('/manager/bills/{bill}/reconcile',    [UtilityBillController::class, 'reconcile'])->name('manager.bills.reconcile');
    Route::post('/manager/bills/{bill}/adjust',       [UtilityBillController::class, 'adjust'])->name('manager.bills.adjust');
    Route::post('/manager/bills/{bill}/void',         [UtilityBillController::class, 'void'])->name('manager.bills.void');

    // -- 11. Collections ----------------------------------------------------
    // Arrears and the demand paper trail. Nothing here evicts, terminates a
    // lease or cuts a utility — see the docblock on CollectionsController.
    Route::get('/manager/collections',                              [CollectionsController::class, 'index'])->name('manager.collections.index');
    Route::get('/manager/collections/export',                       [CollectionsController::class, 'export'])->name('manager.collections.export');
    Route::get('/manager/collections/{lease}',                      [CollectionsController::class, 'show'])->name('manager.collections.show');
    Route::post('/manager/collections/{lease}/demand',              [CollectionsController::class, 'issue'])->name('manager.collections.issue');
    Route::post('/manager/collections/demands/{demand}/deliver',    [CollectionsController::class, 'deliver'])->name('manager.collections.deliver');
    Route::post('/manager/collections/demands/{demand}/close',      [CollectionsController::class, 'close'])->name('manager.collections.close');
});
