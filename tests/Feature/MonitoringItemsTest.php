<?php

namespace Tests\Feature;

use App\Http\Controllers\MaterialRequestController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MonitoringItemsTest extends TestCase
{
    public function test_monitoring_items_lists_numbered_pos_with_saved_staff_and_item_types(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'app.key' => 'base64:'.base64_encode(str_repeat('t', 32)), 'session.driver' => 'array']);
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
                $table->integer('qty')->default(20);
                $table->string('unit')->default('PCS');
                $table->timestamps();
            });
            Schema::create('item_po_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('material_request_item_id');
                $table->string('nomor_po')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->integer('qty')->default(5);
                $table->date('tgl_po')->nullable();
                $table->date('expected_date')->nullable();
                $table->dateTime('tanggal_disetujui_direksi')->nullable();
                $table->timestamps();
            });

            Schema::create('role_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('permission_id');
            });
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
            });
            Schema::create('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
            });
            Role::create(['name' => 'Purchasing', 'guard_name' => 'web']);
            Role::create(['name' => 'admin', 'guard_name' => 'web']);
            Role::create(['name' => 'Gudang', 'guard_name' => 'web']);
            Role::create(['name' => 'FM/GM', 'guard_name' => 'web']);

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
            ], array_map(fn ($line) => array_intersect_key($line, array_flip(['nomor_po', 'purchasing'])), $items[1]['po_lines']));
            $this->assertSame('Import', $items[1]['type']);
            $this->assertSame('Lokal', $items[2]['type']);
            $this->assertSame('PO-004', $items[2]['po_lines'][0]['nomor_po']);
            $this->assertSame('Tidak diketahui', $items[2]['po_lines'][0]['purchasing']);
            $this->assertSame('Belum ditentukan', $items[3]['type']);
            $this->assertCount(3, $items[3]['po_lines']);
            $this->assertSame([5, 6, 7], array_column($items[3]['po_lines'], 'id'));
            $this->assertFalse($response->getData(true)['props']['can_edit_po']);
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

            $this->withoutMiddleware([HandleInertiaRequests::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
            $url = '/monitoring-items/1/po-lines/1';
            $this->patchJson($url, [])->assertUnauthorized();
            $this->postJson('/monitoring-items/1/po-lines', [])->assertUnauthorized();
            $otherLine = DB::table('item_po_lines')->where('id', 2)->first();
            $mrBefore = DB::table('material_requests')->where('id', 1)->first();
            $itemBefore = DB::table('material_request_items')->where('id', 1)->first();
            foreach (['Purchasing', 'admin', 'Gudang', 'FM/GM'] as $role) {
                $user = User::findOrFail(2);
                $user->syncRoles([$role]);
                $this->actingAs($user);
                $request->setUserResolver(fn () => $user);
                $request->headers->set('X-Inertia', 'true');
                $response = app(MaterialRequestController::class)->monitoringItemsIndex($request)->toResponse($request);
                $allowed = in_array($role, ['Purchasing', 'admin']);
                $this->assertSame($allowed, $response->getData(true)['props']['can_edit_po']);
                $editQuery = $filters + ['page' => '2', 'return_url' => '//example.com'];
                $edit = $this->get($url.'/edit?'.http_build_query($editQuery), ['X-Inertia' => 'true']);
                $create = $this->get('/monitoring-items/1/po-lines/create?'.http_build_query($editQuery), ['X-Inertia' => 'true']);
                if ($allowed) {
                    $create->assertOk()->assertJsonPath('component', 'Approval/EditMonitoringItemPo')
                        ->assertJsonPath('props.item.id', 1)
                        ->assertJsonPath('props.line', null)
                        ->assertJsonPath('props.remaining_qty', 5);
                    parse_str(parse_url($create->json('props.return_url'), PHP_URL_QUERY), $createReturnQuery);
                    $this->assertEquals($filters + ['page' => '2'], $createReturnQuery);
                    $edit->assertOk()->assertJsonPath('component', 'Approval/EditMonitoringItemPo')
                        ->assertJsonPath('props.item.id', 1)
                        ->assertJsonPath('props.item.item_name', 'Imported item')
                        ->assertJsonPath('props.item.item_code', null)
                        ->assertJsonPath('props.item.mr_number', 'MR-001')
                        ->assertJsonPath('props.line.id', 1)
                        ->assertJsonPath('props.line.nomor_po', $role === 'Purchasing' ? 'PO-001' : 'PO-EDIT')
                        ->assertJsonPath('props.line.tgl_po', $role === 'Purchasing' ? null : '2026-09-14')
                        ->assertJsonPath('props.line.expected_date', $role === 'Purchasing' ? null : '2026-09-20')
                        ->assertJsonPath('props.line.tanggal_disetujui_direksi', $role === 'Purchasing' ? null : '2026-09-14T13:45');
                    $returnUrl = $edit->json('props.return_url');
                    $this->assertSame('/monitoring-items', parse_url($returnUrl, PHP_URL_PATH));
                    $this->assertSame(parse_url(config('app.url'), PHP_URL_HOST), parse_url($returnUrl, PHP_URL_HOST));
                    parse_str(parse_url($returnUrl, PHP_URL_QUERY), $returnQuery);
                    $this->assertEquals($filters + ['page' => '2'], $returnQuery);
                    $this->getJson('/monitoring-items/2/po-lines/1/edit')->assertNotFound();
                    $this->getJson('/monitoring-items/999/po-lines/1/edit')->assertNotFound();
                    $this->getJson('/monitoring-items/1/po-lines/999/edit')->assertNotFound();
                    $this->getJson($url.'/edit?page=-1&search[]=bad')->assertRedirect();
                    $this->get($url.'/edit', ['X-Inertia' => 'true'])->assertOk()
                        ->assertJsonPath('props.return_url', route('monitoring.items'));
                } else {
                    $create->assertForbidden();
                    $edit->assertForbidden();
                }
                $createPayload = [
                    'qty' => 4,
                    'nomor_po' => '  PO-NEW  ',
                    'tgl_po' => '2026-09-15',
                    'expected_date' => '2026-09-22',
                    'tanggal_disetujui_direksi' => '2026-09-14T14:30',
                    'user_id' => 1,
                    'material_request_item_id' => 2,
                    'id' => 999,
                ];
                $created = $this->postJson('/monitoring-items/1/po-lines', $createPayload);
                if ($allowed) {
                    $created->assertOk()->assertJson(['ok' => true]);
                    $createdLineId = DB::table('item_po_lines')->max('id');
                    $this->assertDatabaseHas('item_po_lines', [
                        'material_request_item_id' => 1, 'qty' => 4, 'user_id' => 2, 'nomor_po' => 'PO-NEW',
                        'tgl_po' => '2026-09-15', 'expected_date' => '2026-09-22', 'tanggal_disetujui_direksi' => '2026-09-14 14:30',
                    ]);
                    $this->postJson('/monitoring-items/1/po-lines', $createPayload)->assertUnprocessable()->assertJsonValidationErrors('qty');
                    $this->assertDatabaseHas('item_po_lines', ['id' => $createdLineId]);
                    $this->get('/monitoring-items/1/po-lines/create', ['X-Inertia' => 'true'])
                        ->assertOk()->assertJsonPath('props.remaining_qty', 1);
                    $fullLine = $this->postJson('/monitoring-items/1/po-lines', ['qty' => 1, 'nomor_po' => '   ']);
                    $fullLine->assertOk();
                    $fullLineId = DB::table('item_po_lines')->max('id');
                    $this->assertDatabaseHas('item_po_lines', ['id' => $fullLineId, 'nomor_po' => null, 'qty' => 1]);
                    $this->get('/monitoring-items/1/po-lines/create', ['X-Inertia' => 'true'])->assertUnprocessable();
                    DB::table('item_po_lines')->whereIn('id', [$createdLineId, $fullLineId])->delete();
                } else {
                    $created->assertForbidden();
                }
                $payload = [
                    'nomor_po' => 'PO-EDIT',
                    'tgl_po' => '2026-09-14',
                    'expected_date' => '2026-09-20',
                    'tanggal_disetujui_direksi' => '2026-09-14T13:45',
                    'qty' => 999,
                    'user_id' => 1,
                    'material_request_item_id' => 2,
                    'id' => 999,
                ];
                $result = $this->patchJson($url, $payload);
                if (! $allowed) {
                    $result->assertForbidden();

                    continue;
                }
                $result->assertOk()->assertJson(['ok' => true]);
                $this->assertDatabaseHas('item_po_lines', [
                    'id' => 1, 'material_request_item_id' => 1, 'qty' => 5, 'user_id' => 2,
                    'nomor_po' => 'PO-EDIT', 'tgl_po' => '2026-09-14', 'expected_date' => '2026-09-20',
                    'tanggal_disetujui_direksi' => '2026-09-14 13:45',
                ]);
                $this->patchJson('/monitoring-items/2/po-lines/1', $payload)->assertNotFound();
                $this->patchJson('/monitoring-items/999/po-lines/1', $payload)->assertNotFound();
                $this->patchJson($url, [
                    'nomor_po' => str_repeat('x', 101), 'tgl_po' => 'bad',
                    'expected_date' => 'bad', 'tanggal_disetujui_direksi' => '2026-09-14 13:45',
                ])->assertUnprocessable()->assertJsonValidationErrors(['nomor_po', 'tgl_po', 'expected_date', 'tanggal_disetujui_direksi']);
                $this->patchJson('/monitoring-items/3/po-lines/5', ['nomor_po' => 'PO-FILLED'])->assertOk();
                $this->assertDatabaseHas('item_po_lines', ['id' => 5, 'nomor_po' => 'PO-FILLED', 'qty' => 5, 'user_id' => 2]);
                $this->patchJson('/monitoring-items/3/po-lines/5', [
                    'nomor_po' => null, 'tgl_po' => null, 'expected_date' => null, 'tanggal_disetujui_direksi' => null,
                ])->assertOk();
            }
            DB::table('material_requests')->where('id', 1)->update(['status_workflow' => 'Fully Approved']);
            $user = User::findOrFail(2);
            $user->syncRoles(['Purchasing']);
            $this->actingAs($user);
            $this->get('/monitoring-items/1/po-lines/create', ['X-Inertia' => 'true'])->assertForbidden();
            $this->postJson('/monitoring-items/1/po-lines', ['qty' => 1])->assertForbidden();
            DB::table('material_requests')->where('id', 1)->update(['status_workflow' => 'Purchasing']);
            $this->postJson('/monitoring-items/4/po-lines', [])->assertUnprocessable()->assertJsonValidationErrors(['qty']);
            $this->postJson('/monitoring-items/4/po-lines', [
                'qty' => 1, 'nomor_po' => str_repeat('x', 101), 'tgl_po' => 'bad',
                'expected_date' => 'bad', 'tanggal_disetujui_direksi' => '2026-09-14 13:45',
            ])->assertUnprocessable()->assertJsonValidationErrors(['nomor_po', 'tgl_po', 'expected_date', 'tanggal_disetujui_direksi']);
            $this->assertEquals($otherLine, DB::table('item_po_lines')->where('id', 2)->first());
            $this->assertEquals($mrBefore, DB::table('material_requests')->where('id', 1)->first());
            $this->assertEquals($itemBefore, DB::table('material_request_items')->where('id', 1)->first());
            $this->assertSame(7, DB::table('item_po_lines')->count());
        } finally {
            DB::purge('sqlite');
        }
    }
}
