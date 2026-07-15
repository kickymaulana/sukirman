<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MaterialRequestController;


Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/material-requests', [MaterialRequestController::class, 'store']);
    Route::get('/material-requests', [MaterialRequestController::class, 'index']);
    Route::get('/material-requests/{id}', [MaterialRequestController::class, 'show']);
    Route::put('/material-requests/{id}/forward', [MaterialRequestController::class, 'forwardByManager']);
    Route::put('/material-requests/{id}/acknowledge', [MaterialRequestController::class, 'acknowledgeByGM']);
    Route::put('/material-requests/{id}/decision', [MaterialRequestController::class, 'decisionByDireksi']);
    Route::put('/material-requests/{id}/verify-gudang', [MaterialRequestController::class, 'verifyByGudang']);
    Route::put('/material-requests/{id}/complete-purchasing', [MaterialRequestController::class, 'completeByPurchasing']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Anda bisa menambahkan route internal SUKIRMAN lainnya di sini...
});
