<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DailyChecklistController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MortalityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReplantingController;
use App\Http\Controllers\ScionReceivingController;
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

// Operational Routes — Batches, Mortality, Replanting and the checklist all
// require the `record_operations` access restriction (PRD §5).
Route::prefix('operational')->middleware(['auth', 'permission:record_operations'])->group(function () {
    Route::get('/checklist', [DailyChecklistController::class, 'index'])->name('operational.checklist');
    Route::get('/checklist/export', [DailyChecklistController::class, 'exportPdf'])->name('operational.checklist.export');
    Route::post('/checklist/tasks', [DailyChecklistController::class, 'storeTask'])->name('operational.checklist.tasks.store');
    Route::put('/checklist/tasks/{task}', [DailyChecklistController::class, 'updateTask'])->name('operational.checklist.tasks.update');
    Route::post('/checklist/tasks/{task}/complete', [DailyChecklistController::class, 'completeTask'])->name('operational.checklist.tasks.complete');
    Route::post('/checklist/tasks/{task}/archive', [DailyChecklistController::class, 'archiveTask'])->name('operational.checklist.tasks.archive');
    Route::post('/checklist/tasks/{task}/restore', [DailyChecklistController::class, 'restoreTask'])->name('operational.checklist.tasks.restore');
    Route::delete('/checklist/tasks/{task}', [DailyChecklistController::class, 'destroyTask'])->name('operational.checklist.tasks.destroy');

    // Mortality is record-only: the adjustment route was removed with the
    // Mortality_Adjustments entity (revised ERD §6).
    Route::get('/mortality', [MortalityController::class, 'index'])->name('operational.mortality');
    Route::post('/mortality/records', [MortalityController::class, 'store'])->name('operational.mortality.records.store');

    Route::get('/replanting', [ReplantingController::class, 'index'])->name('operational.replanting');
    Route::post('/replanting/records', [ReplantingController::class, 'store'])->name('operational.replanting.records.store');

    // Batches moved from `/inventory/batches` to `/operational/batches` (P3
    // decision f): a batch is the central operational record (PRD §5).
    Route::get('/batches', [BatchController::class, 'index'])->name('operational.batches');
    Route::post('/batches/manage', [BatchController::class, 'store'])->name('operational.batches.manage.store');
    Route::put('/batches/{batch}', [BatchController::class, 'update'])->name('operational.batches.update');
    Route::post('/batches/{batch}/archive', [BatchController::class, 'archive'])->name('operational.batches.archive');
    Route::post('/batches/{batch}/restore', [BatchController::class, 'restore'])->name('operational.batches.restore');
});

// Scion Variety master data (FR-07). Lives in the Operational area but keeps
// the master-data access restriction `manage_inventory` (P3 decision g).
Route::prefix('operational')->middleware(['auth', 'permission:manage_inventory'])->group(function () {
    Route::get('/scion-varieties', [ScionVarietyController::class, 'index'])->name('operational.scion-varieties');
    Route::post('/scion-varieties/manage', [ScionVarietyController::class, 'store'])->name('operational.scion-varieties.manage.store');
    Route::put('/scion-varieties/{scion_variety}', [ScionVarietyController::class, 'update'])->name('operational.scion-varieties.update');
    Route::post('/scion-varieties/{scion_variety}/archive', [ScionVarietyController::class, 'archive'])->name('operational.scion-varieties.archive');
    Route::post('/scion-varieties/{scion_variety}/restore', [ScionVarietyController::class, 'restore'])->name('operational.scion-varieties.restore');
});

// Scion Receiving log (approved additive entity, ERD §3.16). Sits in the
// Operational area but keeps `manage_inventory`: it is reference-adjacent data
// entry, not routine day-to-day recording (same reasoning as Scion Variety).
Route::prefix('operational')->middleware(['auth', 'permission:manage_inventory'])->group(function () {
    Route::get('/scion-receivings', [ScionReceivingController::class, 'index'])->name('operational.scion-receivings');
    Route::post('/scion-receivings', [ScionReceivingController::class, 'store'])->name('operational.scion-receivings.store');
});

// Inventory Routes
Route::prefix('inventory')->group(function () {
    // Inventory view: available to stock handlers and master-data managers.
    Route::middleware(['auth', 'permission:manage_inventory,manage_stock'])
        ->get('/', [InventoryController::class, 'index'])
        ->name('inventory.index');

    // Stock transactions — require the `manage_stock` access restriction.
    Route::middleware(['auth', 'permission:manage_stock'])->group(function () {
        Route::get('/stock-in', [StockInController::class, 'index'])->name('inventory.stock-in');
        Route::post('/stock-in', [StockInController::class, 'store'])->name('inventory.stock-in.store');
        Route::post('/suppliers', [StockInController::class, 'storeSupplier'])->name('inventory.suppliers.store');

        Route::get('/stock-out', [StockOutController::class, 'index'])->name('inventory.stock-out');
        Route::post('/stock-out', [StockOutController::class, 'store'])->name('inventory.stock-out.store');
        Route::post('/clients', [StockOutController::class, 'storeClient'])->name('inventory.clients.store');
    });

    // "Record Monthly Inventory" (FR-08) writes a stored monthly snapshot.
    Route::middleware(['auth', 'permission:manage_inventory'])
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
    // Users listing is readable by any authenticated user; mutations require
    // the `manage_users` access restriction (FR-02).
    Route::get('/users', [UserController::class, 'index'])->name('users.index');

    Route::middleware('permission:manage_users')->group(function () {
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
