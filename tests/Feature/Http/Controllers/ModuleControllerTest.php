<?php

use App\Enums\WorkStatus;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;

describe('store', function () {
    it('creates a module with the next code and an initial architecture task', function () {
        $this->freezeTime();
        User::factory()->create(['name' => 'Kong Rathana']);
        Module::factory()->create(['code' => 'PROJ-1888']);
        $project = Project::factory()->create(['name' => 'EOT Axis']);
        $lead = User::factory()->create(['name' => 'Ti Tong']);

        $response = $this->postJson(route('admin.api.modules.store'), [
            'project_id' => $project->id,
            'name' => 'Axis Payroll',
            'customer_name' => 'Ms. Dara',
            'manager_name' => 'Ti Tong',
            'deadline' => now()->addDays(10)->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'PROJ-1889')
            ->assertJsonPath('data.projBadge', 'A')
            ->assertJsonPath('data.custBadge', 'M')
            ->assertJsonPath('data.team', ['Ti Tong', 'Kong Rathana'])
            ->assertJsonPath('data.overdue', '10 days remaining')
            ->assertJsonPath('data.tasks.0.name', 'Module Architecture & Setup')
            ->assertJsonPath('data.tasks.0.handler', 'Ti Tong')
            ->assertJsonPath('data.tasks.0.employeeId', $lead->id)
            ->assertJsonPath('data.projectId', $project->id);

        $module = Module::firstWhere('code', 'PROJ-1889');
        expect($module->project_id)->toBe($project->id)
            ->and($module->status)->toBe(WorkStatus::Processing)
            ->and($module->tasks()->count())->toBe(1);
    });

    it('stores the module description and start date', function () {
        $project = Project::factory()->create();

        $this->postJson(route('admin.api.modules.store'), [
            'project_id' => $project->id,
            'name' => 'Axis Commerce',
            'description' => 'Online ordering',
            'customer_name' => 'Ms. Dara',
            'manager_name' => 'Ti Tong',
            'start_date' => '2026-12-01',
            'deadline' => '2027-03-01',
        ])
            ->assertCreated()
            ->assertJsonPath('data.description', 'Online ordering')
            ->assertJsonPath('data.startDate', '2026-12-01');
    });

    it('returns 422 when the required module fields are missing', function () {
        $this->postJson(route('admin.api.modules.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id', 'name', 'customer_name', 'manager_name', 'deadline']);

        expect(Module::count())->toBe(0);
    });
});

describe('update', function () {
    it('updates the project status', function () {
        $module = Module::factory()->create();

        $this->patchJson(route('admin.api.modules.update', $module), ['status' => 'Finished'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Finished');

        expect($module->fresh()->status)->toBe(WorkStatus::Finished);
    });

    it('recalculates the overdue label when the deadline moves into the past', function () {
        $this->freezeTime();
        $module = Module::factory()->create();

        $this->patchJson(route('admin.api.modules.update', $module), ['deadline' => now()->subDays(3)->toDateString()])
            ->assertOk()
            ->assertJsonPath('data.overdue', '3 days overdue');
    });

    it('replaces the team attendees', function () {
        $module = Module::factory()->create(['team_members' => ['Ti Tong']]);

        $this->patchJson(route('admin.api.modules.update', $module), ['team_members' => ['Ti Tong', 'Sopha Eot']])
            ->assertOk();

        expect($module->fresh()->team_members)->toBe(['Ti Tong', 'Sopha Eot']);
    });

    it('updates the name, customer and project, refreshing the list badges', function () {
        $module = Module::factory()->create(['name' => 'Old name', 'customer_name' => 'Old customer']);
        $project = Project::factory()->create(['name' => 'EOT Axis']);

        $this->patchJson(route('admin.api.modules.update', $module), [
            'project_id' => $project->id,
            'name' => 'Spa Booking',
            'customer_name' => 'Ms. Dara',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Spa Booking')
            ->assertJsonPath('data.customer', 'Ms. Dara')
            ->assertJsonPath('data.projectId', $project->id)
            ->assertJsonPath('data.projectName', 'EOT Axis')
            ->assertJsonPath('data.projBadge', 'S')
            ->assertJsonPath('data.custBadge', 'M');
    });

    it('returns 422 for a blank name or customer and an unknown project', function () {
        $module = Module::factory()->create();

        $this->patchJson(route('admin.api.modules.update', $module), ['name' => '', 'customer_name' => '', 'project_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'customer_name', 'project_id']);
    });

    it('returns 422 for an unknown status', function () {
        $module = Module::factory()->create();

        $this->patchJson(route('admin.api.modules.update', $module), ['status' => 'Archived'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    });
});
