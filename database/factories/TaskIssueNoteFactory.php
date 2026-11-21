<?php

namespace Database\Factories;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\ProjectTask;
use App\Models\TaskIssueNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskIssueNote>
 */
class TaskIssueNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_task_id' => ProjectTask::factory(),
            'category' => 'Bug / Blocker',
            'severity' => IssueSeverity::High,
            'git_branch' => 'fix/'.fake()->slug(2),
            'status' => IssueStatus::Open,
            'encountered' => fake()->sentence(),
            'resolution' => fake()->sentence(),
            'author_name' => fake()->name(),
        ];
    }

    /**
     * A note whose fix has been applied.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => IssueStatus::Resolved]);
    }
}
