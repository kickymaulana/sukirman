<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MaterialRequestController;
use App\Http\Controllers\DashboardController;
use Inertia\Inertia;

// 💡 Redirect root '/' langsung ke dashboard (nanti otomatis ke login jika belum auth)
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Guest Routes (Hanya untuk user yang belum login)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
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
        Route::get('/material-requests/{id}', 'show')->name('material-requests.show');
        Route::get('/material-requests/{id}/edit-revision', 'revisionEdit')->name('material-requests.revision-edit');
        Route::post('/material-requests/{id}/resubmit', 'revisionResubmit')->name('material-requests.resubmit');
    });

    // ==================== APPROVAL WORKFLOW ====================

    // Manager: Forward
    Route::middleware('role:manager')->group(function () {
        Route::get('/approval/manager', [MaterialRequestController::class, 'managerIndex'])->name('approval.manager');
        Route::post('/approval/manager/{id}/forward', [MaterialRequestController::class, 'forward'])->name('approval.forward');
    });

    // FM/GM: Acknowledge
    Route::middleware('role:fm/gm')->group(function () {
        Route::get('/approval/fmgm', [MaterialRequestController::class, 'fmGmIndex'])->name('approval.fmgm');
        Route::post('/approval/fmgm/{id}/acknowledge', [MaterialRequestController::class, 'acknowledge'])->name('approval.acknowledge');
    });

    // Direksi: Decision
    Route::middleware('role:direksi')->group(function () {
        Route::get('/approval/direksi', [MaterialRequestController::class, 'direksiIndex'])->name('approval.direksi');
        Route::post('/approval/direksi/{id}/decision', [MaterialRequestController::class, 'decision'])->name('approval.decision');
    });

    // Gudang: Verifikasi
    Route::middleware('role:gudang')->group(function () {
        Route::get('/approval/gudang', [MaterialRequestController::class, 'gudangIndex'])->name('approval.gudang');
        Route::post('/approval/gudang/{id}/verify', [MaterialRequestController::class, 'verifyGudang'])->name('approval.verify-gudang');
    });

    // Purchasing: Export
    Route::middleware('role:purchasing')->group(function () {
        Route::get('/approval/purchasing', [MaterialRequestController::class, 'purchasingIndex'])->name('approval.purchasing');
        Route::get('/approval/purchasing/export', [MaterialRequestController::class, 'exportExcel'])->name('approval.export');
    });

    // Notifications
    Route::get('/notifications', function () {
        $notifications = auth()->user()->unreadNotifications()->latest()->get()->map(function ($n) {
            $data = $n->data;
            return ['id' => $n->id, 'message' => $data['message'] ?? '', 'mr_id' => $data['mr_id'] ?? null, 'mr_number' => $data['mr_number'] ?? '', 'time' => $n->created_at->diffForHumans()];
        });
        return Inertia::render('Notification/Index', ['notifications' => $notifications]);
    })->name('notifications.index');

    Route::post('/notifications/{id}/read', function ($id) {
        $notification = auth()->user()->notifications()->find($id);
        if ($notification) { $notification->markAsRead(); }
        return response()->json(['ok' => true]);
    })->name('notifications.read');

    Route::post('/notifications/read-all', function () {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    })->name('notifications.read-all');

    // Auth Actions
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
