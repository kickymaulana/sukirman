<?php

namespace Tests\Feature;

use App\Http\Controllers\MaterialRequestController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StatistikPurchasingTest extends TestCase
{
    public function test_statistik_purchasing_filters_items_by_custom_po_date_range(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('t', 32)),
        ]);
        DB::purge('sqlite');

        try {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('nik')->nullable();
            });
            Schema::create('item_po_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('material_request_item_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->date('tgl_po')->nullable();
            });

            DB::table('users')->insert([
                ['id' => 1, 'name' => 'Purchasing Januari', 'nik' => 'P-001'],
                ['id' => 2, 'name' => 'Purchasing Februari', 'nik' => 'P-002'],
            ]);
            DB::table('item_po_lines')->insert([
                ['material_request_item_id' => 1, 'user_id' => 1, 'tgl_po' => '2026-01-10'],
                ['material_request_item_id' => 2, 'user_id' => 1, 'tgl_po' => '2026-01-31'],
                ['material_request_item_id' => 3, 'user_id' => 2, 'tgl_po' => '2026-02-01'],
                ['material_request_item_id' => 4, 'user_id' => 2, 'tgl_po' => null],
            ]);

            $request = Request::create('/statistik-purchasing', 'GET', [
                'period' => 'custom',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ]);
            $request->headers->set('X-Inertia', 'true');

            $response = app(MaterialRequestController::class)->statistikPurchasing($request)->toResponse($request);
            $props = $response->getData(true)['props'];

            $this->assertSame(2, $props['total_all']);
            $this->assertSame([['id' => 1, 'total' => 2]], array_map(
                fn (array $purchasing) => array_intersect_key($purchasing, array_flip(['id', 'total'])),
                $props['purchasings'],
            ));
            $this->assertSame([
                'period' => 'custom',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ], $props['filters']);
        } finally {
            DB::purge('sqlite');
        }
    }
}
