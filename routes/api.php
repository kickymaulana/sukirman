<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Route Login (Bisa diakses tanpa token)
Route::post('/login', [AuthController::class, 'login']);

// Route yang diproteksi oleh Sanctum
Route::middleware('auth:sanctum')->group(function () {

    // Route Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // Route untuk cek data profile user
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Anda bisa menambahkan route internal SUKIRMAN lainnya di sini...
});
