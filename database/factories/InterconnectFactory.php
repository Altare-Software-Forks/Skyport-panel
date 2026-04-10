<?php

namespace Database\Factories;

use App\Models\Interconnect;
use App\Models\Node;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interconnect>
 */
class InterconnectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'node_id' => Node::factory(),
            'name' => fake()->unique()->word(),
        ];
    }
}
