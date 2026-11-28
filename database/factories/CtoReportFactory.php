<?php

namespace Database\Factories;

use App\Enums\ReportType;
use App\Models\CtoReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CtoReport>
 */
class CtoReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_type' => ReportType::Daily,
            'report_date' => now()->toDateString(),
            'handler_name' => fake()->name(),
            'module_name' => 'Axis Core System',
            'summary' => fake()->sentence(),
            'blocker' => 'None',
            'hours_logged' => 8,
        ];
    }

    /**
     * A report filed by the given team member.
     */
    public function authoredBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'handler_name' => $user->name,
        ]);
    }
}
