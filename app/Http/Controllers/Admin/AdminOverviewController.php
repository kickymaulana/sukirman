<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialRequest;
use App\Models\User;
use App\Notifications\MrNotification;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class AdminOverviewController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->search;
        $status = $request->status;

        $query = MaterialRequest::with(['user', 'manager', 'fmGm', 'direksi', 'items']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('mr_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('name', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%");
                  });
            });
        }

        if ($status) {
            $query->where('status_workflow', $status);
        }

        $requests = $query->latest()->paginate(15)->withQueryString();

        // ===== STATISTIK =====
        $stats = [
            'total'      => MaterialRequest::count(),
            'hari_ini'   => MaterialRequest::whereDate('created_at', today())->count(),
            'pengaju'    => MaterialRequest::distinct('user_id')->count('user_id'),
            'berjalan'   => MaterialRequest::whereNotIn('status_workflow', ['Fully Approved', 'Rejected'])->count(),
        ];

        $statusCounts = MaterialRequest::select('status_workflow', DB::raw('count(*) as total'))
            ->groupBy('status_workflow')
            ->get()
            ->keyBy('status_workflow');

        $topUsers = MaterialRequest::with('user')
            ->select('user_id', DB::raw('count(*) as total'))
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        $managers  = User::role('Manager')->get(['id', 'name', 'nik']);
        $fmGms     = User::role('FM/GM')->get(['id', 'name', 'nik']);
        $direksis  = User::role('Direksi')->get(['id', 'name', 'nik']);

        return Inertia::render('Admin/Overview', [
            'requests' => $requests,
            'filters'  => ['search' => $search ?? '', 'status' => $status ?? ''],
            'stats'    => $stats,
            'statusCounts' => $statusCounts,
            'topUsers' => $topUsers,
            'managers' => $managers,
            'fmGms'    => $fmGms,
            'direksis' => $direksis,
            'canEdit'  => auth()->user()->hasRole('admin'),
            'canGudang' => auth()->user()->hasRole('Gudang'),
            'allStatuses' => [
                'Pending Manager', 'Pending FM/GM', 'Pending Direksi',
                'Verifikasi Gudang', 'Fully Approved', 'Purchasing', 'Rejected', 'Revision',
            ],
        ]);
    }

    /**
     * Ubah tujuan approval sesuai status workflow MR.
     */
    public function updateTarget(Request $request, $id)
    {
        $mr = MaterialRequest::findOrFail($id);

        $role = match ($mr->status_workflow) {
            'Pending Manager' => 'Manager',
            'Pending FM/GM'   => 'FM/GM',
            'Pending Direksi' => 'Direksi',
            default           => null,
        };

        if (!$role) {
            return response()->json(['error' => 'MR tidak sedang menunggu approval, tujuan tidak bisa diubah.'], 422);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $user = User::find($validated['user_id']);
        if (!$user->hasRole($role)) {
            return response()->json(['error' => "User tujuan harus berrole {$role}."], 422);
        }

        if ($role === 'Manager') {
            $mr->update(['manager_id' => $user->id]);
        } elseif ($role === 'FM/GM') {
            $mr->update(['fm_gm_id' => $user->id]);
        } elseif ($role === 'Direksi') {
            $mr->update(['direksi_id' => $user->id]);
        }

        return response()->json(['ok' => true, 'message' => "Tujuan ({$role}) diubah ke {$user->name}."]);
    }

    /**
     * Halaman edit MR khusus admin.
     */
    public function edit($id)
    {
        $mr = MaterialRequest::with(['user', 'manager', 'fmGm', 'direksi', 'items', 'approvalLogs.user'])
            ->findOrFail($id);

        $targetRole = match ($mr->status_workflow) {
            'Pending Manager' => 'Manager',
            'Pending FM/GM'   => 'FM/GM',
            'Pending Direksi' => 'Direksi',
            default           => null,
        };

        return Inertia::render('Admin/MrEdit', [
            'mr' => $mr,
            'targetRole' => $targetRole,
            'managers' => User::role('Manager')->get(['id', 'name', 'nik']),
            'fmGms'    => User::role('FM/GM')->get(['id', 'name', 'nik']),
            'direksis' => User::role('Direksi')->get(['id', 'name', 'nik']),
            'allStatuses' => [
                'Pending Manager', 'Pending FM/GM', 'Pending Direksi',
                'Pending MTC', 'Pending IT', 'Pending HRD',
                'Verifikasi Gudang', 'Fully Approved', 'Purchasing', 'Rejected', 'Revision',
            ],
        ]);
    }

    /**
     * Simpan perubahan MR oleh admin.
     */
    public function update(Request $request, $id)
    {
        $mr = MaterialRequest::findOrFail($id);

        // Target lama sebelum diubah (untuk penanganan notifikasi)
        $oldManager = $mr->manager_id;
        $oldFmGm    = $mr->fm_gm_id;
        $oldDireksi = $mr->direksi_id;

        $validated = $request->validate([
            'type'             => ['required', 'in:Lokal,Import'],
            'factory'          => ['required', 'in:KIM,DALU 1,DALU 2'],
            'allocation'       => ['required', 'in:Project,Proses'],
            'status_pembelian' => ['required', 'in:Urgent,Normal'],
            'status_workflow'  => ['required', 'string', 'max:50'],
            'manager_id'       => ['nullable', 'exists:users,id'],
            'fm_gm_id'         => ['nullable', 'exists:users,id'],
            'direksi_id'       => ['nullable', 'exists:users,id'],
        ]);

        $mr->update([
            'type'             => $validated['type'],
            'factory'          => $validated['factory'],
            'allocation'       => $validated['allocation'],
            'status_pembelian' => $validated['status_pembelian'],
            'status_workflow'  => $validated['status_workflow'],
            'manager_id'       => $validated['manager_id'],
            'fm_gm_id'         => $validated['fm_gm_id'],
            'direksi_id'       => $validated['direksi_id'],
        ]);

        \App\Models\ApprovalLog::create([
            'material_request_id' => $mr->id,
            'user_id' => auth()->id(),
            'role' => 'admin',
            'action' => 'admin_edit',
            'notes' => 'MR diedit oleh admin',
        ]);

        $this->notifyTargetChange($mr, $oldManager, $oldFmGm, $oldDireksi);

        return redirect()->route('admin.overview.edit', $id)->with('success', 'MR berhasil diperbarui.');
    }

    /**
     * Kondisikan notifikasi saat admin mengubah tujuan MR.
     */
    private function notifyTargetChange($mr, $oldManager, $oldFmGm, $oldDireksi)
    {
        $status = $mr->status_workflow;

        // Target relevan baru & lama sesuai status
        $newTargetId = match ($status) {
            'Pending Manager' => $mr->manager_id,
            'Pending FM/GM'   => $mr->fm_gm_id,
            'Pending Direksi' => $mr->direksi_id,
            default           => null,
        };
        $oldTargetId = match ($status) {
            'Pending Manager' => $oldManager,
            'Pending FM/GM'   => $oldFmGm,
            'Pending Direksi' => $oldDireksi,
            default           => null,
        };

        // 1. Notifikasi ke target baru (jika diubah)
        if ($newTargetId && (int) $newTargetId !== (int) $oldTargetId) {
            $newUser = User::find($newTargetId);
            if ($newUser) {
                $newUser->notify(new MrNotification($mr, "MR {$mr->mr_number} dialihkan ke Anda — menunggu persetujuan Anda."));
            }
        }

        // 2. Target lama: tandai notifikasi "menunggu" yang lama sudah dibaca + info dialihkan
        if ($oldTargetId && (int) $oldTargetId !== (int) $newTargetId) {
            DatabaseNotification::whereRaw("JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.mr_id')) = ?", [(string) $mr->id])
                ->where('notifiable_id', $oldTargetId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            $oldUser = User::find($oldTargetId);
            if ($oldUser) {
                $oldUser->notify(new MrNotification($mr, "MR {$mr->mr_number} telah dialihkan ke approver lain."));
            }
        }

        // 3. Status departemen (Pending MTC/IT/HRD) → notifikasi semua user dengan role itu
        if (in_array($status, ['Pending MTC', 'Pending IT', 'Pending HRD'])) {
            $deptRole = str_replace('Pending ', '', $status);
            $deptUsers = User::role($deptRole)->get();
            Notification::send($deptUsers, new MrNotification($mr, "MR {$mr->mr_number} menunggu persetujuan {$deptRole}."));
        }
    }

    /**
     * Hapus MR sekaligus membersihkan relasi & notifikasi agar tidak ada yang tertinggal.
     */
    public function destroy($id)
    {
        $mr = MaterialRequest::findOrFail($id);

        DB::transaction(function () use ($mr, $id) {
            // 1. Hapus notifikasi yang merujuk MR ini (untuk semua user, misal yang masih ada di Direksi)
            DatabaseNotification::whereRaw("JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.mr_id')) = ?", [(string) $id])
                ->delete();

            // 2. Hapus MR — items & approval_logs ikut terhapus otomatis (cascade)
            $mr->delete();
        });

        return redirect()->route('admin.overview')
            ->with('status', "MR {$mr->mr_number} berhasil dihapus beserta riwayat & notifikasinya.");
    }
}
