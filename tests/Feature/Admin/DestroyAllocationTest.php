<?php

use App\Models\Allocation;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;

it('deletes an unassigned allocation', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $location = Location::factory()->create();
    $node = Node::factory()->create(['location_id' => $location->id]);
    $allocation = Allocation::factory()->create(['node_id' => $node->id]);

    actingAs($admin);

    delete("/admin/nodes/{$node->id}/allocations/".$allocation->id)
        ->assertRedirect()
        ->assertSessionHas('success', 'Allocation deleted.');

    expect(Allocation::find($allocation->id))->toBeNull();
});

it('cannot delete an allocation assigned to a server', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $location = Location::factory()->create();
    $node = Node::factory()->create(['location_id' => $location->id]);
    $allocation = Allocation::factory()->create(['node_id' => $node->id]);
    $user = User::factory()->create();

    Server::factory()->create([
        'allocation_id' => $allocation->id,
        'node_id' => $node->id,
        'user_id' => $user->id,
    ]);

    actingAs($admin);

    delete("/admin/nodes/{$node->id}/allocations/".$allocation->id)
        ->assertRedirect()
        ->assertSessionHasErrors(['allocation' => 'This allocation is assigned to a server and cannot be deleted.']);

    expect(Allocation::find($allocation->id))->not->toBeNull();
});

it('cannot delete an allocation belonging to another node', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $location = Location::factory()->create();
    $node1 = Node::factory()->create(['location_id' => $location->id]);
    $node2 = Node::factory()->create(['location_id' => $location->id]);
    $allocation = Allocation::factory()->create(['node_id' => $node1->id]);

    actingAs($admin);

    delete("/admin/nodes/{$node2->id}/allocations/".$allocation->id)
        ->assertRedirect()
        ->assertSessionHasErrors(['allocation' => 'This allocation does not belong to this node.']);

    expect(Allocation::find($allocation->id))->not->toBeNull();
});

it('bulk deletes unassigned allocations', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $location = Location::factory()->create();
    $node = Node::factory()->create(['location_id' => $location->id]);
    $a1 = Allocation::factory()->create(['node_id' => $node->id]);
    $a2 = Allocation::factory()->create(['node_id' => $node->id]);
    $a3 = Allocation::factory()->create(['node_id' => $node->id]);

    actingAs($admin);

    delete("/admin/nodes/{$node->id}/allocations/bulk-destroy", ['ids' => [$a1->id, $a2->id, $a3->id]])
        ->assertRedirect()
        ->assertSessionHas('success', '3 allocations deleted.');

    expect(Allocation::find($a1->id))->toBeNull();
    expect(Allocation::find($a2->id))->toBeNull();
    expect(Allocation::find($a3->id))->toBeNull();
});

it('bulk delete rejects when any allocation is assigned', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $location = Location::factory()->create();
    $node = Node::factory()->create(['location_id' => $location->id]);
    $a1 = Allocation::factory()->create(['node_id' => $node->id]);
    $a2 = Allocation::factory()->create(['node_id' => $node->id]);
    $user = User::factory()->create();

    Server::factory()->create([
        'allocation_id' => $a2->id,
        'node_id' => $node->id,
        'user_id' => $user->id,
    ]);

    actingAs($admin);

    delete("/admin/nodes/{$node->id}/allocations/bulk-destroy", ['ids' => [$a1->id, $a2->id]])
        ->assertRedirect()
        ->assertSessionHasErrors('allocation');

    expect(Allocation::find($a1->id))->not->toBeNull();
    expect(Allocation::find($a2->id))->not->toBeNull();
});

it('bulk delete only deletes allocations belonging to the node', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $location = Location::factory()->create();
    $node1 = Node::factory()->create(['location_id' => $location->id]);
    $node2 = Node::factory()->create(['location_id' => $location->id]);
    $a1 = Allocation::factory()->create(['node_id' => $node1->id]);
    $a2 = Allocation::factory()->create(['node_id' => $node2->id]);

    actingAs($admin);

    delete("/admin/nodes/{$node1->id}/allocations/bulk-destroy", ['ids' => [$a1->id, $a2->id]])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Allocation::find($a1->id))->toBeNull();
    expect(Allocation::find($a2->id))->not->toBeNull();
});
