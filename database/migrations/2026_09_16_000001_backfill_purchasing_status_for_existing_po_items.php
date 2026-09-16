<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('material_request_items')
            ->where('purchasing_status', 'Menunggu')
            ->whereExists(function ($query) {
                $query->select(new Expression('1'))
                    ->from('item_po_lines')
                    ->whereColumn('item_po_lines.material_request_item_id', 'material_request_items.id');
            })
            ->update(['purchasing_status' => 'Diproses']);
    }

    public function down(): void {}
};
