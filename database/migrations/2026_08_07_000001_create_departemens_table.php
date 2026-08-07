<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departemens', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->timestamps();
        });

        $list = [
            'MOULD', 'FILLING', 'WASHING', 'CUCI CELUP', 'SPRAY ON HALUS', 'OVEN',
            'ASAH / GRATING', 'QC', 'TEXTURE / SPK', 'MOULD DESIGN', 'QA', 'FQC',
        ];
        foreach ($list as $nama) {
            DB::table('departemens')->insert([
                'nama' => $nama,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('departemens');
    }
};