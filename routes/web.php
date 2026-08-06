<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MaterialRequestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminOverviewController;
use Inertia\Inertia;

// 💡 Redirect root '/' langsung ke dashboard (nanti otomatis ke login jika belum auth)
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// SSO Login
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::get('/auth/sso', [AuthController::class, 'redirectSso'])->name('sso.redirect');
Route::get('/callback', [AuthController::class, 'callbackSso'])->name('sso.callback');

// Pilih posisi saat user baru (belum aktif)
Route::get('/pending-role', [AuthController::class, 'pendingRole'])->name('pending-role');
Route::post('/pending-role', [AuthController::class, 'submitRole'])->name('pending-role.submit');

// Authenticated Routes (Harus login)
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Material Request (SUKIRMAN)
    Route::controller(MaterialRequestController::class)->group(function () {
        Route::get('/material-requests', 'index')->name('material-requests.index');
        Route::get('/my-mrs', 'myMrs')->name('material-requests.my-mrs');
        Route::get('/material-requests/create', 'create')->name('material-requests.create');
        Route::post('/material-requests', 'store')->name('material-requests.store');
        Route::get('/material-requests/{id}', 'show')->name('material-requests.show');
        Route::get('/material-requests/{id}/print', 'print')->name('material-requests.print');
        Route::get('/material-requests/{id}/xml', 'downloadXml')->name('material-requests.xml-download');
        Route::get('/material-requests/{id}/check-xml', 'checkXmlSkips')->name('material-requests.xml-check');
        Route::get('/material-requests/{id}/edit-revision', 'revisionEdit')->name('material-requests.revision-edit');
        Route::post('/material-requests/{id}/resubmit', 'revisionResubmit')->name('material-requests.resubmit');
        Route::get('/barangs', [BarangController::class, 'index'])->name('barangs.index');
        Route::get('/barangs/search-api', [BarangController::class, 'searchApi'])->name('barangs.search-api');
        Route::post('/barangs', [BarangController::class, 'store'])->name('barangs.store');
        Route::post('/barangs/import', [BarangController::class, 'import'])->name('barangs.import');
        Route::post('/barangs/{id}', [BarangController::class, 'update'])->name('barangs.update');
        Route::delete('/barangs/{id}', [BarangController::class, 'destroy'])->name('barangs.destroy');
        Route::get('/approval/direksi/{id}/revision', 'revisionPage')->name('approval.revision-page');
        Route::post('/approval/direksi/{id}/revision', 'submitRevision')->name('approval.revision-submit');
    });

    // ==================== APPROVAL WORKFLOW ====================

    // Manager: Forward
    Route::middleware('role:Manager')->group(function () {
        Route::get('/approval/manager', [MaterialRequestController::class, 'managerIndex'])->name('approval.manager');
        Route::post('/approval/manager/{id}/forward', [MaterialRequestController::class, 'forward'])->name('approval.forward');
    });

    // FM/GM: Acknowledge
    Route::middleware('role:FM/GM')->group(function () {
        Route::get('/approval/fmgm', [MaterialRequestController::class, 'fmGmIndex'])->name('approval.fmgm');
        Route::post('/approval/fmgm/{id}/acknowledge', [MaterialRequestController::class, 'acknowledge'])->name('approval.acknowledge');
    });

    // Departemen (MTC / IT / HRD): Approval sebelum FM/GM
    Route::middleware('role:MTC')->group(function () {
        Route::get('/approval/mtc', [MaterialRequestController::class, 'departmentIndex'])->name('approval.mtc');
        Route::post('/approval/mtc/{id}/decision', [MaterialRequestController::class, 'departmentDecision'])->name('approval.mtc.decision');
    });
    Route::middleware('role:IT')->group(function () {
        Route::get('/approval/it', [MaterialRequestController::class, 'departmentIndex'])->name('approval.it');
        Route::post('/approval/it/{id}/decision', [MaterialRequestController::class, 'departmentDecision'])->name('approval.it.decision');
    });
    Route::middleware('role:HRD')->group(function () {
        Route::get('/approval/hrd', [MaterialRequestController::class, 'departmentIndex'])->name('approval.hrd');
        Route::post('/approval/hrd/{id}/decision', [MaterialRequestController::class, 'departmentDecision'])->name('approval.hrd.decision');
    });

    // Direksi: Decision
    Route::middleware('role:Direksi')->group(function () {
        Route::get('/approval/direksi', [MaterialRequestController::class, 'direksiIndex'])->name('approval.direksi');
        Route::post('/approval/direksi/{id}/decision', [MaterialRequestController::class, 'decision'])->name('approval.decision');
    });

    // Gudang: Verifikasi
    Route::middleware('role:Gudang')->group(function () {
        Route::get('/approval/gudang', [MaterialRequestController::class, 'gudangIndex'])->name('approval.gudang');
        Route::post('/approval/gudang/{id}/verify', [MaterialRequestController::class, 'verifyGudang'])->name('approval.verify-gudang');
        Route::get('/approval/gudang/{id}/edit', [MaterialRequestController::class, 'gudangEdit'])->name('approval.gudang-edit');
        Route::post('/approval/gudang/{id}/update', [MaterialRequestController::class, 'gudangUpdate'])->name('approval.gudang-update');
    });

    // Purchasing: Export
    Route::middleware('role:Purchasing')->group(function () {
        Route::get('/approval/purchasing', [MaterialRequestController::class, 'purchasingIndex'])->name('approval.purchasing');
        Route::get('/approval/purchasing/export', [MaterialRequestController::class, 'exportXml'])->name('approval.export');
        Route::get('/approval/purchasing/check-xml', [MaterialRequestController::class, 'checkXmlSkips'])->name('approval.export-check');
    });

    // Pengaturan (admin, Purchasing, Gudang)
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

        // Overview: admin bisa edit, Purchasing & Gudang hanya melihat
    Route::middleware('role:admin|Purchasing|Gudang')->get('/admin/overview', [AdminOverviewController::class, 'index'])->name('admin.overview');

        // Admin Panel
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/overview/{id}/edit', [AdminOverviewController::class, 'edit'])->name('overview.edit');
        Route::post('/overview/{id}/update', [AdminOverviewController::class, 'update'])->name('overview.update');
        Route::delete('/overview/{id}', [AdminOverviewController::class, 'destroy'])->name('overview.destroy');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users');
        Route::get('/users/{id}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('/users/{id}/approve', [AdminUserController::class, 'approve'])->name('users.approve');
        Route::post('/users/{id}/role', [AdminUserController::class, 'assignRole'])->name('users.role');
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])->name('users.destroy');
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

    // Profile
    Route::get('/profile', function () {
        $user = auth()->user()->load('roles');
        return Inertia::render('Profile/Index', ['user' => $user]);
    })->name('profile.index');

    // Auth Actions
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
