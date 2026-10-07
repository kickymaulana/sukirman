<?php

namespace App\Http\Controllers;

use App\Models\MaterialRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = auth()->id();

        // 1. Ambil 5 usulan Material Request terbaru milik user
        $recentRequests = MaterialRequest::with('items')
            ->where('user_id', $userId)
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($mr) {
                $firstItem = $mr->items->first();

                return [
                    'id' => $mr->id,
                    'code' => $mr->mr_number,
                    'title' => $firstItem ? $firstItem->item_name : 'Material Request',
                    'date' => $mr->created_at->format('d M Y'),
                    'status' => $mr->status_workflow,
                ];
            });

        $user = auth()->user();
        $roles = $user->getRoleNames();

        $role = $roles->first();
        $pendingCounts = [];

        foreach ($roles as $userRole) {
            $pendingCounts[$userRole] = match ($userRole) {
                'admin', 'Admin' => User::where('is_approved', false)->count(),
                'manager', 'Manager' => MaterialRequest::where('manager_id', $user->id)
                    ->where('status_workflow', 'Pending Manager')->count(),
                'fm/gm', 'FM/GM' => MaterialRequest::where('fm_gm_id', $user->id)
                    ->where('status_workflow', 'Pending FM/GM')->count(),
                'MTC', 'IT', 'HRD', 'QMR' => MaterialRequest::where('status_workflow', 'Pending '.$userRole)->count(),
                'direksi', 'Direksi' => MaterialRequest::where('direksi_id', $user->id)
                    ->where('status_workflow', 'Pending Direksi')->count(),
                'gudang', 'Gudang' => MaterialRequest::where('status_workflow', 'Verifikasi Gudang')->count(),
                'purchasing', 'Purchasing' => MaterialRequest::whereIn('status_workflow', ['Fully Approved', 'Purchasing'])->count(),
                default => 0,
            };
        }

        return Inertia::render('Dashboard', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
                'roles' => $roles->values(),
            ],
            'pending_counts' => $pendingCounts,
            'recentRequests' => $recentRequests,
        ]);
    }
}
