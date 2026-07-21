<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
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

    // Module route groups are appended below per build phase.
});
