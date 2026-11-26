<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkQueue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkQueue>
 */
class WorkQueueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'assignment_rule' => fake()->optional()->sentence(),
            'owner_id' => User::factory(),
            'is_active' => true,
        ];
    }
}
