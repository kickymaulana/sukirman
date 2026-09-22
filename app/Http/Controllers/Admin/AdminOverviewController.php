<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLog;
use App\Models\Departemen;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\User;
use App\Notifications\MrNotification;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
            'total' => MaterialRequest::count(),
            'hari_ini' => MaterialRequest::whereDate('created_at', today())->count(),
            'pengaju' => MaterialRequest::distinct('user_id')->count('user_id'),
            'berjalan' => MaterialRequest::whereNotIn('status_workflow', ['Fully Approved', 'Rejected'])->count(),
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

        $managers = User::role('Manager')->get(['id', 'name', 'nik']);
        $fmGms = User::role('FM/GM')->get(['id', 'name', 'nik']);
        $direksis = User::role('Direksi')->get(['id', 'name', 'nik']);

        return Inertia::render('Admin/Overview', [
            'requests' => $requests,
            'filters' => ['search' => $search ?? '', 'status' => $status ?? ''],
            'stats' => $stats,
            'statusCounts' => $statusCounts,
            'topUsers' => $topUsers,
            'managers' => $managers,
            'fmGms' => $fmGms,
            'direksis' => $direksis,
            'canEdit' => auth()->user()->hasRole('admin'),
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
            'Pending FM/GM' => 'FM/GM',
            'Pending Direksi' => 'Direksi',
            default => null,
        };

        if (! $role) {
            return response()->json(['error' => 'MR tidak sedang menunggu approval, tujuan tidak bisa diubah.'], 422);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $user = User::find($validated['user_id']);
        if (! $user->hasRole($role)) {
            return response()->json(['error' => "User tujuan harus berrole {$role}."], 422);
        }

        $oldManager = $mr->manager_id;
        $oldFmGm = $mr->fm_gm_id;
        $oldDireksi = $mr->direksi_id;

        if ($role === 'Manager') {
            $mr->update(['manager_id' => $user->id]);
        } elseif ($role === 'FM/GM') {
            $mr->update(['fm_gm_id' => $user->id]);
        } else {
            $mr->update(['direksi_id' => $user->id]);
        }

        ApprovalLog::create([
            'material_request_id' => $mr->id,
            'user_id' => auth()->id(),
            'role' => 'admin',
            'action' => 'admin_target_change',
            'notes' => "Tujuan {$role} diubah ke {$user->name}",
        ]);

        $this->notifyTargetChange($mr, $oldManager, $oldFmGm, $oldDireksi);

        return response()->json(['ok' => true, 'message' => "Tujuan ({$role}) diubah ke {$user->name}."]);
    }

    /**
     * Halaman edit MR khusus admin.
     */
    public function edit($id)
    {
        $mr = MaterialRequest::with(['user', 'manager', 'fmGm', 'direksi', 'items.item_po_lines', 'approvalLogs.user'])
            ->findOrFail($id);

        $targetRole = match ($mr->status_workflow) {
            'Pending Manager' => 'Manager',
            'Pending FM/GM' => 'FM/GM',
            'Pending Direksi' => 'Direksi',
            default => null,
        };

        return Inertia::render('Admin/MrEdit', [
            'mr' => $mr,
            'targetRole' => $targetRole,
            'managers' => User::role('Manager')->get(['id', 'name', 'nik']),
            'fmGms' => User::role('FM/GM')->get(['id', 'name', 'nik']),
            'direksis' => User::role('Direksi')->get(['id', 'name', 'nik']),
            'departemens' => Departemen::orderBy('nama')->get(['id', 'nama']),
            'hasPo' => $mr->items->contains(fn ($item) => $item->item_po_lines->isNotEmpty()),
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
        $validated = $request->validate([
            'type' => ['required', 'in:Lokal,Import'],
            'factory' => ['required', 'in:KIM,DALU 1,DALU 2'],
            'allocation' => ['required', 'in:Project,Proses'],
            'status_pembelian' => ['required', 'in:Urgent,Normal'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'distinct'],
            'items.*.item_code' => ['nullable', 'string', 'max:50'],
            'items.*.type' => ['required', 'in:Lokal,Import'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.specification' => ['nullable', 'string'],
            'items.*.departemen_id' => ['nullable', 'exists:departemens,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['required', 'string', 'max:20'],
            'items.*.item_status' => ['required', 'in:Urgent,Normal,New,Replace'],
            'items.*.monthly_usage' => ['nullable', 'integer', 'min:0'],
            'items.*.stock_on_hand' => ['nullable', 'integer', 'min:0'],
            'items.*.purpose' => ['nullable', 'string'],
            'items.*.foto' => ['nullable', 'image', 'max:10240'],
            'items.*.remove_foto' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($validated, $request, $id) {
            $mr = MaterialRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            $existingItems = $mr->items()->lockForUpdate()->get()->keyBy('id');

            if (MaterialRequestItem::where('material_request_id', $mr->id)->whereHas('item_po_lines')->exists()) {
                throw ValidationException::withMessages(['items' => 'MR sudah memiliki PO sehingga item tidak dapat dikoreksi.']);
            }

            $submittedIds = collect($validated['items'])->pluck('id')->filter()->map(fn ($itemId) => (int) $itemId);
            if ($submittedIds->diff($existingItems->keys())->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => 'Terdapat item yang bukan milik MR ini.']);
            }

            $mr->update([
                'type' => $validated['type'],
                'factory' => $validated['factory'],
                'allocation' => $validated['allocation'],
                'status_pembelian' => $validated['status_pembelian'],
            ]);

            foreach ($existingItems->except($submittedIds->all()) as $deletedItem) {
                $deletedItem->deleteS3Photo();
                $deletedItem->delete();
            }

            foreach ($validated['items'] as $index => $itemData) {
                $item = isset($itemData['id'])
                    ? $existingItems->get((int) $itemData['id'])
                    : $mr->items()->create([]);

                $item->update([
                    'item_code' => filled($itemData['item_code'] ?? null) ? mb_strtoupper($itemData['item_code']) : null,
                    'type' => $itemData['type'],
                    'item_name' => mb_strtoupper($itemData['item_name']),
                    'specification' => filled($itemData['specification'] ?? null) ? mb_strtoupper($itemData['specification']) : null,
                    'departemen_id' => $itemData['departemen_id'] ?? null,
                    'qty' => $itemData['qty'],
                    'unit' => mb_strtoupper($itemData['unit']),
                    'item_status' => $itemData['item_status'],
                    'monthly_usage' => $itemData['monthly_usage'] ?? 0,
                    'stock_on_hand' => $itemData['stock_on_hand'] ?? 0,
                    'purpose' => filled($itemData['purpose'] ?? null) ? mb_strtoupper($itemData['purpose']) : null,
                ]);

                $fotoFile = $request->file("items.{$index}.foto");
                if ($fotoFile) {
                    $oldPhoto = $item->foto;
                    $path = Storage::disk('s3')->putFileAs(
                        "item-foto/{$item->id}",
                        $fotoFile,
                        time().'-'.Str::random(8).'.jpg'
                    );
                    $item->update(['foto' => $path]);
                    if ($oldPhoto) {
                        Storage::disk('s3')->delete($oldPhoto);
                    }
                } elseif (($itemData['remove_foto'] ?? false) && $item->foto) {
                    $item->deleteS3Photo();
                    $item->update(['foto' => null]);
                }
            }

            ApprovalLog::create([
                'material_request_id' => $mr->id,
                'user_id' => auth()->id(),
                'role' => 'admin',
                'action' => 'admin_item_edit',
                'notes' => 'Data MR dan item dikoreksi oleh admin tanpa mengubah status workflow',
            ]);
        });

        return redirect()->route('admin.overview.edit', $id)->with('success', 'MR berhasil dikoreksi tanpa mengubah status.');
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
            'Pending FM/GM' => $mr->fm_gm_id,
            'Pending Direksi' => $mr->direksi_id,
            default => null,
        };
        $oldTargetId = match ($status) {
            'Pending Manager' => $oldManager,
            'Pending FM/GM' => $oldFmGm,
            'Pending Direksi' => $oldDireksi,
            default => null,
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

            // 2. Hapus file foto item dari MinIO
            $mr->deleteS3Photos();

            // 3. Hapus MR — items & approval_logs ikut terhapus otomatis (cascade)
            $mr->delete();
        });

        return redirect()->route('admin.overview')
            ->with('status', "MR {$mr->mr_number} berhasil dihapus beserta riwayat & notifikasinya.");
    }
}
