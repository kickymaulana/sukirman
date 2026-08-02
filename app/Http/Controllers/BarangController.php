<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BarangController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user || !$user->hasAnyRole(['Gudang', 'Purchasing', 'admin'])) {
                abort(403, 'Anda tidak memiliki akses.');
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $query = Barang::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('kode_barang', 'like', "%{$search}%")
                  ->orWhere('nama_barang', 'like', "%{$search}%");
            });
        }

        $barangs = $query->orderBy('kode_barang')->paginate(20)->withQueryString();

        return Inertia::render('Barang/Index', [
            'barangs' => $barangs,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode_barang' => 'required|string|max:50|unique:barangs,kode_barang',
            'nama_barang' => 'required|string|max:255',
        ], [
            'kode_barang.required' => 'Kode barang wajib diisi.',
            'kode_barang.unique' => 'Kode barang sudah terdaftar.',
            'nama_barang.required' => 'Nama barang wajib diisi.',
        ]);

        Barang::create($request->only(['kode_barang', 'nama_barang']));

        return redirect()->route('barangs.index')->with('success', 'Barang ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $barang = Barang::findOrFail($id);

        $request->validate([
            'kode_barang' => 'required|string|max:50|unique:barangs,kode_barang,' . $id,
            'nama_barang' => 'required|string|max:255',
        ], [
            'kode_barang.required' => 'Kode barang wajib diisi.',
            'kode_barang.unique' => 'Kode barang sudah terdaftar.',
            'nama_barang.required' => 'Nama barang wajib diisi.',
        ]);

        $barang->update($request->only(['kode_barang', 'nama_barang']));

        return redirect()->route('barangs.index')->with('success', 'Barang diperbarui.');
    }

    public function destroy($id)
    {
        Barang::findOrFail($id)->delete();
        return redirect()->route('barangs.index')->with('success', 'Barang dihapus.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = fopen($request->file('file')->getRealPath(), 'r');
        $firstLine = fgets($file);
        rewind($file);

        // Deteksi delimiter: titik koma (;) atau koma (,)
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $enclosure = '"';

        $header = fgetcsv($file, 0, $delimiter, $enclosure);

        // Cari index kolom
        $kodeIndex = array_search('kode_barang', array_map(fn($h) => strtolower(trim(str_replace('"', '', $h))), $header ?? []));
        $namaIndex = array_search('nama_barang', array_map(fn($h) => strtolower(trim(str_replace('"', '', $h))), $header ?? []));

        if ($kodeIndex === false || $namaIndex === false) {
            fclose($file);
            return back()->with('error', 'Format CSV harus memiliki kolom kode_barang dan nama_barang.');
        }

        set_time_limit(300);
        DB::beginTransaction();
        try {
            $new = 0; $updated = 0; $skipped = 0;
            while (($row = fgetcsv($file, 0, $delimiter, $enclosure)) !== false) {
                $kode = trim(str_replace('"', '', $row[$kodeIndex] ?? ''));
                $nama = trim(str_replace('"', '', $row[$namaIndex] ?? ''));
                if ($kode === '' || $nama === '') continue;

                $existing = Barang::where('kode_barang', $kode)->first();
                if ($existing) {
                    if ($existing->nama_barang !== $nama) {
                        $existing->update(['nama_barang' => $nama]);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    Barang::create(['kode_barang' => $kode, 'nama_barang' => $nama]);
                    $new++;
                }
            }
            fclose($file);
            DB::commit();

            return back()->with('success', "Import selesai! Baru: {$new}, Diupdate: {$updated}, Sama: {$skipped}.");
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($file);
            return back()->with('error', 'Gagal import: ' . $e->getMessage());
        }
    }
}
