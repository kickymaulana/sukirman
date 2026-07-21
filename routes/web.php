<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MaterialRequestController;
use App\Http\Controllers\DashboardController;

// 💡 Redirect root '/' langsung ke dashboard (nanti otomatis ke login jika belum auth)
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Guest Routes (Hanya untuk user yang belum login)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Authenticated Routes (Harus login)
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Material Request (SUKIRMAN)
    Route::controller(MaterialRequestController::class)->group(function () {
        Route::get('/material-requests', 'index')->name('material-requests.index');
        Route::get('/material-requests/create', 'create')->name('material-requests.create');
        Route::post('/material-requests', 'store')->name('material-requests.store');
    });

    // Auth Actions
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
