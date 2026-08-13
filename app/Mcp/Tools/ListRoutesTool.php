<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Menampilkan daftar seluruh route aplikasi: method, URI, nama route, controller, dan middleware.')]
class ListRoutesTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $routes = collect(Route::getRoutes())->map(function ($route) {
            return [
                'method'  => implode('|', $route->methods()),
                'uri'     => $route->uri(),
                'name'    => $route->getName(),
                'action'  => $route->getActionName(),
                'middleware' => implode(', ', $route->middleware() ?: []),
            ];
        })->sortBy('uri')->values();

        return Response::structured([
            'count'  => $routes->count(),
            'routes' => $routes->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
