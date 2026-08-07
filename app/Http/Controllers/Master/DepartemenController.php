<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Departemen;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartemenController extends Controller
{
    public function index(Request $request)
    {
        $query = Departemen::query();

        if ($search = $request->input('search')) {
            $query->where('nama', 'like', "%{$search}%");
        }

        $departemens = $query->orderBy('nama')->paginate(20)->withQueryString();

        return Inertia::render('Master/Departemen/Index', [
            'departemens' => $departemens,
            'filters' => ['search' => $search ?? ''],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:100|unique:departemens,nama',
        ], ['nama.unique' => 'Departemen ini sudah ada.']);

        Departemen::create($validated);

        return back()->with('success', 'Departemen ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $departemen = Departemen::findOrFail($id);
        $validated = $request->validate([
            'nama' => 'required|string|max:100|unique:departemens,nama,' . $departemen->id,
        ], ['nama.unique' => 'Departemen ini sudah ada.']);

        $departemen->update($validated);

        return back()->with('success', 'Departemen diperbarui.');
    }

    public function destroy($id)
    {
        Departemen::findOrFail($id)->delete();

        return back()->with('success', 'Departemen dihapus.');
    }
}