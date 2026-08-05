<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Kolom jenis MR (UMUM, MTC, IT, HRD)
        Schema::table('material_requests', function (Blueprint $table) {
            $table->string('jenis', 20)->default('UMUM')->after('status_pembelian');
        });

        // 2. Role departemen untuk langkah approval MTC/IT/HRD
        foreach (['MTC', 'IT', 'HRD'] as $name) {
            if (!Role::where('name', $name)->where('guard_name', 'web')->exists()) {
                Role::create(['name' => $name, 'guard_name' => 'web']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropColumn('jenis');
        });
    }
};
