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
        return response()->json(['ok' => true, 'message' => "User {$user->name} diaktifkan"]);
    }

    public function assignRole(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $request->validate(['role' => 'required|exists:roles,name']);
        $user->syncRoles([$request->role]);
        return response()->json(['ok' => true, 'message' => "Role {$user->name} diubah ke {$request->role}"]);
    }

    public function destroy($id)
    {
        User::findOrFail($id)->delete();
        return response()->json(['ok' => true, 'message' => 'User dihapus']);
    }
}
