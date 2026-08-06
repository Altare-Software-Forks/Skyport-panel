<?php

use App\Models\Allocation;
use App\Models\Cargo;
use App\Models\Location;
use App\Models\Node;
use App\Models\NodeCredential;
use App\Models\Server;
use App\Models\ServerUser;
use App\Models\User;

use function Pest\Laravel\postJson;

function sftpAuthDependencies(): array
{
    $location = Location::factory()->create();
    $node = Node::factory()->create([
        'daemon_port' => 2800,
        'fqdn' => 'node.example.com',
        'location_id' => $location->id,
        'sftp_port' => 2022,
    ]);
    $cargo = Cargo::factory()->create();

    NodeCredential::factory()->create([
        'daemon_secret_hash' => hash('sha256', 'sftp-daemon-secret'),
        'daemon_secret_issued_at' => now(),
        'node_id' => $node->id,
    ]);

    $user = User::factory()->create([
        'email' => 'player@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $server = Server::factory()->create([
        'allocation_id' => Allocation::factory()->create(['node_id' => $node->id])->id,
        'cargo_id' => $cargo->id,
        'node_id' => $node->id,
        'user_id' => $user->id,
    ]);

    return [
        'node' => $node,
        'server' => $server,
        'user' => $user,
    ];
}

test('valid owner credentials return server info', function () {
    $deps = sftpAuthDependencies();

    postJson('/api/daemon/sftp/auth', [
        'username' => "player@example.com.{$deps['server']->id}",
        'password' => 'secret123',
    ], [
        'Authorization' => 'Bearer sftp-daemon-secret',
    ])
        ->assertOk()
        ->assertJson([
            'server_id' => $deps['server']->id,
            'user_id' => $deps['user']->id,
            'permissions' => ['*'],
        ]);
});

test('subuser with files permission gets their permissions', function () {
    $deps = sftpAuthDependencies();
    $subuser = User::factory()->create([
        'email' => 'sub@example.com',
        'password' => bcrypt('subpass'),
    ]);

    ServerUser::factory()->create([
        'server_id' => $deps['server']->id,
        'user_id' => $subuser->id,
        'permissions' => ['console', 'files'],
    ]);

    postJson('/api/daemon/sftp/auth', [
        'username' => "sub@example.com.{$deps['server']->id}",
        'password' => 'subpass',
    ], [
        'Authorization' => 'Bearer sftp-daemon-secret',
    ])
        ->assertOk()
        ->assertJson([
            'server_id' => $deps['server']->id,
            'user_id' => $subuser->id,
            'permissions' => ['console', 'files'],
        ]);
});

test('subuser without files permission is rejected', function () {
    $deps = sftpAuthDependencies();
    $subuser = User::factory()->create([
        'email' => 'nofiles@example.com',
        'password' => bcrypt('subpass'),
    ]);

    ServerUser::factory()->create([
        'server_id' => $deps['server']->id,
        'user_id' => $subuser->id,
        'permissions' => ['console', 'power'],
    ]);

    postJson('/api/daemon/sftp/auth', [
        'username' => "nofiles@example.com.{$deps['server']->id}",
        'password' => 'subpass',
    ], [
        'Authorization' => 'Bearer sftp-daemon-secret',
    ])->assertForbidden();
});

test('wrong password is rejected', function () {
    $deps = sftpAuthDependencies();

    postJson('/api/daemon/sftp/auth', [
        'username' => "player@example.com.{$deps['server']->id}",
        'password' => 'wrongpassword',
    ], [
        'Authorization' => 'Bearer sftp-daemon-secret',
    ])->assertForbidden();
});

test('missing daemon token is rejected', function () {
    $deps = sftpAuthDependencies();

    postJson('/api/daemon/sftp/auth', [
        'username' => "player@example.com.{$deps['server']->id}",
        'password' => 'secret123',
    ])->assertUnauthorized();
});

test('user without access is rejected', function () {
    $deps = sftpAuthDependencies();
    $stranger = User::factory()->create([
        'email' => 'stranger@example.com',
        'password' => bcrypt('strangerpw'),
    ]);

    postJson('/api/daemon/sftp/auth', [
        'username' => "stranger@example.com.{$deps['server']->id}",
        'password' => 'strangerpw',
    ], [
        'Authorization' => 'Bearer sftp-daemon-secret',
    ])->assertForbidden();
});

test('invalid username format is rejected', function () {
    sftpAuthDependencies();

    postJson('/api/daemon/sftp/auth', [
        'username' => 'nodotshere',
        'password' => 'secret123',
    ], [
        'Authorization' => 'Bearer sftp-daemon-secret',
    ])->assertForbidden();
});
