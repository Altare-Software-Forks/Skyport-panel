<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Client\Concerns\AuthorizesServerAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreFirewallRuleRequest;
use App\Models\FirewallRule;
use App\Models\Server;
use App\Models\ServerUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ServerFirewallController extends Controller
{
    use AuthorizesServerAccess;

    public function index(Request $request, Server $server): Response
    {
        $this->authorizeServerAccess($request, $server, ServerUser::PERMISSION_FIREWALL);

        $rules = $server->firewallRules()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (FirewallRule $rule): array => [
                'id' => $rule->id,
                'direction' => $rule->direction,
                'action' => $rule->action,
                'protocol' => $rule->protocol,
                'source' => $rule->source,
                'port_start' => $rule->port_start,
                'port_end' => $rule->port_end,
                'notes' => $rule->notes,
                'created_at' => $rule->created_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('server/networking/firewall', [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
                'status' => $server->status,
            ],
            'rules' => $rules,
        ]);
    }

    public function store(StoreFirewallRuleRequest $request, Server $server): RedirectResponse
    {
        $this->authorizeServerAccess($request, $server, ServerUser::PERMISSION_FIREWALL);

        $server->firewallRules()->create($request->validated());

        return Redirect::back()->with('success', 'Firewall rule created.');
    }

    public function destroy(Request $request, Server $server, FirewallRule $rule): RedirectResponse
    {
        $this->authorizeServerAccess($request, $server, ServerUser::PERMISSION_FIREWALL);

        abort_unless(
            $rule->server_id === $server->id,
            422,
            'This rule does not belong to this server.',
        );

        $rule->delete();

        return Redirect::back()->with('success', 'Firewall rule deleted.');
    }
}
