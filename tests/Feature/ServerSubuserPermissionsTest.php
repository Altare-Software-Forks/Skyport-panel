<?php

use App\Models\Allocation;
use App\Models\Cargo;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerUser;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

function serverSubuserPermissionsDependencies(): array
{
    $location = Location::factory()->create();
    $node = Node::factory()->create(['location_id' => $location->id]);
    $cargo = Cargo::factory()->create();
    $owner = User::factory()->create();
    $allocation = Allocation::factory()->create(['node_id' => $node->id]);

    $server = Server::factory()->create([
        'allocation_id' => $allocation->id,
        'cargo_id' => $cargo->id,
        'name' => 'Subuser Server',
        'node_id' => $node->id,
        'status' => 'running',
        'user_id' => $owner->id,
    ]);

    return [
        'server' => $server,
        'owner' => $owner,
    ];
}

test('subuser with console permission can access console but not files or settings', function () {
    $deps = serverSubuserPermissionsDependencies();
    $subuser = User::factory()->create();

    ServerUser::factory()->create([
        'server_id' => $deps['server']->id,
        'user_id' => $subuser->id,
        'permissions' => [ServerUser::PERMISSION_CONSOLE],
    ]);

    actingAs($subuser);

    get("/server/{$deps['server']->id}/console")->assertOk();
    get("/server/{$deps['server']->id}/files")->assertForbidden();
    get("/server/{$deps['server']->id}/settings")->assertForbidden();
    get("/server/{$deps['server']->id}/backups")->assertForbidden();
    get("/server/{$deps['server']->id}/networking/allocations")->assertForbidden();
    get("/server/{$deps['server']->id}/networking/firewall")->assertForbidden();
});

test('subuser without power permission is forbidden from sending power actions', function () {
    $deps = serverSubuserPermissionsDependencies();
    $subuser = User::factory()->create();

    ServerUser::factory()->create([
        'server_id' => $deps['server']->id,
        'user_id' => $subuser->id,
        'permissions' => [ServerUser::PERMISSION_CONSOLE],
    ]);

    actingAs($subuser);

    postJson("/api/client/servers/{$deps['server']->id}/power", [
        'signal' => 'start',
    ])->assertForbidden();
});

test('subuser with power permission can send power actions', function () {
    $deps = serverSubuserPermissionsDependencies();
    $subuser = User::factory()->create();

    ServerUser::factory()->create([
        'server_id' => $deps['server']->id,
        'user_id' => $subuser->id,
        'permissions' => [ServerUser::PERMISSION_POWER],
    ]);

    actingAs($subuser);

    // Will fail with 422 if authorized because daemon credentials aren't set up, proving authorization passed (not 403)
    postJson("/api/client/servers/{$deps['server']->id}/power", [
        'signal' => 'start',
    ])->assertStatus(422);
});
