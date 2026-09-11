<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_request_items', function (Blueprint $table) {
            $table->enum('type', ['Lokal', 'Import'])->nullable()->after('material_request_id');
        });

        DB::statement('UPDATE material_request_items AS items INNER JOIN material_requests AS requests ON requests.id = items.material_request_id SET items.type = requests.type');
    }

    public function down(): void
    {
        Schema::table('material_request_items', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
