<?php

namespace App\Http\Controllers;

use App\Models\MaterialRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = auth()->id();

        // 1. Hitung ringkasan status berdasarkan workflow
        $summary = [
            'pending'    => MaterialRequest::where('user_id', $userId)->where('status_workflow', 'Pending Manager')->count(),
            'processing' => MaterialRequest::where('user_id', $userId)->whereIn('status_workflow', ['Pending FM/GM', 'Pending Direksi', 'Verifikasi Gudang'])->count(),
            'approved'   => MaterialRequest::where('user_id', $userId)->where('status_workflow', 'Fully Approved')->count(),
            'rejected'   => MaterialRequest::where('user_id', $userId)->where('status_workflow', 'Rejected')->count(),
        ];

        // 2. Ambil 5 usulan Material Request terbaru milik user
        $recentRequests = MaterialRequest::with('items')
            ->where('user_id', $userId)
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($mr) {
                // Ambil nama item pertama sebagai judul representatif card
                $firstItem = $mr->items->first();
                $title = $firstItem ? $firstItem->item_name : 'Material Request';

                if ($mr->items->count() > 1) {
                    $title .= ' (+' . ($mr->items->count() - 1) . ' item)';
                }

                return [
                    'id'       => $mr->id,
                    'code'     => $mr->mr_number,
                    'title'    => $title,
                    'category' => $mr->factory . ' • ' . $mr->allocation,
                    'date'     => $mr->created_at->format('d M Y'),
                    'status'   => $mr->status_workflow,
                ];
            });

        $user = auth()->user();
        $roles = $user->getRoleNames();

        $pendingCount = null;
        $role = $roles->first();
        if (in_array($role, ['manager', 'Manager'])) {
            $pendingCount = MaterialRequest::where('status_workflow', 'Pending Manager')->count();
        } elseif (in_array($role, ['fm/gm', 'FM/GM'])) {
            $pendingCount = MaterialRequest::where('status_workflow', 'Pending FM/GM')->count();
        } elseif (in_array($role, ['direksi', 'Direksi'])) {
            $pendingCount = MaterialRequest::where('status_workflow', 'Pending Direksi')->where('direksi_id', $user->id)->count();
        } elseif (in_array($role, ['gudang', 'Gudang'])) {
            $pendingCount = MaterialRequest::where('status_workflow', 'Verifikasi Gudang')->count();
        } elseif (in_array($role, ['purchasing', 'Purchasing'])) {
            $pendingCount = MaterialRequest::whereIn('status_workflow', ['Fully Approved', 'Purchasing'])->count();
        }

        return Inertia::render('Dashboard', [
            'user' => [
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $role,
            ],
            'pending_count' => $pendingCount,
            'summary' => $summary,
            'recentRequests' => $recentRequests,
        ]);
    }
}
