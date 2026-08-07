<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Departemen;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $query = User::with('roles', 'departemen');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(20)->withQueryString();
        $roles = \Spatie\Permission\Models\Role::pluck('name');

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'allRoles' => $roles,
            'filters' => ['search' => $search ?? ''],
        ]);
    }

    public function show($id)
    {
        $user = User::with('roles', 'departemen')->findOrFail($id);
        $roles = \Spatie\Permission\Models\Role::pluck('name');
        $departemens = Departemen::orderBy('nama')->get(['id', 'nama']);
        return Inertia::render('Admin/UserDetail', [
            'user' => $user,
            'allRoles' => $roles,
            'departemens' => $departemens,
        ]);
    }

    public function updateDepartemen(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $validated = $request->validate([
            'departemen_id' => ['nullable', 'exists:departemens,id'],
        ]);
        $user->update(['departemen_id' => $validated['departemen_id'] ?? null]);
        return response()->json(['ok' => true, 'message' => 'Departemen diperbarui']);
    }

    public function approve($id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_approved' => true]);

        // Auto-assign role yang diminta user saat registrasi (jika valid)
        if (!empty($user->requested_role) && \Spatie\Permission\Models\Role::where('name', $user->requested_role)->exists()) {
            $user->syncRoles([$user->requested_role]);
        }

        return response()->json(['ok' => true, 'message' => "User {$user->name} diaktifkan"]);
    }

    public function assignRole(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['exists:roles,name'],
        ]);
        $user->syncRoles($request->roles);
        return response()->json(['ok' => true, 'message' => "Role {$user->name} diperbarui: " . implode(', ', $request->roles)]);
    }

    public function destroy($id)
    {
        User::findOrFail($id)->delete();
        return response()->json(['ok' => true, 'message' => 'User dihapus']);
    }
}
