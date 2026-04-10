<?php

namespace Database\Factories;

use App\Models\Allocation;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerTransfer>
 */
class ServerTransferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'source_node_id' => Node::factory(),
            'target_node_id' => Node::factory(),
            'target_allocation_id' => Allocation::factory(),
            'status' => 'archiving',
        ];
    }
}
