<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Nomor PO per item
        Schema::table('material_request_items', function (Blueprint $table) {
            $table->string('nomor_po', 100)->nullable()->after('departemen_id');
        });

        // 2. Pindahkan nomor PO lama dari MR ke semua item-nya (data lama)
        $mrs = DB::table('material_requests')->where('input_po', 'Sudah')->whereNotNull('nomor_po')->get(['id', 'nomor_po']);
        foreach ($mrs as $mr) {
            DB::table('material_request_items')->where('material_request_id', $mr->id)->update(['nomor_po' => $mr->nomor_po]);
        }

        // 3. Hapus kolom PO di header MR (pindah ke item)
        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropColumn(['input_po', 'nomor_po', 'tanggal_po']);
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->string('input_po', 20)->default('Belum');
            $table->string('nomor_po', 100)->nullable();
            $table->timestamp('tanggal_po')->nullable();
        });

        Schema::table('material_request_items', function (Blueprint $table) {
            $table->dropColumn('nomor_po');
        });
    }
};