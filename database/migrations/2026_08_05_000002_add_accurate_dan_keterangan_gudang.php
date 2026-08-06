<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Status input Accurate di header MR
        Schema::table('material_requests', function (Blueprint $table) {
            $table->string('input_accurate', 20)->default('Belum')->after('jenis');
        });

        // Ketersediaan & keterangan per item (diisi Gudang)
        Schema::table('material_request_items', function (Blueprint $table) {
            $table->integer('qty_tersedia')->nullable()->after('qty');
            $table->text('keterangan_gudang')->nullable()->after('qty_tersedia');
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropColumn('input_accurate');
        });
        Schema::table('material_request_items', function (Blueprint $table) {
            $table->dropColumn(['qty_tersedia', 'keterangan_gudang']);
        });
    }
};
