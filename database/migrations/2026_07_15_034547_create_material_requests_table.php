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
        Schema::create('material_requests', function (Blueprint $table) {
            $table->id();
            $table->string('mr_number')->unique(); // Generate otomatis, misal: MR-010191
            $table->foreignId('user_id')->constrained('users'); // Supervisor pembuat
            $table->enum('type', ['Lokal', 'Import']); // Jenis pembelian
            $table->enum('factory', ['KIM', 'DALU 1', 'DALU 2']); // Lokasi Pabrik
            $table->enum('allocation', ['Project', 'Proses']); // Jenis Alokasi
            $table->enum('status_pembelian', ['Urgent', 'Normal']); // Urgensi dokumen
            $table->string('status_workflow')->default('Pending Manager'); // Posisi dokumen di sistem
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_requests');
    }
};
