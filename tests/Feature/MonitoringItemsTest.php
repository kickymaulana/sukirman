<?php

namespace Tests\Feature;

use App\Http\Controllers\MaterialRequestController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MonitoringItemsTest extends TestCase
{
    public function test_monitoring_items_lists_numbered_pos_with_saved_staff_and_item_types(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        try {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('nik')->nullable();
                $table->unsignedBigInteger('departemen_id')->nullable();
            });
            Schema::create('material_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('mr_number')->nullable();
                $table->string('factory')->nullable();
                $table->string('jenis')->nullable();
                $table->string('status_workflow')->nullable();
            });
            Schema::create('material_request_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('material_request_id')->nullable();
                $table->string('item_name');
                $table->string('item_code')->nullable();
                $table->string('type')->nullable();
                $table->timestamps();
            });
            Schema::create('item_po_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('material_request_item_id');
                $table->string('nomor_po')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
            });

            DB::table('users')->insert([
                ['id' => 1, 'name' => 'Staff One'],
                ['id' => 2, 'name' => 'Staff Two'],
            ]);
            DB::table('material_request_items')->insert([
                ['id' => 1, 'item_name' => 'Imported item', 'type' => 'Import'],
                ['id' => 2, 'item_name' => 'Local item', 'type' => 'Lokal'],
                ['id' => 3, 'item_name' => 'Unspecified item', 'type' => null],
                ['id' => 4, 'item_name' => 'No PO item', 'type' => null],
            ]);
            DB::table('item_po_lines')->insert([
                ['material_request_item_id' => 1, 'nomor_po' => 'PO-001', 'user_id' => 1],
                ['material_request_item_id' => 1, 'nomor_po' => 'PO-002', 'user_id' => 2],
                ['material_request_item_id' => 1, 'nomor_po' => 'PO-003', 'user_id' => null],
                ['material_request_item_id' => 2, 'nomor_po' => 'PO-004', 'user_id' => 999],
                ['material_request_item_id' => 3, 'nomor_po' => null, 'user_id' => 1],
                ['material_request_item_id' => 3, 'nomor_po' => '', 'user_id' => 1],
                ['material_request_item_id' => 3, 'nomor_po' => '   ', 'user_id' => 2],
            ]);

            $request = Request::create('/monitoring-items');
            $request->headers->set('X-Inertia', 'true');
            $response = app(MaterialRequestController::class)->monitoringItemsIndex($request)->toResponse($request);
            $items = collect($response->getData(true)['props']['items']['data'])->keyBy('id');

            $this->assertSame([
                ['nomor_po' => 'PO-001', 'purchasing' => 'Staff One'],
                ['nomor_po' => 'PO-002', 'purchasing' => 'Staff Two'],
                ['nomor_po' => 'PO-003', 'purchasing' => 'Tidak diketahui'],
            ], $items[1]['po_lines']);
            $this->assertSame('Import', $items[1]['type']);
            $this->assertSame('Lokal', $items[2]['type']);
            $this->assertSame([['nomor_po' => 'PO-004', 'purchasing' => 'Tidak diketahui']], $items[2]['po_lines']);
            $this->assertSame('Belum ditentukan', $items[3]['type']);
            $this->assertSame([], $items[3]['po_lines']);
            $this->assertSame([], $items[4]['po_lines']);

            foreach (['' => [1, 2, 3, 4], 'Lokal' => [2], 'Import' => [1], 'invalid' => [1, 2, 3, 4]] as $type => $expectedIds) {
                $request = Request::create('/monitoring-items', 'GET', ['type' => $type]);
                $request->headers->set('X-Inertia', 'true');
                $response = app(MaterialRequestController::class)->monitoringItemsIndex($request)->toResponse($request);
                $props = $response->getData(true)['props'];

                $this->assertEqualsCanonicalizing($expectedIds, array_column($props['items']['data'], 'id'));
                $this->assertSame($type === 'invalid' ? '' : $type, $props['filters']['type']);
            }

            DB::table('material_requests')->insert([
                'id' => 1, 'mr_number' => 'MR-001', 'factory' => 'KIM', 'jenis' => 'IT', 'status_workflow' => 'Purchasing',
            ]);
            DB::table('material_request_items')->whereIn('id', [1, 2])->update(['material_request_id' => 1]);
            $filters = ['type' => 'Lokal', 'search' => 'item', 'factory' => 'KIM', 'jenis' => 'IT', 'status' => 'Purchasing'];
            $request = Request::create('/monitoring-items', 'GET', $filters);
            $request->headers->set('X-Inertia', 'true');
            $this->app->instance('request', $request);
            $response = app(MaterialRequestController::class)->monitoringItemsIndex($request)->toResponse($request);
            $props = $response->getData(true)['props'];
            $this->assertSame([2], array_column($props['items']['data'], 'id'));
            $this->assertEquals($filters, array_intersect_key($props['filters'], $filters));

            for ($id = 5; $id <= 14; $id++) {
                DB::table('material_request_items')->insert([
                    'id' => $id, 'material_request_id' => 1, 'item_name' => 'Local item', 'type' => 'Lokal',
                ]);
            }
            $response = app(MaterialRequestController::class)->monitoringItemsIndex($request)->toResponse($request);
            $pagination = $response->getData(true)['props']['items'];
            $this->assertSame(11, $pagination['total']);
            parse_str(parse_url($pagination['next_page_url'], PHP_URL_QUERY), $nextQuery);
            $this->assertEquals($filters + ['page' => '2'], $nextQuery);
        } finally {
            DB::purge('sqlite');
        }
    }
}
