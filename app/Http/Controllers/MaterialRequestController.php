<?php

namespace App\Http\Controllers;

use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\ApprovalLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use App\Notifications\MrNotification;
use Illuminate\Support\Facades\Notification;

class MaterialRequestController extends Controller
{

    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $query = MaterialRequest::with('items')
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

        $materialRequests = $query->latest()->paginate(10)->withQueryString();

        return Inertia::render('MaterialRequest/Index', [
            'requests' => $materialRequests,
            'filters'  => [
                'search' => $search ?? '',
            ],
        ]);
    }

    /**
     * Menampilkan form pembuatan usulan baru (Create).
     */
    public function create(): Response
    {
        return Inertia::render('MaterialRequest/Create');
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_code' => ['nullable', 'string', 'max:50'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.specification' => ['nullable', 'string'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['required', 'string', 'max:20'],
            'items.*.item_status' => ['required', 'in:Urgent,Normal,New,Replace'],
            'items.*.monthly_usage' => ['nullable', 'integer', 'min:0'],
            'items.*.stock_on_hand' => ['nullable', 'integer', 'min:0'],
            'items.*.purpose' => ['nullable', 'string'],
        ], [
            'items.required' => 'Minimal harus menambahkan 1 item barang.',
            'items.*.item_name.required' => 'Nama barang wajib diisi.',
            'items.*.qty.required' => 'Jumlah (Qty) wajib diisi.',
            'items.*.unit.required' => 'Satuan wajib diisi.',
        ]);

        DB::transaction(function () use ($validated) {
            // Auto generate MR Number: MR-YmdHis (misal: MR-20260721143000)
            $mrNumber = 'MR-' . date('YmdHis');

            $mr = MaterialRequest::create([
                'mr_number' => $mrNumber,
                'user_id' => auth()->id(),
                'type' => $validated['type'],
                'factory' => $validated['factory'],
                'allocation' => $validated['allocation'],
                'status_pembelian' => $validated['status_pembelian'],
                'status_workflow' => 'Pending Manager', // Default workflow stage
            ]);

            foreach ($validated['items'] as $item) {
                $mr->items()->create([
                    'item_code' => $item['item_code'] ?? null,
                    'item_name' => $item['item_name'],
                    'specification' => $item['specification'] ?? null,
                    'qty' => $item['qty'],
                    'unit' => $item['unit'],
                    'item_status' => $item['item_status'],
                    'monthly_usage' => $item['monthly_usage'] ?? 0,
                    'stock_on_hand' => $item['stock_on_hand'] ?? 0,
                    'purpose' => $item['purpose'] ?? null,
                ]);
            }
        });

        return redirect()->route('material-requests.index')->with('success', 'Usulan Material Request berhasil dibuat.');
    }

    // ============ MANAGER: Forward ke Direksi ============

    public function managerIndex()
    {
        $requests = MaterialRequest::with(['user', 'items'])
            ->where('status_workflow', 'Pending Manager')
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

        $mr->update([
            'manager_id' => auth()->id(),
            'status_workflow' => 'Pending FM/GM',
        ]);

        ApprovalLog::create([
            'material_request_id' => $mr->id,
            'user_id' => auth()->id(),
            'role' => 'Manager',
            'action' => 'forward',
            'notes' => $request->notes,
        ]);

        $fmGmUsers = User::role('FM/GM')->get();
        Notification::send($fmGmUsers, new MrNotification($mr, "MR {$mr->mr_number} menunggu review FM/GM"));

        return redirect()->route('approval.manager')->with('success', 'MR diteruskan ke FM/GM');
    }

    // ============ FM/GM: Acknowledge ============

    public function fmGmIndex()
    {
        $requests = MaterialRequest::with(['user', 'items', 'manager'])
            ->where('status_workflow', 'Pending FM/GM')
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
            'action' => 'required|in:approve,reject,revision',
            'notes' => 'nullable|string',
        ]);

        $statusMap = [
            'approve' => 'Verifikasi Gudang',
            'reject' => 'Rejected',
            'revision' => 'Revision',
        ];

        $updates = ['status_workflow' => $statusMap[$request->action]];
        if ($request->action === 'revision') {
            $updates['revision_notes'] = $request->notes;
        }
        $mr->update($updates);

        ApprovalLog::create([
            'material_request_id' => $mr->id,
            'user_id' => auth()->id(),
            'role' => 'Direksi',
            'action' => $request->action,
            'notes' => $request->notes,
        ]);

        // Notifikasi
        if ($request->action === 'approve') {
            $gudangUsers = User::role('Gudang')->get();
            Notification::send($gudangUsers, new MrNotification($mr, "MR {$mr->mr_number} disetujui, perlu verifikasi gudang"));
        } elseif ($request->action === 'reject') {
            $mr->user->notify(new MrNotification($mr, "MR {$mr->mr_number} ditolak: {$request->notes}"));
        }

        return redirect()->route('approval.direksi')->with('success', 'Keputusan diterapkan');
    }

    // ============ GUDANG: Verifikasi Stok ============

    public function gudangIndex()
    {
        $requests = MaterialRequest::with(['user', 'items'])
            ->where('status_workflow', 'Verifikasi Gudang')
            ->latest()->paginate(10);

        return Inertia::render('Approval/Gudang', [
            'requests' => $requests,
        ]);
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

    // ============ PURCHASING: Export Excel ============

    public function purchasingIndex()
    {
        $requests = MaterialRequest::with(['user', 'items'])
            ->whereIn('status_workflow', ['Fully Approved', 'Purchasing'])
            ->latest()->paginate(10);

        return Inertia::render('Approval/Purchasing', [
            'requests' => $requests,
        ]);
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
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['required', 'string', 'max:20'],
            'items.*.specification' => ['nullable', 'string'],
            'items.*.purpose' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $mr) {
            $mr->items()->delete();
            foreach ($request->items as $item) {
                $mr->items()->create([
                    'item_name' => $item['item_name'],
                    'specification' => $item['specification'] ?? null,
                    'qty' => $item['qty'],
                    'unit' => $item['unit'],
                    'item_status' => 'Normal',
                    'purpose' => $item['purpose'] ?? null,
                ]);
            }
            $mr->update(['status_workflow' => 'Pending Manager', 'revision_notes' => null]);
        });

        return redirect()->route('material-requests.index')->with('success', 'MR dikirim ulang');
    }

    public function show($id)
    {
        $mr = MaterialRequest::with(['user', 'items', 'approvalLogs.user', 'manager', 'direksi'])->findOrFail($id);
        $user = auth()->user();
        $role = $user->getRoleNames()->first();

        // Data pendukung untuk action (FM/GM pilih Direksi)
        $direksiUsers = collect();
        if (strtolower($role) === 'fm/gm' && $mr->status_workflow === 'Pending FM/GM') {
            $direksiUsers = User::role('Direksi')->get(['id', 'name']);
        }

        return Inertia::render('Approval/MrDetail', [
            'mr' => $mr,
            'userRole' => $role,
            'direksiUsers' => $direksiUsers,
        ]);
    }

    public function exportExcel()
    {
        $requests = MaterialRequest::with(['user', 'items'])
            ->whereIn('status_workflow', ['Fully Approved', 'Purchasing'])
            ->latest()->get();

        $csv = "MR Number,Tanggal,User,Factory,Type,Status,Item Code,Item Name,Specification,Qty,Unit,Purpose\n";
        foreach ($requests as $mr) {
            foreach ($mr->items as $item) {
                $csv .= implode(',', [
                    $mr->mr_number,
                    $mr->created_at->format('Y-m-d'),
                    $mr->user->name ?? '',
                    $mr->factory,
                    $mr->type,
                    $mr->status_workflow,
                    $item->item_code ?? '',
                    '"' . str_replace('"', '""', $item->item_name) . '"',
                    '"' . str_replace('"', '""', $item->specification ?? '') . '"',
                    $item->qty,
                    $item->unit,
                    '"' . str_replace('"', '""', $item->purpose ?? '') . '"',
                ]) . "\n";
            }
        }

        $filename = 'material-requests-' . date('YmdHis') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
