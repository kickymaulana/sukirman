<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->latest()->paginate(20);
        $roles = \Spatie\Permission\Models\Role::pluck('name');

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'allRoles' => $roles,
        ]);
    }

    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);
        $roles = \Spatie\Permission\Models\Role::pluck('name');
        return Inertia::render('Admin/UserDetail', [
            'user' => $user,
            'allRoles' => $roles,
        ]);
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
