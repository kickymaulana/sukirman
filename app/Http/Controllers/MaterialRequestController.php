<?php

namespace App\Http\Controllers;

use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

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
}
