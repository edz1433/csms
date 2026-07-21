<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\Setup\AccountTitleController;
use App\Http\Controllers\Setup\FundClusterController;
use App\Http\Controllers\Setup\LocationController;
use App\Http\Controllers\Setup\SupplierController;
use App\Http\Controllers\Setup\UnitController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Entry
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => Auth::check()
    ? redirect()->route(Auth::user()->firstAccessiblePage())
    : redirect()->route('login'));

/*
|--------------------------------------------------------------------------
| Guest (auth)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated application
|--------------------------------------------------------------------------
| Every mutating request by accounting_staff is blocked by
| deny.accounting.write EXCEPT the payment-status toggle, which is registered
| outside this group (see Releasing routes). Each page is additionally gated
| by its page key via the `page` middleware.
*/
Route::middleware(['auth', 'deny.accounting.write'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('page:dashboard')->name('dashboard');

    // Stock lookup — used by the Releasing form; any authenticated user.
    Route::get('/items/{item}/stock', [ItemController::class, 'stock'])->name('items.stock');

    /* ---- Inventory / Items (Phase 4) ---- */

    Route::middleware('page:items')->group(function () {
        Route::get('/items', [ItemController::class, 'index'])->name('items.index');
        Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');

        // Item CRUD is Administrator-only.
        Route::middleware('role:administrator')->group(function () {
            Route::post('/items', [ItemController::class, 'store'])->name('items.store');
            Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update');
            Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
        });
    });

    /* ---- Receiving (Phase 5) ---- */

    Route::middleware('page:receiving')->group(function () {
        Route::get('/deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
        Route::get('/deliveries/create', [DeliveryController::class, 'create'])->name('deliveries.create');
        Route::post('/deliveries', [DeliveryController::class, 'store'])->name('deliveries.store');
        Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');
    });

    /* ---- Releasing (Phase 6) ---- */

    Route::middleware('page:releasing')->group(function () {
        Route::get('/releases', [ReleaseController::class, 'index'])->name('releases.index');
        Route::get('/releases/create', [ReleaseController::class, 'create'])->name('releases.create');
        Route::post('/releases', [ReleaseController::class, 'store'])->name('releases.store');
        Route::get('/releases/{release}', [ReleaseController::class, 'show'])->name('releases.show');
    });

    /* ---- Reports (Phase 7) ---- */

    Route::middleware('page:reports')->group(function () {
        Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('/reports/stock-card', [ReportsController::class, 'stockCard'])->name('reports.stock-card');
        Route::get('/reports/payment-status', [ReportsController::class, 'paymentStatus'])->name('reports.payment-status');
        Route::get('/reports/{report}/export', [ReportsController::class, 'export'])->name('reports.export');
    });

    /* ---- Setup: Reference data (Phase 3) ---- */

    Route::middleware('page:locations')->group(function () {
        Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
        Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
        Route::put('/locations/{location}', [LocationController::class, 'update'])->name('locations.update');
        Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');
        Route::patch('/locations/{location}/toggle', [LocationController::class, 'toggle'])->name('locations.toggle');
    });

    Route::middleware('page:units')->group(function () {
        Route::get('/units', [UnitController::class, 'index'])->name('units.index');
        Route::post('/units', [UnitController::class, 'store'])->name('units.store');
        Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
    });

    Route::middleware('page:fund_clusters')->group(function () {
        Route::get('/fund-clusters', [FundClusterController::class, 'index'])->name('fund-clusters.index');
        Route::post('/fund-clusters', [FundClusterController::class, 'store'])->name('fund-clusters.store');
        Route::put('/fund-clusters/{fundCluster}', [FundClusterController::class, 'update'])->name('fund-clusters.update');
        Route::delete('/fund-clusters/{fundCluster}', [FundClusterController::class, 'destroy'])->name('fund-clusters.destroy');
        Route::patch('/fund-clusters/{fundCluster}/toggle', [FundClusterController::class, 'toggle'])->name('fund-clusters.toggle');
    });

    Route::middleware('page:account_titles')->group(function () {
        Route::get('/account-titles', [AccountTitleController::class, 'index'])->name('account-titles.index');
        Route::post('/account-titles', [AccountTitleController::class, 'store'])->name('account-titles.store');
        Route::put('/account-titles/{accountTitle}', [AccountTitleController::class, 'update'])->name('account-titles.update');
        Route::delete('/account-titles/{accountTitle}', [AccountTitleController::class, 'destroy'])->name('account-titles.destroy');
        Route::patch('/account-titles/{accountTitle}/toggle', [AccountTitleController::class, 'toggle'])->name('account-titles.toggle');
    });

    Route::middleware('page:suppliers')->group(function () {
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
        Route::patch('/suppliers/{supplier}/toggle', [SupplierController::class, 'toggle'])->name('suppliers.toggle');
    });
});

/*
|--------------------------------------------------------------------------
| Payment-status toggle (Phase 6) — the ONE write exception for accounting.
|--------------------------------------------------------------------------
| Registered OUTSIDE the deny.accounting.write group. Allowed for
| administrator + accounting_staff only.
*/
Route::middleware(['auth', 'page:releasing', 'role:administrator,accounting_staff'])
    ->patch('/releases/{release}/items/{item}/payment-status', [ReleaseController::class, 'togglePayment'])
    ->name('releases.items.payment');
