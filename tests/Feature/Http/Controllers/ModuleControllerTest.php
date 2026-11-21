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
            ->assertJsonPath('data.sortOrder', 2)
            ->assertJsonPath('data.projBadge', 'A')
            ->assertJsonPath('data.custBadge', 'M')
            ->assertJsonPath('data.team', ['Ti Tong', 'Kong Rathana'])
            ->assertJsonPath('data.overdue', '10 days remaining')
            ->assertJsonPath('data.tasks.0.name', 'Module Architecture & Setup')
            ->assertJsonPath('data.tasks.0.handler', 'Ti Tong')
            ->assertJsonPath('data.tasks.0.employeeId', $lead->id)
            ->assertJsonPath('data.projectId', $project->id)
            ->assertJsonPath('data.customer', 'Ms. Dara')
            ->assertJsonPath('data.customerSub', '(Axis Client)')
            ->assertJsonPath('data.product', 'Axis Core Suite')
            ->assertJsonPath('data.manager', 'Ti Tong')
            ->assertJsonPath('data.status', 'Processing');

        $module = Module::firstWhere('code', 'PROJ-1889');
        expect($module->project_id)->toBe($project->id)
            ->and($module->sort_order)->toBe(2)
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

    it('returns 422 when the deadline is before the default start date', function () {
        $this->freezeTime();
        $project = Project::factory()->create();

        $this->postJson(route('admin.api.modules.store'), [
            'project_id' => $project->id,
            'name' => 'Axis Payroll',
            'customer_name' => 'Ms. Dara',
            'manager_name' => 'Ti Tong',
            'deadline' => now()->subDay()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('deadline');

        expect(Module::count())->toBe(0);
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

    it('returns 422 when the new deadline is before the module start date', function () {
        $module = Module::factory()->create(['start_date' => '2026-12-01']);

        $this->patchJson(route('admin.api.modules.update', $module), ['deadline' => '2026-11-30'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('deadline');
    });

    it('derives the overdue label from the current date rather than a stale snapshot', function () {
        $this->freezeTime();
        $module = Module::factory()->create([
            'deadline' => now()->addDays(4)->toDateString(),
            'overdue_label' => '90 days remaining',
        ]);

        $bootstrap = $this->get(route('admin.dashboard'))->viewData('bootstrap');
        $moduleData = collect($bootstrap['projects'])->firstWhere('id', $module->id);

        expect($moduleData['overdue'])->toBe('4 days remaining');
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
