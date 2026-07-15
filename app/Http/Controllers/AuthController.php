<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validasi input dari Frontend Flet
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required', // Contoh: 'Windows Client', 'Android App'
        ]);

        // 2. Cari user berdasarkan email
        $user = User::where('email', $request->email)->first();

        // 3. Validasi kecocokan user dan password
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang Anda masukkan salah.'],
            ]);
        }

        // 4. Buat token baru menggunakan Sanctum
        $token = $user->createToken($request->device_name)->plainTextToken;

        // 5. Kembalikan response json
        return response()->json([
            'status' => 'success',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        // Menghapus token yang sedang digunakan untuk login saat ini
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil logout dan token telah dihapus.'
        ]);
    }
}
