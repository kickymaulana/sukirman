<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return Inertia::render('Auth/Login');
    }

    public function redirectSso()
    {
        $query = http_build_query([
            'client_id' => config('services.sso.client_id'),
            'redirect_uri' => route('sso.callback'),
            'response_type' => 'code',
            'scope' => '',
        ]);
        return redirect(config('services.sso.base_url') . '/oauth/authorize?' . $query);
    }

    public function callbackSso(Request $request)
    {
        $code = $request->query('code');
        if (!$code) {
            return redirect()->route('login')->withErrors(['message' => 'Gagal autentikasi SSO']);
        }

        $verifySsl = app()->environment('local') ? false : true;

        $tokenRes = Http::withOptions(['verify' => $verifySsl])->asForm()->post(
            config('services.sso.base_url') . '/oauth/token',
            [
                'grant_type' => 'authorization_code',
                'client_id' => config('services.sso.client_id'),
                'client_secret' => config('services.sso.client_secret'),
                'redirect_uri' => route('sso.callback'),
                'code' => $code,
            ]
        );

        if ($tokenRes->failed()) {
            return redirect()->route('login')->withErrors(['message' => 'Gagal mendapatkan token SSO']);
        }

        $accessToken = $tokenRes->json('access_token');

        $userRes = Http::withOptions(['verify' => $verifySsl])
            ->withToken($accessToken)
            ->get(config('services.sso.base_url') . '/api/user');

        if ($userRes->failed()) {
            return redirect()->route('login')->withErrors(['message' => 'Gagal mengambil data user']);
        }

        $ssoUser = $userRes->json();
        $nik = $ssoUser['nik'] ?? null;

        if (!$nik) {
            return redirect()->route('login')->withErrors(['message' => 'Data NIK tidak ditemukan']);
        }

        $user = User::where('nik', $nik)->first();

        // Kalau NIK belum ada, coba cari berdasarkan email (bisa jadi pernah login pake NIK lain)
        if (!$user && !empty($ssoUser['email'])) {
            $user = User::where('email', $ssoUser['email'])->first();
            if ($user) {
                $user->update([
                    'nik' => $nik,
                    'name' => $ssoUser['name'] ?? $user->name,
                ]);
            }
        }

        if (!$user) {
            $user = User::create([
                'nik' => $nik,
                'name' => $ssoUser['name'] ?? 'User ' . $nik,
                'email' => $ssoUser['email'] ?? $nik . '@sso',
                'password' => Hash::make(Str::random(32)),
            ]);
        }

        if (!$user->is_approved) {
            // User baru: minta pilih posisi dulu sebelum admin mengaktifkan
            if (empty($user->requested_role)) {
                session()->put('pending_user_id', $user->id);
                return redirect()->route('pending-role');
            }
            return redirect()->route('login')->withErrors(['message' => 'Akun Anda belum diaktifkan. Silakan hubungi Admin.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function pendingRole()
    {
        $userId = session()->get('pending_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (!$user || $user->is_approved) {
            session()->forget('pending_user_id');
            return redirect()->route('login');
        }

        // Sudah pernah memilih posisi → kembali ke login dengan pesan menunggu
        if (!empty($user->requested_role)) {
            session()->forget('pending_user_id');
            return redirect()->route('login')->withErrors(['message' => 'Akun Anda belum diaktifkan. Silakan hubungi Admin.']);
        }

        return Inertia::render('Auth/PendingRole', [
            'user' => $user,
        ]);
    }

    public function submitRole(Request $request)
    {
        $userId = session()->get('pending_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (!$user || $user->is_approved) {
            session()->forget('pending_user_id');
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'role' => ['required', 'in:Supervisor,Manager,FM/GM,Direksi,Gudang,Purchasing'],
        ], [
            'role.required' => 'Pilih posisi/jabatan Anda terlebih dahulu.',
            'role.in' => 'Posisi yang dipilih tidak valid.',
        ]);

        $user->update(['requested_role' => $validated['role']]);
        session()->forget('pending_user_id');

        return redirect()->route('login')->with('success', 'Permintaan role terkirim! Silakan tunggu persetujuan Admin.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
