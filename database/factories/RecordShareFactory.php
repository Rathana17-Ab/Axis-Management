<?php

namespace Database\Factories;

use App\Enums\RecordShareAbility;
use App\Models\RecordShare;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecordShare>
 */
class RecordShareFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shareable_type' => Team::class,
            'shareable_id' => Team::factory(),
            'user_id' => User::factory(),
            'ability' => RecordShareAbility::View,
        ];
    }
}
