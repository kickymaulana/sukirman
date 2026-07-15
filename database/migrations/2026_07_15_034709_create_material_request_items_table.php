<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('material_request_items', function (Blueprint $table) {
            $table->id();

            // Relasi ke tabel header (material_requests)
            // Jika data header MR dihapus, maka item di dalamnya otomatis ikut terhapus (cascade)
            $table->foreignId('material_request_id')
                  ->constrained('material_requests')
                  ->onDelete('cascade');

            // Kolom dari Formulir Fisik PT. Mark Dynamics Indonesia Tbk
            $table->string('item_code')->nullable(); // Code Barang (nullable jika barang baru belum ada kode) [cite: 23]
            $table->string('item_name'); // Nama Barang [cite: 24]
            $table->text('specification')->nullable(); // Spesifikasi Barang [cite: 25]
            $table->integer('qty'); // Quantity yang diminta [cite: 26]
            $table->string('unit'); // Satuan barang (Pcs, Kotak, Roll, dll) [cite: 26]

            // Status Item: Urgent, Normal, New, Replace [cite: 27]
            $table->enum('item_status', ['Urgent', 'Normal', 'New', 'Replace'])->default('Normal');

            $table->integer('monthly_usage')->default(0); // Pemakaian/bulan [cite: 29]
            $table->integer('stock_on_hand')->default(0); // Stock On Hand (Bisa diisi manual/auto oleh Gudang) [cite: 30]
            $table->text('purpose')->nullable(); // Tujuan Pembelian [cite: 31]

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_request_items');
    }
};
