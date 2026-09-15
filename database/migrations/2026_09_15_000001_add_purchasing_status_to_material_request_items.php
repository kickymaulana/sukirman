<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_request_items', function (Blueprint $table) {
            $table->string('purchasing_status', 20)->default('Menunggu')->after('item_status');
            $table->text('purchasing_note')->nullable()->after('purchasing_status');
        });
    }

    public function down(): void
    {
        Schema::table('material_request_items', function (Blueprint $table) {
            $table->dropColumn(['purchasing_status', 'purchasing_note']);
        });
    }
};
