<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_request_items', function (Blueprint $table) {
            $table->enum('direksi_decision', ['setuju', 'tolak', 'ganti'])->nullable()->after('purpose');
            $table->string('direksi_notes')->nullable()->after('direksi_decision');
        });
    }

    public function down(): void
    {
        Schema::table('material_request_items', function (Blueprint $table) {
            $table->dropColumn(['direksi_decision', 'direksi_notes']);
        });
    }
};
