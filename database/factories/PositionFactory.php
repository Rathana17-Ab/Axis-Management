<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle().' '.fake()->unique()->numberBetween(10, 99);

        return [
            'name' => $name,
            'code' => Str::slug($name).'-'.fake()->unique()->randomDigitNotNull(),
            'description' => fake()->sentence(),
            'is_leader' => false,
            'is_active' => true,
        ];
    }
}
