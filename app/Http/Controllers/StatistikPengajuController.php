<?php

namespace App\Http\Controllers;

use App\Models\MaterialRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StatistikPengajuController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $rows = MaterialRequest::select('user_id', DB::raw('count(*) as total'))
            ->with('user')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->user_id,
                'name' => $t->user?->name ?? '?',
                'nik' => $t->user?->nik,
                'total' => $t->total,
            ]);

        if ($search) {
            $rows = $rows->filter(function ($r) use ($search) {
                return stripos($r['name'], $search) !== false
                    || stripos((string) $r['nik'], $search) !== false;
            })->values();
        }

        return Inertia::render('StatistikPengaju', [
            'pengajus' => $rows,
            'total_all' => $rows->count(),
            'filters' => ['search' => $search ?? ''],
        ]);
    }
}