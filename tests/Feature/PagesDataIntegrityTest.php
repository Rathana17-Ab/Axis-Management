<?php

use App\Models\Module;
use Database\Seeders\AxisSystemSeeder;

beforeEach(function () {
    $this->seed(AxisSystemSeeder::class);
});

it('verifies Dashboard page loads with only the module, project, task and team lists', function () {
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
    $response->assertDontSee('id="dash-kpi-strip"', false)
        ->assertSee('id="dash-modules-cards"', false)
        ->assertSee('id="dash-modules-order"', false)
        ->assertSee('id="dash-project-cards"', false)
        ->assertSee('id="dash-project-order"', false)
        ->assertSee('id="dash-task-cards"', false)
        ->assertSee('id="dash-task-order"', false)
        ->assertSee('Team workload')
        ->assertSee('id="dash-team-body"', false)
        ->assertDontSee('id="dash-modules-card"', false)
        ->assertDontSee('Needs attention')
        ->assertDontSee('id="dash-attention"', false)
        ->assertSee('Axis Core System')
        ->assertSee('PROJ-1888');

    $html = $response->getContent();
    expect(strpos($html, 'dash-modules-toolbar'))->toBeLessThan(strpos($html, 'dash-project-toolbar'))
        ->and(strpos($html, 'dash-project-toolbar'))->toBeLessThan(strpos($html, 'dash-task-toolbar'))
        ->and(strpos($html, 'dash-task-toolbar'))->toBeLessThan(strpos($html, 'dash-team-title'));
});

it('verifies Modules page loads with the module list and no customer column', function () {
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
        ->assertDontSee('id="flt-customer"', false)
        ->assertDontSee('data-mod-sort="customer"', false)
        ->assertDontSee('value="customer:asc"', false)
        ->assertSee('id="projects-tbody"', false)
        ->assertSee('value="custom:asc" selected', false)
        ->assertSee('rel="icon" type="image/x-icon"', false)
        ->assertSee('favicon.ico', false)
        ->assertSee('images/brand/axis-symbol-white.png', false)
        ->assertSee('>Project hub</div>', false);

    expect($response->getContent())->toMatch('/<div class="(?=[^"]*text-\[16px\])(?=[^"]*whitespace-nowrap)[^"]*">Axis Management<\/div>/');
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

it('verifies Team Management page loads with all 11 team members', function () {
    $response = $this->get(route('admin.team.index'))
        ->assertOk()
        ->assertViewIs('axis.workspace')
        ->assertViewHas('initialPage', 'team')
        ->assertSee('<div id="page-team"', false);

    $bootstrap = $response->viewData('bootstrap');
    $members = collect($bootstrap['teamMembers']);

    expect($members->count())->toBe(11)
        ->and($bootstrap['departments'])->not->toBeEmpty()
        ->and($bootstrap['positions'])->not->toBeEmpty();

    $names = $members->pluck('name')->all();
    expect($names)->toContain('Kong Rathana', 'Ti Tong', 'Phal Kristar');

    $response->assertSee('id="team-tbody"', false)
        ->assertSee('id="departments-tbody"', false)
        ->assertSee('id="positions-tbody"', false)
        ->assertSee('id="modal-department-create"', false)
        ->assertSee('id="modal-position-create"', false)
        ->assertDontSee('id="drawer-department-create"', false)
        ->assertDontSee('id="drawer-position-create"', false)
        ->assertSee('id="drawer-team-member-password"', false)
        ->assertSee('Login username')
        ->assertDontSee('>Login</th>', false);
});

it('ships a non-empty Axis icon for the browser URL bar', function () {
    $favicon = public_path('favicon.ico');

    expect(file_exists($favicon))->toBeTrue()
        ->and(filesize($favicon))->toBeGreaterThan(0)
        ->and(substr(file_get_contents($favicon), 0, 4))->toBe("\x00\x00\x01\x00");
});
