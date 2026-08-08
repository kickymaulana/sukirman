<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_po_lines', function (Blueprint $table) {
            $table->date('tgl_po')->nullable()->after('nomor_po');
            $table->date('expected_date')->nullable()->after('tgl_po');
            $table->dateTime('tanggal_disetujui_direksi')->nullable()->after('expected_date');
        });
    }

    public function down(): void
    {
        Schema::table('item_po_lines', function (Blueprint $table) {
            $table->dropColumn(['tgl_po', 'expected_date', 'tanggal_disetujui_direksi']);
        });
    }
};