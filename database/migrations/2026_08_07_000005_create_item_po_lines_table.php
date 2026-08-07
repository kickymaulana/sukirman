<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_po_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_request_item_id')->constrained('material_request_items')->onDelete('cascade');
            $table->integer('qty');
            $table->string('nomor_po', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_po_lines');
    }
};