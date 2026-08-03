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
        $managers = User::role('Manager')->get(['id', 'name', 'nik']);
        return Inertia::render('MaterialRequest/Create', [
            'managers' => $managers,
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
            'manager_id' => ['required', 'exists:users,id'],
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
            'manager_id.required' => 'Pilih Manager tujuan terlebih dahulu.',
            'items.required' => 'Minimal harus menambahkan 1 item barang.',
            'items.*.item_name.required' => 'Nama barang wajib diisi.',
            'items.*.qty.required' => 'Jumlah (Qty) wajib diisi.',
            'items.*.unit.required' => 'Satuan wajib diisi.',
        ]);

        DB::transaction(function () use ($validated) {
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
                'status_workflow' => 'Pending Manager',
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

        $mr->update([
            'manager_id' => auth()->id(),
            'fm_gm_id' => $request->fm_gm_id,
            'status_workflow' => 'Pending FM/GM',
        ]);

        ApprovalLog::create([
            'material_request_id' => $mr->id,
            'user_id' => auth()->id(),
            'role' => 'Manager',
            'action' => 'forward',
            'notes' => $request->notes,
        ]);

        $fmGmUser = User::find($request->fm_gm_id);
        if ($fmGmUser) {
            $fmGmUser->notify(new MrNotification($mr, "MR {$mr->mr_number} menunggu review Anda"));
        }

        return redirect()->route('approval.manager')->with('success', 'MR diteruskan ke FM/GM');
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
        $mr = MaterialRequest::with(['user', 'items', 'approvalLogs.user', 'manager', 'direksi'])->findOrFail($id);
        $user = auth()->user();
        $role = $user->getRoleNames()->first();

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
