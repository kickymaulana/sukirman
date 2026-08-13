<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Menampilkan skema database: daftar tabel (opsional filter), dan untuk satu tabel: daftar kolom + tipe + nullable + index.')]
class DatabaseSchemaTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $tables = Schema::getTables();

        $result = [
            'total_tables' => count($tables),
            'tables'       => [],
        ];

        foreach ($tables as $table) {
            $name = $table['name'];

            if ($request->string('table')->value() && ! str_contains($name, $request->string('table')->value())) {
                continue;
            }

            $columns = collect(Schema::getColumns($name))->map(fn ($c) => [
                'name'     => $c['name'],
                'type'     => $c['type'],
                'nullable' => $c['nullable'],
                'default'  => $c['default'],
            ]);

            $indexes = collect(Schema::getIndexes($name))->map(fn ($i) => [
                'name'    => $i['name'],
                'columns' => $i['columns'],
                'unique'  => $i['unique'],
            ]);

            $result['tables'][] = [
                'name'    => $name,
                'columns' => $columns->all(),
                'indexes' => $indexes->all(),
            ];
        }

        return Response::structured($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'table' => $schema->string()
                ->description('Filter nama tabel (partial match). Kosongkan untuk melihat semua tabel.'),
        ];
    }
}
