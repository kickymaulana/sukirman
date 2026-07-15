<?php

namespace App\Http\Controllers;

use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialRequestController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validasi input dari Frontend
        $request->validate([
            'type' => 'required|in:Lokal,Import',
            'factory' => 'required|in:KIM,DALU 1,DALU 2',
            'allocation' => 'required|in:Project,Proses',
            'status_pembelian' => 'required|in:Urgent,Normal',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unit' => 'required|string',
        ]);

        // Gunakan DB Transaction agar jika ada salah satu item gagal tersimpan,
        // seluruh data dibatalkan otomatis (menghindari data corrupt)
        DB::beginTransaction();

        try {
            // 2. Generate Nomor MR Otomatis (Contoh: MR-20260715-0001)
            $dateCode = now()->format('Ymd');
            $latestMr = MaterialRequest::where('mr_number', 'like', "MR-{$dateCode}-%")->latest()->first();
            $nextNumber = $latestMr ? ((int) explode('-', $latestMr->mr_number)[2]) + 1 : 1;
            $mrNumber = "MR-{$dateCode}-" . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // 3. Simpan data Header MR
            $mr = MaterialRequest::create([
                'mr_number' => $mrNumber,
                'user_id' => $request->user()->id, // Diambil otomatis dari user token Sanctum yang aktif
                'type' => $request->type,
                'factory' => $request->factory,
                'allocation' => $request->allocation,
                'status_pembelian' => $request->status_pembelian,
                'status_workflow' => 'Pending Manager', // Status awal masuk ke antrean Manager
            ]);

            // 4. Simpan semua Item Barang di dalam perulangan loop
            foreach ($request->items as $item) {
                $mr->items()->create([
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
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Material Request berhasil diajukan dengan nomor ' . $mrNumber,
                'data' => $mr->load('items')
            ], 210);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan data: ' . $e->getMessage()
            ], 500);
        }
    }

    // 1. Fungsi untuk mengambil semua data MR (Bisa difilter berdasarkan status workflow)
    public function index(Request $request)
    {
        $status = $request->query('status'); // Misal: ?status=Pending Manager

        $query = MaterialRequest::with('items');

        if ($status) {
            $query->where('status_workflow', $status);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get()
        ]);
    }

    // 2. Fungsi ketika Manager meneruskan MR dan memilih target Direksi
    public function forwardByManager(Request $request, $id)
    {
        $request->validate([
            'target_direksi' => 'required|string', // Mengambil pilihan nama/role direksi dari dropdown
        ]);

        $mr = MaterialRequest::find($id);

        if (!$mr) {
            return response()->json(['status' => 'error', 'message' => 'Data MR tidak ditemukan'], 404);
        }

        // Update status workflow dan simpan catatan target direksi mana yang dituju
        $mr->update([
            'status_workflow' => 'Pending FM/GM',
            // Anda bisa menambahkan kolom target_direksi di migrasi jika ingin mencatat nama direksinya
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Material Request berhasil diteruskan ke FM/GM dengan target persetujuan ' . $request->target_direksi,
            'data' => $mr->load('items')
        ]);
    }

    // 3. Fungsi ketika FM/GM menekan tombol Acknowledge (Mengetahui)
    public function acknowledgeByGM($id)
    {
        $mr = MaterialRequest::find($id);

        if (!$mr) {
            return response()->json(['status' => 'error', 'message' => 'Data MR tidak ditemukan'], 404);
        }

        // Pastikan dokumen memang sedang tertahan di level FM/GM
        if ($mr->status_workflow !== 'Pending FM/GM') {
            return response()->json(['status' => 'error', 'message' => 'Status dokumen tidak valid untuk di-acknowledge FM/GM'], 400);
        }

        // Naikkan status ke level Direksi
        $mr->update([
            'status_workflow' => 'Pending Direksi'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Material Request telah diketahui oleh FM/GM dan diteruskan ke Direksi.',
            'data' => $mr->load('items')
        ]);
    }

    // 4. Fungsi Keputusan Akhir oleh Direksi (Approve / Reject)
    public function decisionByDireksi(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:Approve,Reject',
            'reason' => 'required_if:action,Reject|string|nullable' // Wajib isi alasan kalau menolak
        ]);

        $mr = MaterialRequest::find($id);

        if (!$mr) {
            return response()->json(['status' => 'error', 'message' => 'Data MR tidak ditemukan'], 404);
        }

        if ($mr->status_workflow !== 'Pending Direksi') {
            return response()->json(['status' => 'error', 'message' => 'Status dokumen tidak valid untuk diproses Direksi'], 400);
        }

        // Tentukan status akhir berdasarkan aksi Direksi
        $finalStatus = $request->action === 'Approve' ? 'Approved by Direksi' : 'Rejected by Direksi';

        $mr->update([
            'status_workflow' => $finalStatus
            // Jika Anda ingin menampung alasan reject, bisa ditambahkan kolom notes/reason di tabel migrasi
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Keputusan Direksi berhasil disimpan: ' . $finalStatus,
            'data' => $mr->load('items')
        ]);
    }
}
