<?php

use App\Enums\WorkStatus;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('creates a top-level project', function () {
    $this->postJson(route('admin.api.projects.store'), [
        'name' => 'EOT Axis',
        'start_date' => '2026-02-12',
        'deadline' => '2027-02-28',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'EOT Axis')
        ->assertJsonPath('data.startDate', '2026-02-12')
        ->assertJsonPath('data.deadline', '2027-02-28')
        ->assertJsonPath('data.moduleCount', 0);

    expect(Project::firstWhere('name', 'EOT Axis'))->not->toBeNull();
});

it('returns 422 for a duplicate project name', function () {
    Project::factory()->create(['name' => 'EOT Axis']);

    $this->postJson(route('admin.api.projects.store'), ['name' => 'EOT Axis'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('returns 422 when the deadline is before the start date', function () {
    $this->postJson(route('admin.api.projects.store'), [
        'name' => 'EOT Axis',
        'start_date' => '2026-05-01',
        'deadline' => '2026-04-01',
    ])->assertUnprocessable()->assertJsonValidationErrors('deadline');
});

it('returns 422 when an updated deadline is before the project start date', function () {
    $project = Project::factory()->create(['start_date' => '2026-05-01']);

    $this->patchJson(route('admin.api.projects.update', $project), ['deadline' => '2026-04-01'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('deadline');
});

it('updates a project and reports how many modules it has', function () {
    $project = Project::factory()->create();
    Module::factory()->count(2)->for($project)->create();

    $this->patchJson(route('admin.api.projects.update', $project), ['name' => 'EOT Axis v2'])
        ->assertOk()
        ->assertJsonPath('data.name', 'EOT Axis v2')
        ->assertJsonPath('data.moduleCount', 2);
});

it('stores the description and status, defaulting new projects to not yet started', function () {
    $this->postJson(route('admin.api.projects.store'), [
        'name' => 'EOT Axis',
        'description' => 'Sales and commerce suite',
    ])
        ->assertCreated()
        ->assertJsonPath('data.description', 'Sales and commerce suite')
        ->assertJsonPath('data.status', 'Not yet started');

    $project = Project::factory()->create();

    $this->patchJson(route('admin.api.projects.update', $project), ['status' => 'Finished'])
        ->assertOk()
        ->assertJsonPath('data.status', 'Finished');

    expect($project->fresh()->status)->toBe(WorkStatus::Finished);
});

it('returns 422 for a status outside the sheet values', function () {
    $this->postJson(route('admin.api.projects.store'), ['name' => 'EOT Axis', 'status' => 'On Hold'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('deletes an empty project', function () {
    $project = Project::factory()->create();

    $this->deleteJson(route('admin.api.projects.destroy', $project))->assertNoContent();

    expect(Project::count())->toBe(0);
});

it('refuses to delete a project that still has modules', function () {
    $project = Project::factory()->create();
    Module::factory()->for($project)->create();

    $this->deleteJson(route('admin.api.projects.destroy', $project))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This project still has modules. Move or remove them before deleting it.');

    expect(Project::count())->toBe(1)->and(Module::count())->toBe(1);
});
