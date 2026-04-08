<?php

use App\Models\Allocation;
use App\Models\Cargo;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function homeServerDependencies(): array
{
    $location = Location::factory()->create();
    $node = Node::factory()->create(['location_id' => $location->id]);
    $cargo = Cargo::factory()->create();

    return [
        'cargo' => $cargo,
        'node' => $node,
    ];
}

test('users only see their own servers on the home page', function () {
    $dependencies = homeServerDependencies();
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $ownServer = Server::factory()->create([
        'allocation_id' => Allocation::factory()->create(['node_id' => $dependencies['node']->id])->id,
        'cargo_id' => $dependencies['cargo']->id,
        'name' => 'Owned Server',
        'node_id' => $dependencies['node']->id,
        'user_id' => $user->id,
    ]);
    Server::factory()->create([
        'allocation_id' => Allocation::factory()->create(['node_id' => $dependencies['node']->id])->id,
        'cargo_id' => $dependencies['cargo']->id,
        'name' => 'Other Server',
        'node_id' => $dependencies['node']->id,
        'user_id' => $otherUser->id,
    ]);

    actingAs($user);

    get('/home')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('filters.scope', 'mine')
            ->has('servers.data', 1)
            ->where('servers.data.0.id', $ownServer->id)
            ->where('servers.data.0.name', 'Owned Server'));
});

test('admins can toggle between their servers and all servers on the home page', function () {
    $dependencies = homeServerDependencies();
    $admin = User::factory()->create(['is_admin' => true]);
    $otherUser = User::factory()->create();
    $adminServer = Server::factory()->create([
        'allocation_id' => Allocation::factory()->create(['node_id' => $dependencies['node']->id])->id,
        'cargo_id' => $dependencies['cargo']->id,
        'name' => 'Admin Server',
        'node_id' => $dependencies['node']->id,
        'user_id' => $admin->id,
    ]);
    $otherServer = Server::factory()->create([
        'allocation_id' => Allocation::factory()->create(['node_id' => $dependencies['node']->id])->id,
        'cargo_id' => $dependencies['cargo']->id,
        'name' => 'Shared Server',
        'node_id' => $dependencies['node']->id,
        'user_id' => $otherUser->id,
    ]);

    actingAs($admin);

    get('/home')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.scope', 'mine')
            ->has('servers.data', 1)
            ->where('servers.data.0.id', $adminServer->id));

    get('/home?scope=all')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.scope', 'all')
            ->has('servers.data', 2)
            ->where('servers.data.0.name', 'Admin Server')
            ->where('servers.data.1.name', 'Shared Server')
            ->where('servers.data.1.user.name', $otherUser->name)
            ->where('servers.data.1.id', $otherServer->id));
});

test('admins can remember the all servers scope on the home page', function () {
    $dependencies = homeServerDependencies();
    $admin = User::factory()->create(['is_admin' => true]);
    $otherUser = User::factory()->create();

    Server::factory()->create([
        'allocation_id' => Allocation::factory()->create(['node_id' => $dependencies['node']->id])->id,
        'cargo_id' => $dependencies['cargo']->id,
        'name' => 'Admin Server',
        'node_id' => $dependencies['node']->id,
        'user_id' => $admin->id,
    ]);
    $otherServer = Server::factory()->create([
        'allocation_id' => Allocation::factory()->create(['node_id' => $dependencies['node']->id])->id,
        'cargo_id' => $dependencies['cargo']->id,
        'name' => 'Shared Server',
        'node_id' => $dependencies['node']->id,
        'user_id' => $otherUser->id,
    ]);

    actingAs($admin);

    $this->withCookie('home_server_scope', 'all')
        ->get('/home')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.scope', 'all')
            ->has('servers.data', 2)
            ->where('servers.data.1.id', $otherServer->id)
            ->where('servers.data.1.user.name', $otherUser->name));
});

test('home page paginates server results', function () {
    $dependencies = homeServerDependencies();
    $user = User::factory()->create();

    foreach (range(1, 11) as $number) {
        Server::factory()->create([
            'allocation_id' => Allocation::factory()->create([
                'node_id' => $dependencies['node']->id,
            ])->id,
            'cargo_id' => $dependencies['cargo']->id,
            'name' => sprintf('Server %02d', $number),
            'node_id' => $dependencies['node']->id,
            'user_id' => $user->id,
        ]);
    }

    actingAs($user);

    get('/home?page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('servers.current_page', 2)
            ->where('servers.last_page', 2)
            ->where('servers.total', 11)
            ->has('servers.data', 1)
            ->where('servers.data.0.name', 'Server 11'));
});

test('non admins cannot use all scope on the home page', function () {
    $dependencies = homeServerDependencies();
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $userServer = Server::factory()->create([
        'allocation_id' => Allocation::factory()->create(['node_id' => $dependencies['node']->id])->id,
        'cargo_id' => $dependencies['cargo']->id,
        'name' => 'Owned Server',
        'node_id' => $dependencies['node']->id,
        'user_id' => $user->id,
    ]);
    Server::factory()->create([
        'allocation_id' => Allocation::factory()->create(['node_id' => $dependencies['node']->id])->id,
        'cargo_id' => $dependencies['cargo']->id,
        'name' => 'Other Server',
        'node_id' => $dependencies['node']->id,
        'user_id' => $otherUser->id,
    ]);

    actingAs($user);

    get('/home?scope=all')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.scope', 'mine')
            ->has('servers.data', 1)
            ->where('servers.data.0.id', $userServer->id));
});

test('home page renders an empty server list when the servers table is unavailable', function () {
    $user = User::factory()->create();

    Schema::drop('servers');

    actingAs($user);

    get('/home')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('filters.scope', 'mine')
            ->has('servers.data', 0));
});

test('home page still renders when a server allocation has been removed', function () {
    $dependencies = homeServerDependencies();
    $user = User::factory()->create();
    $allocation = Allocation::factory()->create([
        'node_id' => $dependencies['node']->id,
    ]);
    $server = Server::factory()->create([
        'allocation_id' => $allocation->id,
        'cargo_id' => $dependencies['cargo']->id,
        'node_id' => $dependencies['node']->id,
        'user_id' => $user->id,
    ]);

    $allocation->delete();

    actingAs($user);

    get('/home')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('servers.data.0.id', $server->id)
            ->where('servers.data.0.allocation', null));
});
