<?php

namespace Database\Factories;

use App\Enums\FeatureUpgradeState;
use App\Models\FeatureUpgrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeatureUpgrade>
 */
class FeatureUpgradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'label' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'state' => FeatureUpgradeState::Locked,
        ];
    }
}
