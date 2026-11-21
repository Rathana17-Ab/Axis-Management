<?php

namespace Database\Factories;

use App\Enums\WorkStatus;
use App\Models\Module;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Axis '.fake()->unique()->words(2, true);
        $customer = fake()->name();

        return [
            'project_id' => Project::factory(),
            'code' => 'PROJ-'.fake()->unique()->numberBetween(1000, 1799),
            'name' => $name,
            'proj_badge' => strtoupper($name[0]),
            'customer_name' => $customer,
            'customer_sub' => '('.fake()->company().')',
            'cust_badge' => strtoupper($customer[0]),
            'product_domain' => fake()->randomElement(['Axis Core Suite', 'Axis Hospitality PMS', 'Axis Commercial POS', 'Axis Enterprise Finance']),
            'start_date' => fake()->dateTimeBetween('-6 months', '-1 month'),
            'deadline' => fake()->dateTimeBetween('+1 month', '+6 months'),
            'manager_name' => fake()->name(),
            'team_members' => [fake()->name(), fake()->name()],
            'status' => WorkStatus::Processing,
            'overdue_label' => '30 days remaining',
        ];
    }
}
