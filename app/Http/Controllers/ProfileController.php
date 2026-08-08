<?php

namespace App\Http\Controllers;

use App\Models\Departemen;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function index()
    {
        $user = auth()->user()->load('roles', 'departemen');

        return Inertia::render('Profile/Index', [
            'user' => $user,
            'departemens' => Departemen::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function edit()
    {
        $user = auth()->user()->load('roles', 'departemen');

        return Inertia::render('Profile/Edit', [
            'user' => $user,
            'departemens' => Departemen::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . auth()->id()],
            'departemen_id' => ['nullable', 'exists:departemens,id'],
        ]);

        auth()->user()->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'departemen_id' => $validated['departemen_id'] ?? null,
        ]);

        return redirect()->route('profile.index')->with('success', 'Profil berhasil diperbarui.');
    }
}