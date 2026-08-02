<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user || !$user->hasAnyRole(['Gudang', 'Purchasing', 'admin'])) {
                abort(403, 'Anda tidak memiliki akses.');
            }
            return $next($request);
        });
    }

    public function index(): Response
    {
        return Inertia::render('Settings/Index', [
            'branch_code' => Setting::get('accurate_branch_code', ''),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'branch_code' => ['nullable', 'string', 'max:50'],
        ]);

        Setting::updateOrCreate(
            ['key' => 'accurate_branch_code'],
            ['value' => $validated['branch_code'] ?? '']
        );

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
