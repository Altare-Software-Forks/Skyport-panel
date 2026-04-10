<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Client\Concerns\AuthorizesServerAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreInterconnectRequest;
use App\Models\Interconnect;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ServerInterconnectController extends Controller
{
    use AuthorizesServerAccess;

    public function index(Request $request, Server $server): Response
    {
        $this->authorizeServerAccess($request, $server);

        $server->loadMissing('interconnects.servers');

        $interconnects = Interconnect::query()
            ->where('user_id', $server->user_id)
            ->where('node_id', $server->node_id)
            ->with('servers:id,name,status')
            ->orderBy('name')
            ->get()
            ->map(fn (Interconnect $ic): array => [
                'id' => $ic->id,
                'name' => $ic->name,
                'servers' => $ic->servers->map(fn (Server $s): array => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'status' => $s->status,
                ])->all(),
                'is_member' => $ic->servers->contains('id', $server->id),
            ])
            ->all();

        $eligibleServers = Server::query()
            ->where('user_id', $server->user_id)
            ->where('node_id', $server->node_id)
            ->select(['id', 'name', 'status'])
            ->orderBy('name')
            ->get()
            ->map(fn (Server $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'status' => $s->status,
            ])
            ->all();

        return Inertia::render('server/networking/interconnect', [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
                'status' => $server->status,
            ],
            'interconnects' => $interconnects,
            'eligibleServers' => $eligibleServers,
        ]);
    }

    public function store(StoreInterconnectRequest $request, Server $server): RedirectResponse
    {
        $this->authorizeServerAccess($request, $server);

        $existing = Interconnect::query()
            ->where('user_id', $server->user_id)
            ->where('node_id', $server->node_id)
            ->where('name', $request->validated('name'))
            ->first();

        if ($existing) {
            return Redirect::back()->withErrors([
                'name' => 'An interconnect with this name already exists on this node.',
            ]);
        }

        $interconnect = Interconnect::query()->create([
            'user_id' => $server->user_id,
            'node_id' => $server->node_id,
            'name' => $request->validated('name'),
        ]);

        $interconnect->servers()->attach($server->id);

        return Redirect::back()->with('success', 'Interconnect created.');
    }

    public function join(Request $request, Server $server, Interconnect $interconnect): RedirectResponse
    {
        $this->authorizeServerAccess($request, $server);
        $this->authorizeInterconnectAccess($server, $interconnect);

        if ($interconnect->servers()->where('server_id', $server->id)->exists()) {
            return Redirect::back()->with('success', 'Already a member.');
        }

        $interconnect->servers()->attach($server->id);

        return Redirect::back()->with('success', 'Joined interconnect.');
    }

    public function leave(Request $request, Server $server, Interconnect $interconnect): RedirectResponse
    {
        $this->authorizeServerAccess($request, $server);
        $this->authorizeInterconnectAccess($server, $interconnect);

        $interconnect->servers()->detach($server->id);

        if ($interconnect->servers()->count() === 0) {
            $interconnect->delete();

            return Redirect::back()->with('success', 'Left and deleted empty interconnect.');
        }

        return Redirect::back()->with('success', 'Left interconnect.');
    }

    public function addServer(Request $request, Server $server, Interconnect $interconnect): RedirectResponse
    {
        $this->authorizeServerAccess($request, $server);
        $this->authorizeInterconnectAccess($server, $interconnect);

        $validated = $request->validate([
            'server_id' => ['required', 'integer', 'exists:servers,id'],
        ]);

        $targetServer = Server::query()->findOrFail($validated['server_id']);

        abort_unless(
            $targetServer->user_id === $server->user_id && $targetServer->node_id === $server->node_id,
            422,
            'The server must belong to you and be on the same node.',
        );

        if ($interconnect->servers()->where('server_id', $targetServer->id)->exists()) {
            return Redirect::back()->with('success', 'Server is already a member.');
        }

        $interconnect->servers()->attach($targetServer->id);

        return Redirect::back()->with('success', "{$targetServer->name} added to interconnect.");
    }

    public function removeServer(Request $request, Server $server, Interconnect $interconnect): RedirectResponse
    {
        $this->authorizeServerAccess($request, $server);
        $this->authorizeInterconnectAccess($server, $interconnect);

        $validated = $request->validate([
            'server_id' => ['required', 'integer', 'exists:servers,id'],
        ]);

        $interconnect->servers()->detach($validated['server_id']);

        if ($interconnect->servers()->count() === 0) {
            $interconnect->delete();

            return Redirect::back()->with('success', 'Server removed and empty interconnect deleted.');
        }

        return Redirect::back()->with('success', 'Server removed from interconnect.');
    }

    public function destroy(Request $request, Server $server, Interconnect $interconnect): RedirectResponse
    {
        $this->authorizeServerAccess($request, $server);
        $this->authorizeInterconnectAccess($server, $interconnect);

        $interconnect->delete();

        return Redirect::back()->with('success', 'Interconnect deleted.');
    }

    private function authorizeInterconnectAccess(Server $server, Interconnect $interconnect): void
    {
        abort_unless(
            $interconnect->user_id === $server->user_id && $interconnect->node_id === $server->node_id,
            422,
            'This interconnect does not belong to you on this node.',
        );
    }
}
