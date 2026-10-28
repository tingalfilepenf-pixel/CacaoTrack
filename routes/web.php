<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CorrectionController;
use App\Http\Controllers\DailyChecklistController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MortalityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegraftController;
use App\Http\Controllers\ScionVarietyController;
use App\Http\Controllers\StockInController;
use App\Http\Controllers\StockOutController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Dashboard (PRD §12): real KPIs/charts, protected like every other data page (FR-01).
// Readable by every authenticated role — no access restriction.
Route::middleware('auth')->get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Operational Routes.
//
// The Daily Checklist has two distinct authorities (confirmed workflow):
//   * managing/verifying task definitions  -> `manage_checklist`
//   * marking an assigned task complete    -> `complete_checklist`
// Staff hold the completion authority; Managers hold the management authority.
// Both may open the page and print it.
Route::prefix('operational')->middleware('auth')->group(function () {
    Route::middleware('permission:manage_checklist,complete_checklist')->group(function () {
        Route::get('/checklist', [DailyChecklistController::class, 'index'])->name('operational.checklist');
        Route::get('/checklist/export', [DailyChecklistController::class, 'exportPdf'])->name('operational.checklist.export');
    });

    Route::middleware('permission:complete_checklist')->group(function () {
        Route::post('/checklist/tasks/{task}/complete', [DailyChecklistController::class, 'completeTask'])->name('operational.checklist.tasks.complete');
    });

    Route::middleware('permission:manage_checklist')->group(function () {
        Route::post('/checklist/tasks', [DailyChecklistController::class, 'storeTask'])->name('operational.checklist.tasks.store');
        Route::put('/checklist/tasks/{task}', [DailyChecklistController::class, 'updateTask'])->name('operational.checklist.tasks.update');
        // Delete replaces the former archive/restore flow (owner-directed); it
        // is guarded server-side — a task with completion history is never deleted.
        Route::delete('/checklist/tasks/{task}', [DailyChecklistController::class, 'destroyTask'])->name('operational.checklist.tasks.destroy');
        // Managers record verification on a Staff completion; Staff cannot.
        Route::post('/checklist/completions/{completion}/verify', [DailyChecklistController::class, 'verifyCompletion'])->name('operational.checklist.completions.verify');
    });

    // Mortality, Regraft and Batches remain `record_operations` functions.
    Route::middleware('permission:record_operations')->group(function () {
        // Mortality is record-only: the adjustment route was removed with the
        // Mortality_Adjustments entity (revised ERD §6).
        Route::get('/mortality', [MortalityController::class, 'index'])->name('operational.mortality');
        Route::post('/mortality/records', [MortalityController::class, 'store'])->name('operational.mortality.records.store');

        Route::get('/regraft', [RegraftController::class, 'index'])->name('operational.regraft');
        Route::post('/regraft/records', [RegraftController::class, 'store'])->name('operational.regraft.records.store');

        // Batches moved from `/inventory/batches` to `/operational/batches` (P3
        // decision f): a batch is the central operational record (PRD §5).
        Route::get('/batches', [BatchController::class, 'index'])->name('operational.batches');
        Route::post('/batches/manage', [BatchController::class, 'store'])->name('operational.batches.manage.store');
        Route::put('/batches/{batch}', [BatchController::class, 'update'])->name('operational.batches.update');
        // Delete replaces the former archive/restore flow (owner-directed); it
        // is guarded server-side — a batch with dependent operational records
        // is never deleted.
        Route::delete('/batches/{batch}', [BatchController::class, 'destroy'])->name('operational.batches.destroy');
    });
});

// Record corrections (OD-5): fix a mistaken mortality, regraft, stock-out or
// stock-in entry. Gated by the `correct_records` access restriction, which is
// administrator-only by default (see Role::ADMIN_ONLY_PERMISSIONS). No new
// table and no revival of the removed Mortality_Adjustments / Stock_Reversal
// concepts — a correction re-validates and writes before/after to Activity_Log.
Route::prefix('corrections')->middleware(['auth', 'permission:correct_records'])->group(function () {
    Route::post('/mortality/{mortality}', [CorrectionController::class, 'mortality'])->name('corrections.mortality');
    Route::post('/regraft/{regraft}', [CorrectionController::class, 'regraft'])->name('corrections.regraft');
    Route::post('/stock-in/{stockIn}', [CorrectionController::class, 'stockIn'])->name('corrections.stock-in');
    Route::post('/stock-out/{stockOut}', [CorrectionController::class, 'stockOut'])->name('corrections.stock-out');
});

// Scion Variety master data (FR-07). Lives in the Operational area but keeps
// the master-data access restriction `manage_inventory` (P3 decision g).
Route::prefix('operational')->middleware(['auth', 'permission:manage_inventory'])->group(function () {
    Route::get('/scion-varieties', [ScionVarietyController::class, 'index'])->name('operational.scion-varieties');
    Route::post('/scion-varieties/manage', [ScionVarietyController::class, 'store'])->name('operational.scion-varieties.manage.store');
    Route::put('/scion-varieties/{scion_variety}', [ScionVarietyController::class, 'update'])->name('operational.scion-varieties.update');
    // Delete replaces the former archive/restore flow (owner-directed); it is
    // guarded server-side — a variety referenced by any record is never deleted.
    Route::delete('/scion-varieties/{scion_variety}', [ScionVarietyController::class, 'destroy'])->name('operational.scion-varieties.destroy');
});

