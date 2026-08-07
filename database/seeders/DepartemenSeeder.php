<?php

namespace Database\Seeders;

use App\Models\Departemen;
use Illuminate\Database\Seeder;

class DepartemenSeeder extends Seeder
{
    public function run(): void
    {
        $list = [
            'MOULD',
            'FILLING',
            'WASHING',
            'CUCI CELUP',
            'SPRAY ON HALUS',
            'OVEN',
            'ASAH / GRATING',
            'QC',
            'TEXTURE / SPK',
            'MOULD DESIGN',
            'QA',
            'FQC',
        ];

        foreach ($list as $nama) {
            Departemen::firstOrCreate(['nama' => $nama]);
        }
    }
}