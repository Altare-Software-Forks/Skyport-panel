<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Client\Concerns\AuthorizesServerAccess;
use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\ServerUser;
use App\Support\ServerPowerState;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerConsoleController extends Controller
{
    use AuthorizesServerAccess;

    public function show(Request $request, Server $server): Response
    {
        $this->authorizeServerAccess($request, $server, ServerUser::PERMISSION_CONSOLE);

        $server->loadMissing(['allocation', 'cargo', 'node']);

        return Inertia::render('server/console', [
            'server' => [
                'allocation' => $server->allocation
                    ? [
                        'bind_ip' => $server->allocation->bind_ip,
                        'ip_alias' => $server->allocation->ip_alias,
                        'port' => $server->allocation->port,
                    ]
                    : null,
                'allowed_actions' => ServerPowerState::mapFor($server->status),
                'cargo' => [
                    'id' => $server->cargo->id,
                    'name' => $server->cargo->name,
                ],
                'cpu_limit' => $server->cpu_limit,
                'disk_mib' => $server->disk_mib,
                'id' => $server->id,
                'last_error' => $server->last_error,
                'memory_mib' => $server->memory_mib,
                'name' => $server->name,
                'node' => [
                    'id' => $server->node->id,
                    'name' => $server->node->name,
                    'online' => $server->node->isOnline(),
                ],
                'status' => $server->status,
            ],
        ]);
    }
}