// Inventory Routes
Route::prefix('inventory')->group(function () {
    // Inventory view: operational recorders, master-data managers and stock
    // handlers may read the page. Recording a snapshot is a separate authority
    // (below) — view access never implies write access.
    Route::middleware(['auth', 'permission:record_operations,manage_inventory,manage_stock'])
        ->get('/', [InventoryController::class, 'index'])
        ->name('inventory.index');

    // Stock transactions — require the `manage_stock` access restriction.
    Route::middleware(['auth', 'permission:manage_stock'])->group(function () {
        Route::get('/stock-in', [StockInController::class, 'index'])->name('inventory.stock-in');
        Route::post('/stock-in', [StockInController::class, 'store'])->name('inventory.stock-in.store');

        Route::get('/stock-out', [StockOutController::class, 'index'])->name('inventory.stock-out');
        Route::post('/stock-out', [StockOutController::class, 'store'])->name('inventory.stock-out.store');
    });

    // Inline Supplier/Client creation from the stock forms is master-data
    // maintenance, so it needs `manage_inventory` (not `manage_stock`).
    Route::middleware(['auth', 'permission:manage_inventory'])->group(function () {
        Route::post('/suppliers', [StockInController::class, 'storeSupplier'])->name('inventory.suppliers.store');
        Route::post('/clients', [StockOutController::class, 'storeClient'])->name('inventory.clients.store');
    });

    // "Record Monthly Inventory" (FR-08) writes a stored monthly snapshot and
    // requires `record_operations` only.
    Route::middleware(['auth', 'permission:record_operations'])
        ->post('/snapshots', [InventoryController::class, 'store'])
        ->name('inventory.snapshots.store');

    // Management master data — require the `manage_inventory` access restriction.
    Route::middleware(['auth', 'permission:manage_inventory'])->group(function () {
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('inventory.suppliers');
        Route::post('/suppliers/manage', [SupplierController::class, 'store'])->name('inventory.suppliers.manage.store');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('inventory.suppliers.update');
        Route::post('/suppliers/{supplier}/archive', [SupplierController::class, 'archive'])->name('inventory.suppliers.archive');
        Route::post('/suppliers/{supplier}/restore', [SupplierController::class, 'restore'])->name('inventory.suppliers.restore');

        Route::get('/clients', [ClientController::class, 'index'])->name('inventory.clients');
        Route::post('/clients/manage', [ClientController::class, 'store'])->name('inventory.clients.manage.store');
        Route::put('/clients/{client}', [ClientController::class, 'update'])->name('inventory.clients.update');
        Route::post('/clients/{client}/archive', [ClientController::class, 'archive'])->name('inventory.clients.archive');
        Route::post('/clients/{client}/restore', [ClientController::class, 'restore'])->name('inventory.clients.restore');
    });
});

// Analytics Routes — require the `view_analytics` access restriction;
// report generation additionally requires `generate_reports`.
Route::prefix('analytics')->middleware('auth')->group(function () {
    Route::middleware('permission:view_analytics')->group(function () {
        Route::get('/', [AnalyticsController::class, 'index'])->name('analytics.index');

        Route::get('/export', [AnalyticsController::class, 'exportOperationalPdf'])->name('analytics.export');

        Route::get('/export/csv', [AnalyticsController::class, 'exportOperationalCsv'])->name('analytics.export.csv');

        Route::get('/stock-ledger', [AnalyticsController::class, 'stockLedger'])->name('analytics.stock-ledger');

        Route::get('/stock-ledger/export', [AnalyticsController::class, 'exportPdf'])->name('analytics.stock-ledger.export');

        Route::get('/stock-ledger/export/csv', [AnalyticsController::class, 'exportLedgerCsv'])->name('analytics.stock-ledger.export.csv');

        Route::get('/mortality-matrix', [AnalyticsController::class, 'mortalityMatrix'])->name('analytics.mortality-matrix');

        Route::get('/mortality-matrix/export', [AnalyticsController::class, 'exportMatrixPdf'])->name('analytics.mortality-matrix.export');

        Route::get('/mortality-matrix/export/csv', [AnalyticsController::class, 'exportMatrixCsv'])->name('analytics.mortality-matrix.export.csv');

        Route::prefix('historical')->group(function () {
            Route::get('/seasonality', [AnalyticsController::class, 'seasonality'])->name('analytics.historical.seasonality');

            Route::get('/age-profile', [AnalyticsController::class, 'ageProfile'])->name('analytics.historical.age-profile');

            Route::get('/gap-report', [AnalyticsController::class, 'gapReport'])->name('analytics.historical.gap-report');

            Route::get('/gap-report/export', [AnalyticsController::class, 'exportGapPdf'])->name('analytics.historical.gap-report.export');

            Route::get('/gap-report/export/csv', [AnalyticsController::class, 'exportGapCsv'])->name('analytics.historical.gap-report.export.csv');
        });
    });

    Route::post('/reports', [AnalyticsController::class, 'generate'])
        ->middleware('permission:generate_reports')
        ->name('analytics.reports.store');
});

// Account Routes
Route::middleware('auth')->group(function () {
    // User Management (listing and mutations) requires `manage_users` (FR-02).
    Route::middleware('permission:manage_users')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users/manage', [UserController::class, 'store'])->name('users.manage.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::get('/activity', [ActivityController::class, 'index'])
        ->middleware('permission:view_activity')
        ->name('activity.index');

    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
});
