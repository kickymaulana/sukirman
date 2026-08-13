<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Menampilkan daftar model Eloquent beserta relasi dan properti yang diisi (fillable/guarded).')]
class ListModelsTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $files = File::files(app_path('Models'));

        $models = [];

        foreach ($files as $file) {
            $class = 'App\\Models\\' . $file->getBasename('.php');

            if (! class_exists($class)) {
                continue;
            }

            $reflect = new \ReflectionClass($class);

            $relations = [];

            foreach ($reflect->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getNumberOfRequiredParameters() > 0 || $method->isStatic()) {
                    continue;
                }

                try {
                    $model = new $class;
                    $result = $method->invoke($model);

                    $type = null;
                    if ($result instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                        $type = class_basename($result);
                    }

                    if ($type) {
                        $relations[] = [
                            'method' => $method->getName(),
                            'type'   => $type,
                            'target' => get_class($result->getRelated()),
                        ];
                    }
                } catch (\Throwable $e) {
                    // Abaikan method yang tidak menghasilkan relasi
                }
            }

            $models[] = [
                'class'    => $class,
                'table'    => $reflect->hasProperty('table')
                    ? ($reflect->getProperty('table')->getValue(new $class) ?? $reflect->getShortName())
                    : $reflect->getShortName(),
                'relations' => $relations,
            ];
        }

        return Response::structured([
            'count'  => count($models),
            'models' => $models,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
