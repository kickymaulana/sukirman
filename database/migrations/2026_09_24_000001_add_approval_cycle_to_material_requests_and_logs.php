<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->unsignedInteger('approval_cycle')->default(1)->after('status_workflow');
        });

        Schema::table('approval_logs', function (Blueprint $table) {
            $table->unsignedInteger('approval_cycle')->default(1)->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('approval_logs', function (Blueprint $table) {
            $table->dropColumn('approval_cycle');
        });

        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropColumn('approval_cycle');
        });
    }
};
