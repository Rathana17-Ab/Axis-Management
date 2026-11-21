<?php

namespace Database\Factories;

use App\Enums\WorkStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'EOT '.fake()->unique()->word(),
            'start_date' => now()->subMonths(2)->toDateString(),
            'deadline' => now()->addMonths(4)->toDateString(),
            'description' => fake()->sentence(),
            'status' => WorkStatus::Processing,
        ];
    }
}
