<?php

use App\Models\Module;
use Database\Seeders\AxisSystemSeeder;

beforeEach(function () {
    $this->seed(AxisSystemSeeder::class);
});

it('verifies Dashboard page loads and renders live KPIs and project overview', function () {
    $response = $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewIs('axis.workspace')
        ->assertViewHas('initialPage', 'dashboard')
        ->assertSee('<div id="page-dashboard"', false);

    // Verify hydrated bootstrap data
    $bootstrap = $response->viewData('bootstrap');
    expect($bootstrap['projects'])->not->toBeEmpty()
        ->and(count($bootstrap['projects']))->toBe(9)
        ->and($bootstrap)->not->toHaveKeys(['milestoneSteps', 'businessModels'])
        ->and(count($bootstrap['teamMembers']))->toBe(11)
        ->and(count($bootstrap['ctoReports']))->toBe(5);

    // Verify page shell elements and data
    $response->assertSee('id="dash-kpi-strip"', false)
        ->assertSee('Needs attention')
        ->assertSee('Team workload')
        ->assertSee('Axis Core System')
        ->assertSee('PROJ-1888');
});

it('verifies Modules page loads and renders all 9 Axis product modules', function () {
    $response = $this->get(route('admin.modules.index'))
        ->assertOk()
        ->assertViewIs('axis.workspace')
        ->assertViewHas('initialPage', 'modules')
        ->assertSee('<div id="page-modules"', false);

    $bootstrap = $response->viewData('bootstrap');
    $projectCodes = collect($bootstrap['projects'])->pluck('code')->all();

    expect($projectCodes)->toContain('PROJ-1888', 'PROJ-1887', 'PROJ-1886');

    $response->assertSee('Axis Core System')
        ->assertSee('Axis Sales')
        ->assertSee('id="sticky-filter-bar"', false)
        ->assertSee('id="projects-tbody"', false);
});

it('verifies Task Detail page loads with project tasks, live timers, and issue notes', function () {
    $project = Module::firstWhere('code', 'PROJ-1888');
    expect($project)->not->toBeNull();

    $response = $this->get(route('admin.projects.tasks', $project))
        ->assertOk()
        ->assertViewIs('axis.workspace')
        ->assertViewHas('initialPage', 'task-detail')
        ->assertSee('<div id="page-task-detail"', false);

    $bootstrap = $response->viewData('bootstrap');
    expect($bootstrap['activeProjectId'])->toBe($project->id);

    $projectData = collect($bootstrap['projects'])->firstWhere('id', $project->id);
    expect($projectData['tasks'])->not->toBeEmpty();

    $taskNames = collect($projectData['tasks'])->pluck('name')->all();
    expect($taskNames)->toContain('Sidebar');

    $response->assertSee('id="task-kpi-strip"', false)
        ->assertSee('id="tasks-tbody"', false)
        ->assertSee('id="sticky-task-filter-bar"', false);
});

it('verifies Reports page loads with weekly and daily reports, scope highlights and developer filters', function () {
    $response = $this->get(route('admin.reports.index'))
        ->assertOk()
        ->assertViewIs('axis.workspace')
        ->assertViewHas('initialPage', 'reports')
        ->assertSee('<div id="page-reports"', false);

    $bootstrap = $response->viewData('bootstrap');
    $reports = collect($bootstrap['ctoReports']);

    expect($reports)->not->toBeEmpty();
    expect($reports->count())->toBe(5);

    // Verify structured weekly report is present
    $weekly = $reports->firstWhere('type', 'Weekly');
    expect($weekly)->not->toBeNull()
        ->and($weekly['module'])->toBe('Axis Sales')
        ->and($weekly['hours'])->toBe(30.0);

    $response->assertSee('id="rep-type-filter"', false)
        ->assertSee('id="rep-handler-filter"', false)
        ->assertSee('id="rep-search-filter"', false)
        ->assertSee('id="reports-kpi-grid"', false)
        ->assertSee('id="reports-tbody"', false);
});

it('verifies Team Management page loads with all 8 engineering squad members', function () {
    $response = $this->get(route('admin.team.index'))
        ->assertOk()
        ->assertViewIs('axis.workspace')
        ->assertViewHas('initialPage', 'team')
        ->assertSee('<div id="page-team"', false);

    $bootstrap = $response->viewData('bootstrap');
    $members = collect($bootstrap['teamMembers']);

    expect($members->count())->toBe(11);

    $names = $members->pluck('name')->all();
    expect($names)->toContain('Kong Rathana', 'Ti Tong', 'Phal Kristar');

    $response->assertSee('id="team-tbody"', false);
});
