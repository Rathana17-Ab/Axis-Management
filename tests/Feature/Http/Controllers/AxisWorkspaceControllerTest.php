<?php

use App\Enums\WorkStatus;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;

it('opens the workspace on the page matching each admin route', function (string $routeName, string $page) {
    User::factory()->create();

    $this->get(route($routeName))
        ->assertOk()
        ->assertViewIs('axis.workspace')
        ->assertViewHas('initialPage', $page)
        ->assertSee('<div id="page-'.$page.'" class="app-page active-page space-y-5">', false);
})->with([
    'dashboard' => ['admin.dashboard', 'dashboard'],
    'projects' => ['admin.projects.index', 'projects'],
    'modules' => ['admin.modules.index', 'modules'],
    'cto reports' => ['admin.reports.index', 'reports'],
    'team' => ['admin.team.index', 'team'],
]);

it('opens the task list detail for the requested project', function () {
    $project = Module::factory()->create();

    $this->get(route('admin.projects.tasks', $project))
        ->assertOk()
        ->assertViewHas('initialPage', 'task-detail')
        ->assertViewHas('bootstrap', fn (array $bootstrap): bool => $bootstrap['activeProjectId'] === $project->id);
});

it('returns 404 for the task list of a missing project', function () {
    $this->get('/admin/project-task-list/999')->assertNotFound();
});

it('hydrates running timers with server-accurate elapsed seconds', function () {
    $this->freezeTime();
    $task = ProjectTask::factory()->running()->create(['act_seconds' => 100]);
    $this->travel(90)->seconds();

    $this->get(route('admin.projects.index'))
        ->assertViewHas('bootstrap', function (array $bootstrap) use ($task): bool {
            $projects = json_decode(json_encode($bootstrap['projects']), true);
            $hydrated = collect($projects)->flatMap(fn (array $p): array => $p['tasks'])->firstWhere('id', $task->id);

            return $hydrated['actSeconds'] === 190 && $hydrated['running'] === true;
        });
});

it('escapes user content in the hydrated state so it cannot break out of the script tag', function () {
    Module::factory()->create(['name' => '</script><script>alert(1)</script>']);

    $this->get(route('admin.projects.index'))
        ->assertOk()
        ->assertDontSee('</script><script>alert(1)', false);
});

it('shows the signed-in team member as the acting user', function () {
    User::factory()->create(['name' => 'First Member']);
    $member = User::factory()->create(['name' => 'Switched Member']);

    $this->actingAs($member)
        ->get(route('admin.projects.index'))
        ->assertViewHas('bootstrap', fn (array $bootstrap): bool => $bootstrap['currentUserId'] === $member->id)
        ->assertSee('Switched Member');
});

it('renders the top header bar with page title, live save status and reload', function () {
    User::factory()->create();

    $this->get(route('admin.projects.index'))
        ->assertOk()
        ->assertSee('class="axis-topbar"', false)
        ->assertDontSee('id="header-page-icon"', false)
        ->assertSee('aria-keyshortcuts="Meta+K Control+K"', false)
        ->assertSee('id="header-page-title"', false)
        ->assertSee('id="header-global-search"', false)
        ->assertSee('aria-controls="axis-command-palette"', false)
        ->assertSee('id="axis-command-palette"', false)
        ->assertSee('role="combobox"', false)
        ->assertSee('id="header-sync-pill"', false)
        ->assertSee('aria-label="Reload the latest data"', false)
        ->assertSee('id="header-create-btn"', false);
});

it('shows the live in-progress count in the projects metadata pills', function () {
    User::factory()->create();
    Module::factory()->count(2)->create();
    Module::factory()->create(['status' => WorkStatus::Finished]);

    $this->get(route('admin.projects.index'))
        ->assertSee('<span id="meta-active-count">2 active</span>', false);
});

it('renders the task page KPI strip with the shared KPI card', function () {
    $project = Module::factory()->create();

    $this->get(route('admin.projects.tasks', $project))
        ->assertOk()
        ->assertSee('id="task-kpi-strip"', false)
        ->assertSeeInOrder(['Overall progress', 'Tasks done', 'Time logged', 'Issues &amp; fixes'], false)
        ->assertDontSee('Issues &amp;amp; fixes', false)
        ->assertSee('id="kpi-td-time"', false);
});

it('does not show a fabricated notification count on the header bell', function () {
    User::factory()->create();

    $this->get(route('admin.projects.index'))
        ->assertOk()
        ->assertSee('aria-label="Reports"', false)
        ->assertDontSee('3 Logs')
        ->assertDontSee('Reports, 3 new');
});

it('fills the projects status banner from live data instead of fixed text', function () {
    User::factory()->create();

    $this->get(route('admin.projects.index'))
        ->assertOk()
        ->assertSee('id="axis-attention-title"', false)
        ->assertDontSee("Everything's up to date")
        ->assertDontSee('axis-topbar__decor', false);
});

it('lets the new task drawer pick any module, labelled with its project', function () {
    User::factory()->create();
    $project = Project::factory()->create(['name' => 'EOT Axis']);
    $sales = Module::factory()->for($project)->create(['code' => 'PROJ-1888', 'name' => 'Axis Sales']);
    Module::factory()->for($project)->create(['code' => 'PROJ-1889', 'name' => 'Axis Hotel Commerce']);

    $this->get(route('admin.projects.tasks', $sales))
        ->assertOk()
        ->assertSee('id="new-task-module"', false)
        ->assertSeeInOrder(['PROJ-1888 — Axis Sales (EOT Axis)', 'PROJ-1889 — Axis Hotel Commerce (EOT Axis)']);
});
