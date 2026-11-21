<?php

namespace Database\Factories;

use App\Enums\EmployeePosition;
use App\Enums\EmployeeStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'job_title' => 'Full-Stack Developer',
            'department' => null,
            'position' => EmployeePosition::Developer,
            'status' => EmployeeStatus::Active,
            'can_login' => true,
        ];
    }

    /**
     * A team leader, allowed to assign tasks.
     */
    public function leader(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => EmployeePosition::Leader,
        ]);
    }

    /**
     * An employee who is no longer active and can't be given new tasks.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmployeeStatus::Inactive,
        ]);
    }

    /**
     * Indicate that the member's login access is locked.
     */
    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_login' => false,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
