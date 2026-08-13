<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\DatabaseSchemaTool;
use App\Mcp\Tools\ListModelsTool;
use App\Mcp\Tools\ListRoutesTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('SUKIRMAN — App Flow Explorer')]
#[Version('1.0.0')]
#[Instructions('Eksplorasi alur program SUKIRMAN: daftar route, skema database, dan relasi model Eloquent. Gunakan tools ini untuk memahami struktur aplikasi sebelum melakukan perubahan.')]
class SukirmanServer extends Server
{
    protected array $tools = [
        ListRoutesTool::class,
        DatabaseSchemaTool::class,
        ListModelsTool::class,
    ];
}
