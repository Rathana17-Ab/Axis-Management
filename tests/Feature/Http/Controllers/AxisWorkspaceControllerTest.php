<?php

use App\Enums\WorkspaceRole;
use App\Enums\WorkStatus;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SalesAccessSeeder;

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

it('offers only developers as assignees and only leaders as assigners in the new task drawer', function () {
    $developer = User::factory()->create(['name' => 'Dev One']);
    $leader = User::factory()->leader()->create(['name' => 'Lead One']);
    User::factory()->inactive()->create(['name' => 'Former Dev']);

    $response = $this->get(route('admin.projects.index'));

    $response->assertViewHas(
        'developers',
        fn ($developers): bool => $developers->pluck('id')->all() === [$developer->id]
    );

    $html = $response->getContent();
    preg_match('/<select id="new-task-handler"[^>]*>(.*?)<\/select>/s', $html, $assignees);
    preg_match('/<select id="new-task-assigned-by"[^>]*>(.*?)<\/select>/s', $html, $assigners);

    expect($assignees[1])
        ->toContain('Dev One')
        ->not->toContain('Lead One')
        ->not->toContain('Former Dev')
        ->and($assigners[1])
        ->toContain('Lead One')
        ->not->toContain('Dev One');
});

it('renders only the module, project, task and team lists on the dashboard', function () {
    Module::factory()->create(['name' => 'Zeta Module', 'sort_order' => 2]);
    Module::factory()->create(['name' => 'Alpha Module', 'sort_order' => 1]);

    $response = $this->get(route('admin.dashboard'));
    $html = $response->getContent();

    $response->assertOk()
        ->assertDontSee('id="dash-kpi-strip"', false)
        ->assertSee('id="dash-modules-toolbar" class="ent-card flex w-full flex-wrap items-center gap-2 px-3 py-2.5"', false)
        ->assertSee('id="dash-modules-cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-5"', false)
        ->assertSee('<h2 id="dash-modules-title" class="text-sm font-bold text-white">Modules</h2>', false)
        ->assertSee('id="dash-modules-order"', false)
        ->assertSee('id="dash-module-search"', false)
        ->assertSee('id="dash-module-project-filter"', false)
        ->assertSee('id="dash-module-status-filter"', false)
        ->assertSee('id="dash-modules-count"', false)
        ->assertSee('id="dash-modules-sort-label"', false)
        ->assertSee('Order number')
        ->assertSee('Click a card to open the Modules page.')
        ->assertSee('id="dash-project-toolbar"', false)
        ->assertSee('id="dash-project-cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-5"', false)
        ->assertSee('<h2 id="dash-project-title" class="text-sm font-bold text-white">Project list</h2>', false)
        ->assertSee('id="dash-project-order"', false)
        ->assertSee('id="dash-project-search"', false)
        ->assertSee('id="dash-project-status-filter"', false)
        ->assertSee('id="dash-project-count"', false)
        ->assertSee('id="dash-project-sort-label"', false)
        ->assertSee('Click a card to open the Projects page.')
        ->assertSee('id="dash-task-toolbar"', false)
        ->assertSee('<h2 id="dash-task-title" class="text-sm font-bold text-white">Task list</h2>', false)
        ->assertSee('id="dash-task-cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-5"', false)
        ->assertSee('id="dash-task-order"', false)
        ->assertSee('id="dash-task-search"', false)
        ->assertSee('id="dash-task-module-filter"', false)
        ->assertSee('id="dash-task-status-filter"', false)
        ->assertSee('id="dash-task-priority-filter"', false)
        ->assertSee('id="dash-task-count"', false)
        ->assertSee('id="dash-task-sort-label"', false)
        ->assertSee('Click a card to open the Tasks page.')
        ->assertSee('id="dash-team-body"', false)
        ->assertSee('Team workload')
        ->assertDontSee('SoundFX', false)
        ->assertDontSee('id="audio-control-wrapper"', false)
        ->assertDontSee('id="modal-sound-lab"', false)
        ->assertDontSee('Sound effects', false)
        ->assertSee('aria-label="Scroll projects right"', false)
        ->assertSee('aria-label="Scroll tasks right"', false)
        ->assertDontSee('aria-label="Scroll modules right"', false)
        ->assertDontSee('id="dash-modules-card"', false);

    expect(strpos($html, 'dash-modules-toolbar'))->toBeLessThan(strpos($html, 'dash-project-toolbar'))
        ->and(strpos($html, 'dash-project-toolbar'))->toBeLessThan(strpos($html, 'dash-task-toolbar'))
        ->and(strpos($html, 'dash-task-toolbar'))->toBeLessThan(strpos($html, 'dash-team-title'));

    $bootstrap = $response->viewData('bootstrap');
    expect(collect($bootstrap['projects'])->pluck('sortOrder')->sort()->values()->all())->toBe([1, 2])
        ->and($bootstrap['axisProjects'])->not->toBeEmpty()
        ->and(collect($bootstrap['axisProjects'])->first())->toHaveKey('moduleCount');
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

it('offers module reordering to a signed-in Sales Person', function () {
    $this->seed(SalesAccessSeeder::class);
    $sales = User::factory()->create();
    $sales->roles()->attach(Role::query()->where('slug', WorkspaceRole::SalesPerson->value)->firstOrFail());

    $this->actingAs($sales)
        ->get(route('admin.modules.index'))
        ->assertOk()
        ->assertViewHas('bootstrap', fn (array $bootstrap): bool => $bootstrap['canReorderModules'] === true);
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
