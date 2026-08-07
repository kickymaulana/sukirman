<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_request_items', function (Blueprint $table) {
            $table->unsignedBigInteger('departemen_id')->nullable()->after('input_accurate');
            $table->foreign('departemen_id')->references('id')->on('departemens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('material_request_items', function (Blueprint $table) {
            $table->dropForeign(['departemen_id']);
            $table->dropColumn('departemen_id');
        });
    }
};