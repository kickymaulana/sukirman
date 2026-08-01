<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->foreignId('fm_gm_id')->nullable()->after('manager_id')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropForeign(['fm_gm_id']);
            $table->dropColumn('fm_gm_id');
        });
    }
};
