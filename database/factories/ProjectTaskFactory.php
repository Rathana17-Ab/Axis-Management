<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\WorkStatus;
use App\Models\Module;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectTask>
 */
class ProjectTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'emp_id' => User::factory(),
            'assigned_by_id' => null,
            'name' => fake()->words(3, true),
            'description' => "*Primary scope\n- Sub-task",
            'priority' => TaskPriority::High,
            'start_date' => now()->subWeek(),
            'deadline' => now()->addWeeks(2),
            'status' => WorkStatus::Processing,
            'progress' => 25,
            'est_hours' => 8,
            'act_seconds' => 0,
            'is_running' => false,
            'timer_started_at' => null,
            'sort_order' => 1,
        ];
    }

    /**
     * A task that has not been started yet.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WorkStatus::NotStarted,
            'progress' => 0,
        ]);
    }

    /**
     * A task whose chronometer is currently running.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_running' => true,
            'timer_started_at' => now(),
        ]);
    }
}
