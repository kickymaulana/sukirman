<?php

namespace App\Http\Controllers;

use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\ApprovalLog;
use App\Models\Setting;
use App\Models\Barang;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use App\Notifications\MrNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;

class MaterialRequestController extends Controller
{

    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $query = MaterialRequest::with(['items', 'approvalLogs.user', 'manager', 'fmGm', 'direksi'])
            ->where('user_id', auth()->id());

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('mr_number', 'like', "%{$search}%")
                  ->orWhere('factory', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhereHas('items', function ($itemQuery) use ($search) {
                      $itemQuery->where('item_name', 'like', "%{$search}%")
                                ->orWhere('item_code', 'like', "%{$search}%");
                  });
            });
        }

        $materialRequests = $query->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($mr) => $this->mapForList($mr));

        return Inertia::render('MaterialRequest/Index', [
            'requests' => $materialRequests,
            'filters'  => [
                'search' => $search ?? '',
            ],
        ]);
    }

    /**
     * Hapus MR oleh pengaju (hanya status tertentu), beserta relasi & notifikasinya.
     */
    public function destroyByOwner($id)
    {
        $mr = MaterialRequest::findOrFail($id);

        abort_if($mr->user_id !== auth()->id(), 403, 'Anda bukan pengaju MR ini.');

        $allowed = ['Pending Manager', 'Pending FM/GM', 'Pending Direksi', 'Pending MTC', 'Pending IT', 'Pending HRD', 'Revision'];
        abort_if(!in_array($mr->status_workflow, $allowed), 403, 'MR tidak bisa dihapus karena sudah diproses lebih lanjut.');

        DB::transaction(function () use ($mr, $id) {
            // Hapus semua notifikasi yang merujuk MR ini (semua user)
            DatabaseNotification::whereRaw("JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.mr_id')) = ?", [(string) $id])
                ->delete();

            // Hapus file foto item dari MinIO
            $mr->deleteS3Photos();

            // Hapus MR — items, approval_logs, item_po_lines ikut terhapus (cascade)
            $mr->delete();
        });

        return redirect()->route('material-requests.index')->with('success', "MR {$mr->mr_number} berhasil dihapus.");
    }

    /**
     * Stream gambar item dari penyimpanan (MinIO).
     */
    public function itemFoto($id)
    {
        $item = MaterialRequestItem::findOrFail($id);
        abort_if(empty($item->foto), 404);

        $disk = Storage::disk('s3');
        try {
            $content = $disk->get($item->foto);
        } catch (\Throwable $e) {
            abort(404);
        }

        return response($content, 200, [
            'Content-Type' => $disk->mimeType($item->foto) ?: 'image/jpeg',
        ]);
    }

    /**
     * Daftar MR yang bersangkutan dengan user (pengaju / target / pernah menindak).
     */
    public function myMrs(Request $request): Response
    {
        $search = $request->input('search');

        $query = MaterialRequest::with(['user', 'manager', 'fmGm', 'direksi', 'items'])
            ->where(function ($q) {
                $q->where('user_id', auth()->id())
                  ->orWhere('manager_id', auth()->id())
                  ->orWhere('fm_gm_id', auth()->id())
                  ->orWhere('direksi_id', auth()->id())
                  ->orWhereHas('approvalLogs', fn ($l) => $l->where('user_id', auth()->id()));
            });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('mr_number', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")
                          ->orWhere('nik', 'like', "%{$search}%"));
            });
        }

        $requests = $query->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(function ($mr) {
                $myId = auth()->id();
                $roles = [];
                if ($mr->user_id === $myId) $roles[] = 'Pengaju';
                if ($mr->manager_id === $myId) $roles[] = 'Manager';
                if ($mr->fm_gm_id === $myId) $roles[] = 'FM/GM';
                if ($mr->direksi_id === $myId) $roles[] = 'Direksi';

                return [
                    'id' => $mr->id,
                    'mr_number' => $mr->mr_number,
                    'jenis' => $mr->jenis,
                    'factory' => $mr->factory,
                    'status_workflow' => $mr->status_workflow,
                    'created_at' => $mr->created_at->format('d M Y'),
                    'pengaju' => $mr->user?->name,
                    'peran_saya' => $roles,
                ];
            });

        return Inertia::render('MaterialRequest/MyMrs', [
            'requests' => $requests,
            'filters' => ['search' => $search ?? ''],
        ]);
    }

    /**
     * Ubah MR menjadi struktur data untuk halaman daftar (progress + riwayat).
     */
    private function mapForList($mr): array
    {
        $status = $mr->status_workflow;
        $rejected = $status === 'Rejected';
        $revision = $status === 'Revision';

        // Tahapan alur: 0 Pengajuan, 1 Manager, 2 FM/GM, 3 Direksi, 4 Gudang, 5 Selesai
        $stage = match ($status) {
            'Pending Manager' => 1,
            'Pending FM/GM'   => 2,
            'Pending Direksi' => 3,
            'Verifikasi Gudang' => 4,
            'Fully Approved'  => 5,
            default           => 1,
        };

        $logs = $mr->approvalLogs->sortByDesc('id')->take(4)->map(fn ($l) => [
            'role' => $l->role,
            'action' => $l->action,
            'user_name' => $l->user?->name ?? '-',
            'time' => $l->created_at->format('d M H:i'),
        ])->values();

        return [
            'id' => $mr->id,
            'mr_number' => $mr->mr_number,
            'type' => $mr->type,
            'factory' => $mr->factory,
            'allocation' => $mr->allocation,
            'status_pembelian' => $mr->status_pembelian,
            'status_workflow' => $status,
            'created_at' => $mr->created_at->format('d M Y'),
            'items' => $mr->items->map(fn ($i) => [
                'id' => $i->id,
                'item_name' => $i->item_name,
                'qty' => $i->qty,
                'unit' => $i->unit,
            ])->values(),
            'stage' => $stage,
            'rejected' => $rejected,
            'revision' => $revision,
            'logs' => $logs,
        ];
    }

    /**
     * Halaman cetak MR sebagai bukti pengajuan.
     */
    public function print($id)
    {
        $mr = MaterialRequest::with([
            'items.departemen',
            'user.departemen',
            'manager',
            'fmGm',
            'direksi',
            'approvalLogs.user',
        ])->findOrFail($id);

        $logs = $mr->approvalLogs->sortByDesc('id');

        // Role departemen sesuai jenis MR
        $deptRole = match ($mr->jenis) {
            'MTC' => 'MTC',
            'IT'   => 'IT',
            'HRD'  => 'HRD',
            default => null,
        };

        // Persetujuan hanya dihitung jika ada LOG approval (bukan sekadar ditugaskan)
        $managerLog = $logs->where('role', 'Manager')->where('action', 'forward')->first();
        $fmGmLog    = $logs->where('role', 'FM/GM')->where('action', 'forward')->first();
        $direksiLog = $logs->where('role', 'Direksi')->where('action', 'approve')->first();

        $managerApproved = (bool) $managerLog;
        $fmGmApproved    = (bool) $fmGmLog;
        $direksiApproved = (bool) $direksiLog;

        $managerApproverName = $managerLog?->user?->name;
        $fmGmApproverName    = $fmGmLog?->user?->name;
        $direksiApproverName = $direksiLog?->user?->name;

        // Departemen: approve dari log departemen, ATAU skip otomatis jika manager sudah approve & punya role tsb
        $deptApproved = false;
        $deptApproverName = null;
        if ($deptRole) {
            $deptLog = $logs->where('role', $deptRole)->where('action', 'approve')->first();
            if ($deptLog) {
                $deptApproved = true;
                $deptApproverName = $deptLog->user?->name;
            } elseif ($managerLog) {
                $managerUser = User::find($mr->manager_id);
                if ($managerUser && $managerUser->hasRole($deptRole)) {
                    $deptApproved = true;
                    $deptApproverName = $managerUser->name;
                }
            }
        }

        return Inertia::render('MaterialRequest/Print', [
            'mr' => $mr,
            'deptRole' => $deptRole,
            'deptApproved' => $deptApproved,
            'deptApproverName' => $deptApproverName,
            'managerApproved' => $managerApproved,
            'managerApproverName' => $managerApproverName,
            'fmGmApproved' => $fmGmApproved,
            'fmGmApproverName' => $fmGmApproverName,
            'direksiApproved' => $direksiApproved,
            'direksiApproverName' => $direksiApproverName,
        ]);
    }

    /**
     * Menampilkan form pembuatan usulan baru (Create).
     */
    public function create(): Response
    {
        $managers = User::role('Manager')->get(['id', 'name', 'nik']);
        return Inertia::render('MaterialRequest/Create', [
            'managers' => $managers,
            'departemens' => \App\Models\Departemen::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    /**
     * Menyimpan data usulan Material Request beserta item-itemnya (Store).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:Lokal,Import'],
            'factory' => ['required', 'in:KIM,DALU 1,DALU 2'],
            'allocation' => ['required', 'in:Project,Proses'],
            'status_pembelian' => ['required', 'in:Urgent,Normal'],
            'jenis' => ['required', 'in:UMUM,MTC,IT,HRD'],
            'manager_id' => ['required', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_code' => ['nullable', 'string', 'max:50'],
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
        ], [
            'manager_id.required' => 'Pilih Manager tujuan terlebih dahulu.',
            'items.required' => 'Minimal harus menambahkan 1 item barang.',
            'items.*.item_name.required' => 'Nama barang wajib diisi.',
            'items.*.qty.required' => 'Jumlah (Qty) wajib diisi.',
            'items.*.unit.required' => 'Satuan wajib diisi.',
        ]);

        DB::transaction(function () use ($validated, $request) {
            // Format pendek: MR010508 (jam) — pastikan unik dengan menambahkan suffix bila dobel
            $base = 'MR' . date('His');
            $mrNumber = $base;
            $suffix = 0;
            while (MaterialRequest::where('mr_number', $mrNumber)->exists()) {
                $suffix++;
                $mrNumber = $base . $suffix;
            }

            $mr = MaterialRequest::create([
                'mr_number' => $mrNumber,
                'user_id' => auth()->id(),
                'manager_id' => $validated['manager_id'],
                'type' => $validated['type'],
                'factory' => $validated['factory'],
                'allocation' => $validated['allocation'],
                'status_pembelian' => $validated['status_pembelian'],
                'jenis' => $validated['jenis'],
                'status_workflow' => 'Pending Manager',
            ]);

            foreach ($validated['items'] as $index => $item) {
                $mrItem = $mr->items()->create([
                    'item_code' => isset($item['item_code']) ? mb_strtoupper($item['item_code']) : null,
                    'item_name' => mb_strtoupper($item['item_name']),
                    'specification' => isset($item['specification']) ? mb_strtoupper($item['specification']) : null,
                    'departemen_id' => $item['departemen_id'] ?? null,
                    'qty' => $item['qty'],
                    'unit' => mb_strtoupper($item['unit']),
                    'item_status' => $item['item_status'],
                    'monthly_usage' => $item['monthly_usage'] ?? 0,
                    'stock_on_hand' => $item['stock_on_hand'] ?? 0,
                    'purpose' => isset($item['purpose']) ? mb_strtoupper($item['purpose']) : null,
                ]);

                // Upload foto item ke MinIO
                $fotoFile = $request->file("items.{$index}.foto");
                if ($fotoFile) {
                    $path = Storage::disk('s3')->putFileAs(
                        "item-foto/{$mrItem->id}",
                        $fotoFile,
                        time() . '-' . \Illuminate\Support\Str::random(8) . '.jpg'
                    );
                    $mrItem->update(['foto' => $path]);
                }
            }
        });

        return redirect()->route('material-requests.index')->with('success', 'Usulan Material Request berhasil dibuat.');
    }

    /**
     * Menampilkan form edit MR (hanya status sebelum Direksi approve).
     */
    public function edit($id)
    {
        $mr = MaterialRequest::with('items')->findOrFail($id);

        $allowedStatuses = ['Pending Manager', 'Pending FM/GM', 'Pending Direksi', 'Pending MTC', 'Pending IT', 'Pending HRD'];
        abort_if(!in_array($mr->status_workflow, $allowedStatuses), 403, 'MR tidak bisa diedit pada status ini.');
        abort_if($mr->user_id !== auth()->id(), 403, 'Anda bukan pengaju MR ini.');

        $managers = User::role('Manager')->get(['id', 'name', 'nik']);

        return Inertia::render('MaterialRequest/EditPending', [
            'mr' => $mr,
            'managers' => $managers,
            'departemens' => \App\Models\Departemen::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    /**
     * Update MR (hanya status sebelum Direksi approve).
     */
    public function update(Request $request, $id)
    {
        $mr = MaterialRequest::findOrFail($id);

        $allowedStatuses = ['Pending Manager', 'Pending FM/GM', 'Pending Direksi', 'Pending MTC', 'Pending IT', 'Pending HRD'];
        abort_if(!in_array($mr->status_workflow, $allowedStatuses), 403, 'MR tidak bisa diedit pada status ini.');
        abort_if($mr->user_id !== auth()->id(), 403, 'Anda bukan pengaju MR ini.');

        $validated = $request->validate([
            'type' => ['required', 'in:Lokal,Import'],
            'factory' => ['required', 'in:KIM,DALU 1,DALU 2'],
            'allocation' => ['required', 'in:Project,Proses'],
            'status_pembelian' => ['required', 'in:Urgent,Normal'],
            'jenis' => ['required', 'in:UMUM,MTC,IT,HRD'],
            'manager_id' => ['required', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_code' => ['nullable', 'string', 'max:50'],
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
        ], [
            'manager_id.required' => 'Pilih Manager tujuan terlebih dahulu.',
            'items.required' => 'Minimal harus menambahkan 1 item barang.',
            'items.*.item_name.required' => 'Nama barang wajib diisi.',
            'items.*.qty.required' => 'Jumlah (Qty) wajib diisi.',
            'items.*.unit.required' => 'Satuan wajib diisi.',
        ]);

        DB::transaction(function () use ($validated, $request, $mr) {
            $mr->update([
                'manager_id' => $validated['manager_id'],
                'type' => $validated['type'],
                'factory' => $validated['factory'],
                'allocation' => $validated['allocation'],
                'status_pembelian' => $validated['status_pembelian'],
                'jenis' => $validated['jenis'],
                'status_workflow' => 'Pending Manager',
                'revision_notes' => null,
            ]);

            // Hapus item lama (termasuk foto di MinIO)
            foreach ($mr->items as $item) {
                $item->deleteS3Photo();
            }
            $mr->items()->delete();

            // Create item baru
            foreach ($validated['items'] as $index => $item) {
                $mrItem = $mr->items()->create([
                    'item_code' => isset($item['item_code']) ? mb_strtoupper($item['item_code']) : null,
                    'item_name' => mb_strtoupper($item['item_name']),
                    'specification' => isset($item['specification']) ? mb_strtoupper($item['specification']) : null,
                    'departemen_id' => $item['departemen_id'] ?? null,
                    'qty' => $item['qty'],
                    'unit' => mb_strtoupper($item['unit']),
                    'item_status' => $item['item_status'],
                    'monthly_usage' => $item['monthly_usage'] ?? 0,
                    'stock_on_hand' => $item['stock_on_hand'] ?? 0,
                    'purpose' => isset($item['purpose']) ? mb_strtoupper($item['purpose']) : null,
                ]);

                $fotoFile = $request->file("items.{$index}.foto");
                if ($fotoFile) {
                    $path = Storage::disk('s3')->putFileAs(
                        "item-foto/{$mrItem->id}",
                        $fotoFile,
                        time() . '-' . \Illuminate\Support\Str::random(8) . '.jpg'
                    );
                    $mrItem->update(['foto' => $path]);
                }
            }
        });

        return redirect()->route('material-requests.index')->with('success', 'MR berhasil diperbarui dan dikembalikan ke Pending Manager.');
    }

    // ============ MANAGER: Forward ke Direksi ============

    public function managerIndex()
    {
        $requests = MaterialRequest::with(['user', 'items'])
            ->where('status_workflow', 'Pending Manager')
            ->where('manager_id', auth()->id())
            ->latest()->paginate(10);

        $direksiUsers = User::role('Direksi')->get(['id', 'name']);

        return Inertia::render('Approval/Manager', [
            'requests' => $requests,
            'direksiUsers' => $direksiUsers,
        ]);
    }

    public function forward(Request $request, $id)
    {
        $mr = MaterialRequest::findOrFail($id);
        $request->validate([
            'action' => 'required|in:tolak,lanjut',
            'fm_gm_id' => 'required_if:action,lanjut|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        if ($request->action === 'tolak') {
            $mr->update(['status_workflow' => 'Rejected']);

            ApprovalLog::create([
                'material_request_id' => $mr->id,
                'user_id' => auth()->id(),
                'role' => 'Manager',
                'action' => 'reject',
                'notes' => $request->notes,
            ]);

            $mr->user->notify(new MrNotification($mr, "MR {$mr->mr_number} ditolak Manager: {$request->notes}"));

            return redirect()->route('approval.manager')->with('success', 'MR ditolak');
        }

        // Tentukan role departemen sesuai jenis MR
        $deptRole = match ($mr->jenis) {
            'MTC' => 'MTC',
            'IT'   => 'IT',
            'HRD'  => 'HRD',
            default => null,
        };

        // Jika MR non-UMUM dan Manager yang approve BUKAN role departemen itu → wajib langkah departemen.
        // Jika Manager sudah punya role departemen tersebut → langkah departemen otomatis terpenuhi (skip).
        $nextStatus = 'Pending FM/GM';
        if ($deptRole && !auth()->user()->hasRole($deptRole)) {
            $nextStatus = 'Pending ' . $deptRole;
        }

        $mr->update([
            'manager_id' => auth()->id(),
            'fm_gm_id' => $request->fm_gm_id,
            'status_workflow' => $nextStatus,
        ]);

        ApprovalLog::create([
            'material_request_id' => $mr->id,
            'user_id' => auth()->id(),
            'role' => 'Manager',
            'action' => 'forward',
            'notes' => $request->notes,
        ]);

        if ($nextStatus === 'Pending FM/GM') {
            $fmGmUser = User::find($request->fm_gm_id);
            if ($fmGmUser) {
                $fmGmUser->notify(new MrNotification($mr, "MR {$mr->mr_number} menunggu review Anda"));
            }
        } else {
            $deptUsers = User::role($deptRole)->get();
            Notification::send($deptUsers, new MrNotification($mr, "MR {$mr->mr_number} menunggu persetujuan {$deptRole}"));
        }

        return redirect()->route('approval.manager')->with('success', 'MR diteruskan');
    }

    // ============ DEPARTEMEN (MTC/IT/HRD): Approve / Reject ============

    private function deptRoleUser(): ?string
    {
        return collect(['MTC', 'IT', 'HRD'])->first(fn ($r) => auth()->user()->hasRole($r));
    }

    public function departmentIndex()
    {
        $deptRole = $this->deptRoleUser();

        $requests = MaterialRequest::with(['user', 'items', 'manager'])
            ->where('status_workflow', 'Pending ' . $deptRole)
            ->latest()->paginate(10);

        return Inertia::render('Approval/Departemen', [
            'requests' => $requests,
            'deptRole' => $deptRole,
        ]);
    }

    public function departmentDecision(Request $request, $id)
    {
        $mr = MaterialRequest::findOrFail($id);
        $deptRole = $this->deptRoleUser();

        abort_if($mr->status_workflow !== 'Pending ' . $deptRole, 404);

        $request->validate(['action' => 'required|in:approve,reject']);

        $route = match ($deptRole) {
            'MTC' => 'approval.mtc',
            'IT'   => 'approval.it',
            'HRD'  => 'approval.hrd',
            default => 'approval.manager',
        };

        if ($request->action === 'reject') {
            $mr->update(['status_workflow' => 'Rejected']);

            ApprovalLog::create([
                'material_request_id' => $mr->id,
                'user_id' => auth()->id(),
                'role' => $deptRole,
                'action' => 'reject',
                'notes' => $request->notes,
            ]);

            $mr->user->notify(new MrNotification($mr, "MR {$mr->mr_number} ditolak {$deptRole}: {$request->notes}"));

            return redirect()->route($route)->with('success', 'MR ditolak');
        }

        $mr->update(['status_workflow' => 'Pending FM/GM']);

        ApprovalLog::create([
            'material_request_id' => $mr->id,
            'user_id' => auth()->id(),
            'role' => $deptRole,
            'action' => 'approve',
            'notes' => $request->notes,
        ]);

        $fmGmUser = User::find($mr->fm_gm_id);
        if ($fmGmUser) {
            $fmGmUser->notify(new MrNotification($mr, "MR {$mr->mr_number} menunggu review Anda"));
        }

        return redirect()->route($route)->with('success', "MR disetujui {$deptRole}, diteruskan ke FM/GM");
    }

    // ============ FM/GM: Acknowledge ============

    public function fmGmIndex()
    {
        $requests = MaterialRequest::with(['user', 'items', 'manager'])
            ->where('status_workflow', 'Pending FM/GM')
            ->where('fm_gm_id', auth()->id())
            ->latest()->paginate(10);

        return Inertia::render('Approval/FmGm', [
            'requests' => $requests,
        ]);
    }

    public function acknowledge(Request $request, $id)
    {
        $mr = MaterialRequest::findOrFail($id);
        $request->validate([
            'action' => 'required|in:tolak,forward',
            'direksi_id' => 'required_if:action,forward|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        if ($request->action === 'tolak') {
            $mr->update(['status_workflow' => 'Rejected']);

            ApprovalLog::create([
                'material_request_id' => $mr->id,
                'user_id' => auth()->id(),
                'role' => 'FM/GM',
                'action' => 'reject',
                'notes' => $request->notes,
            ]);

            $mr->user->notify(new MrNotification($mr, "MR {$mr->mr_number} ditolak FM/GM: {$request->notes}"));

            return redirect()->route('approval.fmgm')->with('success', 'MR ditolak');
        }

        $mr->update([
            'status_workflow' => 'Pending Direksi',
            'direksi_id' => $request->direksi_id,
        ]);

        ApprovalLog::create([
            'material_request_id' => $mr->id,
            'user_id' => auth()->id(),
            'role' => 'FM/GM',
            'action' => 'forward',
            'notes' => $request->notes,
        ]);

        $direksiUser = User::find($request->direksi_id);
        if ($direksiUser) {
            $direksiUser->notify(new MrNotification($mr, "MR {$mr->mr_number} menunggu keputusan Anda"));
        }

        return redirect()->route('approval.fmgm')->with('success', 'MR diteruskan ke Direksi');
    }

    // ============ DIREKSI: Approve / Reject / Revision ============

    public function direksiIndex()
    {
        $requests = MaterialRequest::with(['user', 'items', 'manager'])
            ->where('status_workflow', 'Pending Direksi')
            ->where('direksi_id', auth()->id())
            ->latest()->paginate(10);

        return Inertia::render('Approval/Direksi', [
            'requests' => $requests,
        ]);
    }

    public function decision(Request $request, $id)
    {
        $mr = MaterialRequest::findOrFail($id);
        $request->validate([
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string',
        ]);

        $statusMap = [
            'approve' => 'Verifikasi Gudang',
            'reject' => 'Rejected',
        ];

        $mr->update(['status_workflow' => $statusMap[$request->action]]);

        ApprovalLog::create([
            'material_request_id' => $mr->id,
            'user_id' => auth()->id(),
            'role' => 'Direksi',
            'action' => $request->action,
            'notes' => $request->notes,
        ]);

        if ($request->action === 'approve') {
            $gudangUsers = User::role('Gudang')->get();
            Notification::send($gudangUsers, new MrNotification($mr, "MR {$mr->mr_number} disetujui, perlu verifikasi gudang"));
        } elseif ($request->action === 'reject') {
            $mr->user->notify(new MrNotification($mr, "MR {$mr->mr_number} ditolak: {$request->notes}"));
        }

        return redirect()->route('approval.direksi')->with('success', 'Keputusan diterapkan');
    }

    // ============ DIREKSI: Revisi per item ============

    public function revisionPage($id)
    {
        $mr = MaterialRequest::with(['user', 'items', 'manager'])->findOrFail($id);
        abort_if($mr->status_workflow !== 'Pending Direksi', 404);
        abort_if($mr->direksi_id !== auth()->id(), 403);

        return Inertia::render('Approval/Revision', [
            'mr' => $mr,
        ]);
    }

    public function submitRevision(Request $request, $id)
    {
        $mr = MaterialRequest::with('items')->findOrFail($id);
        abort_if($mr->status_workflow !== 'Pending Direksi', 404);
        abort_if($mr->direksi_id !== auth()->id(), 403);

        $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'exists:material_request_items,id'],
            'items.*.decision' => ['required', 'in:setuju,tolak,ganti'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        foreach ($request->items as $itemData) {
            $item = MaterialRequestItem::where('id', $itemData['id'])->where('material_request_id', $mr->id)->first();
            if ($item) {
                $item->update([
                    'direksi_decision' => $itemData['decision'],
                    'direksi_notes' => $itemData['notes'] ?? null,
                ]);
            }
        }

        $mr->update(['status_workflow' => 'Revision']);

        ApprovalLog::create([
            'material_request_id' => $mr->id,
            'user_id' => auth()->id(),
            'role' => 'Direksi',
            'action' => 'revision',
            'notes' => 'Revisi per item',
        ]);

        $mr->user->notify(new MrNotification($mr, "MR {$mr->mr_number} direvisi, mohon periksa catatan per item"));

        return redirect()->route('approval.direksi')->with('success', 'Revisi diterapkan');
    }

    // ============ GUDANG: Verifikasi Stok ============

    public function gudangIndex(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $query = MaterialRequest::with(['user.departemen', 'items', 'items.item_po_lines.user'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('mr_number', 'like', "%{$search}%")
                      ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")
                              ->orWhere('nik', 'like', "%{$search}%"));
                });
            })
            ->when($status, fn ($q) => $q->where('status_workflow', $status))
            ->latest();

        $requests = $query->paginate(10)->withQueryString()
            ->through(function ($mr) {
                $items = $mr->items;
                $total = $items->count();
                $nomorPos = collect();
                $poUsers = collect();
                foreach ($items as $it) {
                    $lines = $it->item_po_lines;
                    $nomorPos = $nomorPos->merge($lines->pluck('nomor_po')->filter());
                    foreach ($lines as $ln) {
                        if ($ln->user?->name) { $poUsers->push($ln->user->name); }
                    }
                }
                $poStatus = $nomorPos->count() > 0 ? ($nomorPos->count() === $total ? 'Sudah' : 'Sebagian') : 'Belum';

                return [
                    'id' => $mr->id,
                    'mr_number' => $mr->mr_number,
                    'jenis' => $mr->jenis,
                    'factory' => $mr->factory,
                    'status_workflow' => $mr->status_workflow,
                    'input_accurate' => $mr->input_accurate,
                    'po_status' => $poStatus,
                    'nomor_pos' => $nomorPos->unique()->values(),
                    'po_users' => $poUsers->unique()->values(),
                    'created_at' => $mr->created_at->format('d M Y'),
                    'pengaju' => $mr->user?->name,
                    'departemen' => $mr->user?->departemen?->nama,
                    'items_count' => $total,
                ];
            });

        return Inertia::render('Approval/Gudang', [
            'requests' => $requests,
            'filters' => ['search' => $search ?? '', 'status' => $status ?? ''],
            'allStatuses' => [
                'Pending Manager', 'Pending FM/GM', 'Pending Direksi',
                'Pending MTC', 'Pending IT', 'Pending HRD',
                'Verifikasi Gudang', 'Fully Approved', 'Purchasing', 'Rejected', 'Revision',
            ],
        ]);
    }

    /**
     * Halaman kerja Gudang: input item ke Accurate.
     */
    public function gudangInput($id)
    {
        $mr = MaterialRequest::with(['user', 'items.departemen'])->findOrFail($id);

        return Inertia::render('Approval/GudangInput', [
            'mr' => $mr,
        ]);
    }

    /**
     * Detail PO read-only (flathe lihat saja — untuk Gudang/admin/Purchasing).
     */
    public function poDetail($id)
    {
        $mr = MaterialRequest::with([
            'user.departemen',
            'items.departemen',
            'items.item_po_lines.user',
        ])->findOrFail($id);

        return Inertia::render('Approval/PoDetail', [
            'mr' => $mr,
        ]);
    }

    /**
     * Toggle per-item: sudah/belum diinput ke Accurate.
     */
    public function toggleItemAccurate(Request $request, $id)
    {
        $mrItem = MaterialRequestItem::findOrFail($id);
        $validated = $request->validate(['value' => 'required|in:Belum,Sudah']);
        $mrItem->update(['input_accurate' => $validated['value']]);
        return response()->json(['ok' => true, 'message' => "Item ditandai: {$validated['value']}"]);
    }

    public function verifyGudang(Request $request, $id)
    {
        $mr = MaterialRequest::findOrFail($id);
        $request->validate([
            'action' => 'required|in:tersedia,tidak_tersedia',
            'notes' => 'nullable|string',
        ]);

        $newStatus = $request->action === 'tersedia' ? 'Fully Approved' : 'Purchasing';
        $mr->update(['status_workflow' => $newStatus]);

        ApprovalLog::create([
            'material_request_id' => $mr->id,
            'user_id' => auth()->id(),
            'role' => 'Gudang',
            'action' => $request->action === 'tersedia' ? 'stock_available' : 'stock_unavailable',
            'notes' => $request->notes,
        ]);

        // Notifikasi
        if ($request->action === 'tersedia') {
            $mr->user->notify(new MrNotification($mr, "MR {$mr->mr_number} — barang tersedia di gudang"));
        } else {
            $purchasingUsers = User::role('Purchasing')->get();
            Notification::send($purchasingUsers, new MrNotification($mr, "MR {$mr->mr_number} — stok tidak ada, perlu pembelian"));
        }

        return redirect()->route('approval.gudang')->with('success', 'Verifikasi stok selesai');
    }

    public function gudangEdit($id)
    {
        $mr = MaterialRequest::with(['user', 'items', 'manager'])->findOrFail($id);
        abort_if($mr->status_workflow !== 'Verifikasi Gudang', 404);

        return Inertia::render('Approval/GudangEdit', [
            'mr' => $mr,
        ]);
    }

    public function gudangUpdate(Request $request, $id)
    {
        $mr = MaterialRequest::findOrFail($id);
        abort_if($mr->status_workflow !== 'Verifikasi Gudang', 404);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.item_code' => ['nullable', 'string', 'max:50'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.specification' => ['nullable', 'string'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['required', 'string', 'max:20'],
            'items.*.item_status' => ['required', 'in:Urgent,Normal,New,Replace'],
            'items.*.monthly_usage' => ['nullable', 'integer', 'min:0'],
            'items.*.stock_on_hand' => ['nullable', 'integer', 'min:0'],
            'items.*.purpose' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($mr, $validated) {
            $keepIds = [];
            foreach ($validated['items'] as $item) {
                if (!empty($item['id'])) {
                    $mrItem = MaterialRequestItem::find($item['id']);
                    if ($mrItem && $mrItem->material_request_id === $mr->id) {
                        $mrItem->update([
                            'item_code' => $item['item_code'] ?? null,
                            'item_name' => $item['item_name'],
                            'specification' => $item['specification'] ?? null,
                            'qty' => $item['qty'],
                            'unit' => $item['unit'],
                            'item_status' => $item['item_status'] ?? 'Normal',
                            'monthly_usage' => $item['monthly_usage'] ?? 0,
                            'stock_on_hand' => $item['stock_on_hand'] ?? 0,
                            'purpose' => $item['purpose'] ?? null,
                        ]);
                        $keepIds[] = $mrItem->id;
                    }
                } else {
                    $newItem = $mr->items()->create([
                        'item_code' => $item['item_code'] ?? null,
                        'item_name' => $item['item_name'],
                        'specification' => $item['specification'] ?? null,
                        'qty' => $item['qty'],
                        'unit' => $item['unit'],
                        'item_status' => $item['item_status'] ?? 'Normal',
                        'monthly_usage' => $item['monthly_usage'] ?? 0,
                        'stock_on_hand' => $item['stock_on_hand'] ?? 0,
                        'purpose' => $item['purpose'] ?? null,
                    ]);
                    $keepIds[] = $newItem->id;
                }
            }

            // Hapus item yang tidak dikirim di form (dihapus oleh Gudang)
            $mr->items()->whereNotIn('id', $keepIds)->delete();
        });

        ApprovalLog::create([
            'material_request_id' => $mr->id,
            'user_id' => auth()->id(),
            'role' => 'Gudang',
            'action' => 'gudang_edit',
            'notes' => 'MR diedit oleh Gudang (pembersihan data)',
        ]);

        return redirect()->route('approval.gudang')->with('success', 'MR berhasil diedit.');
    }

    /**
     * Gudang menandai apakah MR sudah diinput ke Accurate.
     * Saat "Sudah" → status workflow ikut pindah ke Purchasing.
     */
    public function toggleAccurate(Request $request, $id)
    {
        $mr = MaterialRequest::findOrFail($id);
        $validated = $request->validate(['value' => 'required|in:Belum,Sudah']);

        $mr->update([
            'input_accurate' => $validated['value'],
            'status_workflow' => $validated['value'] === 'Sudah' ? 'Purchasing' : 'Verifikasi Gudang',
        ]);

        return response()->json(['ok' => true, 'message' => "Ditandai: {$validated['value']}"]);
    }

    /**
     * Gudang mengisi ketersediaan & keterangan tiap item.
     */
    public function updateGudangItems(Request $request, $id)
    {
        $mr = MaterialRequest::findOrFail($id);
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.qty_tersedia' => ['nullable', 'integer', 'min:0'],
            'items.*.keterangan_gudang' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated['items'] as $item) {
            $mrItem = MaterialRequestItem::find($item['id']);
            if ($mrItem && $mrItem->material_request_id === $mr->id) {
                $mrItem->update([
                    'qty_tersedia' => $item['qty_tersedia'] ?? null,
                    'keterangan_gudang' => $item['keterangan_gudang'] ?? null,
                ]);
            }
        }

        return response()->json(['ok' => true, 'message' => 'Keterangan item diperbarui.']);
    }

    // ============ PURCHASING: Export Excel ============

    public function purchasingIndex(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $query = MaterialRequest::with(['user.departemen', 'items', 'items.item_po_lines.user'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('mr_number', 'like', "%{$search}%")
                      ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")
                              ->orWhere('nik', 'like', "%{$search}%"));
                });
            })
            ->when($status, fn ($q) => $q->where('status_workflow', $status))
            ->latest();

        $requests = $query->paginate(10)->withQueryString()
            ->through(function ($mr) {
                $items = $mr->items;
                $total = $items->count();
                $doneCount = 0;
                $hasPo = false;
                $nomorPos = collect();
                $poUsers = collect();
                foreach ($items as $it) {
                    $lines = $it->item_po_lines;
                    $covered = $lines->sum('qty');
                    if ($covered >= (int) $it->qty) { $doneCount++; }
                    if ($covered > 0) {
                        $hasPo = true;
                        $nomorPos = $nomorPos->merge($lines->pluck('nomor_po')->filter());
                    }
                    foreach ($lines as $ln) {
                        if ($ln->user?->name) { $poUsers->push($ln->user->name); }
                    }
                }

                $poStatus = $total === 0 ? 'Belum' : ($doneCount === $total ? 'Sudah' : ($hasPo ? 'Sebagian' : 'Belum'));

                return [
                    'id' => $mr->id,
                    'mr_number' => $mr->mr_number,
                    'jenis' => $mr->jenis,
                    'factory' => $mr->factory,
                    'status_workflow' => $mr->status_workflow,
                    'po_status' => $poStatus,
                    'nomor_pos' => $nomorPos->unique()->values(),
                    'po_users' => $poUsers->unique()->values(),
                    'created_at' => $mr->created_at->format('d M Y'),
                    'pengaju' => $mr->user?->name,
                    'departemen' => $mr->user?->departemen?->nama,
                    'items_count' => $total,
                ];
            });

        $topUsers = MaterialRequest::with('user')
            ->select('user_id', DB::raw('count(*) as total'))
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->take(5)
            ->get()
            ->map(fn ($t) => ['name' => $t->user?->name ?? '?', 'total' => $t->total]);

        return Inertia::render('Approval/Purchasing', [
            'requests' => $requests,
            'filters' => ['search' => $search ?? '', 'status' => $status ?? ''],
            'topUsers' => $topUsers,
            'allStatuses' => [
                'Pending Manager', 'Pending FM/GM', 'Pending Direksi',
                'Pending MTC', 'Pending IT', 'Pending HRD',
                'Verifikasi Gudang', 'Fully Approved', 'Purchasing', 'Rejected', 'Revision',
            ],
        ]);
    }

    /**
     * Halaman kerja Purchasing: input MR menjadi PO.
     */
    public function purchasingInput($id)
    {
        $mr = MaterialRequest::with(['user', 'items.departemen', 'items.item_po_lines'])->findOrFail($id);

        return Inertia::render('Approval/PurchasingInput', [
            'mr' => $mr,
        ]);
    }

    /**
     * Simpan baris PO per item (qty terbagi + nomor PO, bisa beda pemasok).
     */
    public function updateItemsPo(Request $request, $id)
    {
        $mr = MaterialRequest::findOrFail($id);
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.lines' => ['array'],
            'items.*.lines.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.lines.*.nomor_po' => ['nullable', 'string', 'max:100'],
            'items.*.lines.*.tgl_po' => ['nullable', 'date'],
            'items.*.lines.*.expected_date' => ['nullable', 'date'],
            'items.*.lines.*.tanggal_disetujui_direksi' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ]);

        foreach ($validated['items'] as $itemData) {
            $mrItem = MaterialRequestItem::find($itemData['id']);
            if (!$mrItem || $mrItem->material_request_id !== $mr->id) {
                continue;
            }

            $lines = $itemData['lines'] ?? [];
            $total = array_sum(array_column($lines, 'qty'));

            // Jaga agar total qty PO tidak melebihi qty permintaan
            if ($total > (int) $mrItem->qty) {
                return response()->json([
                    'error' => "Item {$mrItem->item_name}: total qty PO ({$total}) melebihi qty permintaan ({$mrItem->qty}).",
                ], 422);
            }

            $mrItem->item_po_lines()->delete();
            foreach ($lines as $line) {
                $tglSetuju = !empty($line['tanggal_disetujui_direksi']) ? $line['tanggal_disetujui_direksi'] : null;
                if ($tglSetuju) {
                    $tglSetuju = str_replace('T', ' ', $tglSetuju);
                }

                $mrItem->item_po_lines()->create([
                    'qty' => (int) $line['qty'],
                    'nomor_po' => !empty($line['nomor_po']) ? $line['nomor_po'] : null,
                    'tgl_po' => !empty($line['tgl_po']) ? $line['tgl_po'] : null,
                    'expected_date' => !empty($line['expected_date']) ? $line['expected_date'] : null,
                    'tanggal_disetujui_direksi' => $tglSetuju,
                    'user_id' => auth()->id(),
                ]);
            }
        }

        return response()->json(['ok' => true, 'message' => 'Baris PO diperbarui.']);
    }

    public function revisionEdit($id)
    {
        $mr = MaterialRequest::with('items')->findOrFail($id);
        abort_if($mr->status_workflow !== 'Revision', 404);
        abort_if($mr->user_id !== auth()->id(), 403);

        return Inertia::render('MaterialRequest/Edit', [
            'mr' => $mr,
            'thermalPintus' => [], // dummy, not used
            'ovens' => [],
            'customers' => [],
            'tinggiFormers' => [],
            'jamKeluarOvens' => [],
        ]);
    }

    public function revisionResubmit(Request $request, $id)
    {
        $mr = MaterialRequest::findOrFail($id);
        abort_if($mr->status_workflow !== 'Revision', 404);
        abort_if($mr->user_id !== auth()->id(), 403);

        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['required', 'string', 'max:20'],
            'items.*.specification' => ['nullable', 'string'],
            'items.*.purpose' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $mr) {
            // Hapus item yang ditolak oleh Direksi
            $mr->items()->where('direksi_decision', 'tolak')->delete();

            // Update item yang dikirim ulang (setuju & ganti) + buat item baru
            foreach ($request->items as $item) {
                if ((int) $item['id'] > 0) {
                    $mrItem = MaterialRequestItem::find($item['id']);
                    if ($mrItem && $mrItem->material_request_id === $mr->id) {
                        $mrItem->update([
                            'item_name' => $item['item_name'],
                            'specification' => $item['specification'] ?? null,
                            'qty' => $item['qty'],
                            'unit' => $item['unit'],
                            'purpose' => $item['purpose'] ?? null,
                            'direksi_decision' => null,
                            'direksi_notes' => null,
                        ]);
                    }
                } else {
                    $mr->items()->create([
                        'item_name' => $item['item_name'],
                        'specification' => $item['specification'] ?? null,
                        'qty' => $item['qty'],
                        'unit' => $item['unit'],
                        'purpose' => $item['purpose'] ?? null,
                        'direksi_decision' => null,
                        'direksi_notes' => null,
                    ]);
                }
            }

            $mr->update(['status_workflow' => 'Pending Manager', 'revision_notes' => null]);
        });

        return redirect()->route('material-requests.index')->with('success', 'MR dikirim ulang');
    }

    public function show($id)
    {
        $mr = MaterialRequest::with(['user', 'items', 'items.item_po_lines.user', 'approvalLogs.user', 'manager', 'direksi', 'fmGm'])->findOrFail($id);
        $user = auth()->user();
        $role = $user->getRoleNames()->first();

        // Role departemen yang dimiliki user (MTC/IT/HRD) — untuk aksi approval departemen
        $deptRole = collect(['MTC', 'IT', 'HRD'])->first(fn ($r) => $user->hasRole($r));

        // Data pendukung untuk action
        $direksiUsers = collect();
        $fmGmUsers = collect();
        if (strtolower($role) === 'fm/gm' && $mr->status_workflow === 'Pending FM/GM') {
            $direksiUsers = User::role('Direksi')->get(['id', 'name']);
        }
        if (strtolower($role) === 'manager' && $mr->status_workflow === 'Pending Manager') {
            $fmGmUsers = User::role('FM/GM')->get(['id', 'name', 'nik']);
        }

        return Inertia::render('Approval/MrDetail', [
            'mr' => $mr,
            'userRole' => $role,
            'deptRole' => $deptRole,
            'direksiUsers' => $direksiUsers,
            'fmGmUsers' => $fmGmUsers,
        ]);
    }

    public function exportXml()
    {
        $requests = MaterialRequest::with('items')
            ->whereIn('status_workflow', ['Fully Approved', 'Purchasing'])
            ->latest()->get();

        $xml = $this->buildAccurateXml(collect($requests));

        $filename = 'accurate-' . date('YmdHis') . '.xml';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function downloadXml($id)
    {
        $mr = MaterialRequest::with('items')->findOrFail($id);

        $xml = $this->buildAccurateXml(collect([$mr]));

        $filename = 'accurate-' . $mr->mr_number . '.xml';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function buildAccurateXml($mrs): string
    {
        $branch = Setting::get('accurate_branch_code', '');
        $validCodes = Barang::query()->pluck('kode_barang')->filter()->flip();

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><NMEXML/>');
        $xml->addAttribute('EximID', '0');
        $xml->addAttribute('BranchCode', $branch);
        $xml->addAttribute('ACCOUNTANTCOPYID', '');

        $trans = $xml->addChild('TRANSACTIONS');
        $trans->addAttribute('OnError', 'CONTINUE');

        $reqId = 1;
        foreach ($mrs as $mr) {
            $req = $trans->addChild('REQUISITION');
            $req->addAttribute('operation', 'Add');
            $req->addAttribute('REQUESTID', (string) $reqId);
            $req->addChild('TRANSACTIONID', '3000');
            $req->addChild('BRANCHCODEID', $branch);
            $req->addChild('REQNO', $mr->mr_number);
            $req->addChild('REQDATE', $mr->created_at->format('Y-m-d'));
            $req->addChild('DESCRIPTION', 'Permintaan barang dari sistem portal Laravel');

            $seq = 0;
            foreach ($mr->items as $item) {
                // Hanya export item yang kodenya valid (terdaftar di tabel Barang)
                $code = trim((string) ($item->item_code ?? ''));
                if ($code === '' || !$validCodes->has($code)) {
                    continue;
                }

                $line = $req->addChild('ITEMLINE');
                $line->addAttribute('operation', 'Add');
                $line->addChild('KeyID', (string) $seq);
                $line->addChild('SEQ', (string) $seq);
                $line->addChild('ITEMNO', $code);
                $line->addChild('QUANTITY', (string) $item->qty);
                $line->addChild('ITEMUNIT');
                $line->addChild('UNITRATIO', '1');
                $line->addChild('ITEMOVDESC', $item->item_name);
                $line->addChild('UNITPRICE', '0');
                $line->addChild('REQDATE', $mr->created_at->format('Y-m-d'));
                $line->addChild('NOTES', $item->purpose ?? '');
                $seq++;
            }
            $reqId++;
        }

        return $xml->asXML();
    }

    public function checkXmlSkips($id = null)
    {
        if ($id) {
            $mrs = collect([MaterialRequest::with('items')->findOrFail($id)]);
        } else {
            $mrs = MaterialRequest::with('items')
                ->whereIn('status_workflow', ['Fully Approved', 'Purchasing'])
                ->latest()->get();
        }

        $validCodes = Barang::query()->pluck('kode_barang')->filter()->flip();

        $skips = [];
        foreach ($mrs as $mr) {
            foreach ($mr->items as $item) {
                $code = trim((string) ($item->item_code ?? ''));
                if ($code === '' || !$validCodes->has($code)) {
                    $skips[] = [
                        'mr' => $mr->mr_number,
                        'item_name' => $item->item_name,
                        'item_code' => $code === '' ? '(kosong)' : $code,
                    ];
                }
            }
        }

        return response()->json([
            'skips' => $skips,
            'total' => count($skips),
        ]);
    }
}
