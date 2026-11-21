<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeStatus;
use App\Http\Resources\CtoReportResource;
use App\Http\Resources\ModuleResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TeamMemberResource;
use App\Models\CtoReport;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AxisWorkspaceController extends Controller
{
    /**
     * Maps each workspace route name to the prototype page id it opens.
     *
     * @var array<string, string>
     */
    private const PAGES = [
        'admin.dashboard' => 'dashboard',
        'admin.projects.index' => 'projects',
        'admin.modules.index' => 'modules',
        'admin.projects.tasks' => 'task-detail',
        'admin.reports.index' => 'reports',
        'admin.team.index' => 'team',
    ];

    /**
     * Render the workspace shell opened on the page matching the current route.
     */
    public function index(Request $request): View
    {
        return $this->workspace(self::PAGES[$request->route()?->getName()] ?? 'projects');
    }

    /**
     * Render the workspace shell opened on a module's task list detail.
     */
    public function showTaskList(Module $module): View
    {
        return $this->workspace('task-detail', $module->id);
    }

    /**
     * Named API routes exposed to `window.AxisAPI` (the `__ID__` placeholder is filled client-side).
     *
     * @var list<string>
     */
    private const API_ROUTES = [
        'projects.store', 'projects.update', 'projects.destroy',
        'modules.store', 'modules.update',
        'tasks.store', 'tasks.update', 'tasks.timer.toggle', 'tasks.timer.reset', 'tasks.destroy',
        'issues.store', 'issues.update', 'issues.cycle', 'issues.destroy',
        'reports.store', 'reports.show', 'reports.update', 'reports.destroy',
        'team.store', 'team.update', 'team.toggle-login',
        'session.switch',
    ];

    /**
     * Build the hydrated workspace view.
     */
    private function workspace(string $initialPage, ?int $activeProjectId = null): View
    {
        $request = request();

        // `$projects` / bootstrap `projects` hold the *modules* (Axis Core System, Axis Sales, …) —
        // the name predates the Project → Module split and is used throughout the views and JS.
        // Top-level projects (e.g. "EOT Axis") are exposed separately as `axisProjects`.
        // Code order (newest module first) mirrors the prototype's list, where new modules are unshifted.
        $projects = Module::query()
            ->with(['project', 'tasks.issueNotes', 'tasks.employee', 'tasks.assignedBy'])
            ->orderByDesc('code')
            ->get();
        $axisProjects = Project::query()->withCount('modules')->orderBy('name')->get();

        $ctoReports = CtoReport::query()->latest('report_date')->orderBy('id')->get();
        $teamMembers = User::query()->orderBy('id')->get();
        $activeEmployees = $teamMembers->filter(fn (User $member): bool => $member->status === EmployeeStatus::Active);
        $leaders = $activeEmployees->filter(fn (User $member): bool => $member->isLeader())->values();
        $currentUser = $this->actingMember();

        $activeProject = $projects->firstWhere('id', $activeProjectId) ?? $projects->sortBy('id')->first();

        $handlers = collect(config('axis.handlers'))
            ->merge($teamMembers->pluck('name'))
            ->unique()
            ->values();

        $quickSwitchEmails = collect(config('axis.quick_switch_emails'));
        $quickSwitchMembers = $teamMembers
            ->filter(fn (User $member): bool => $quickSwitchEmails->contains($member->email))
            ->sortBy(fn (User $member): int|false => $quickSwitchEmails->search($member->email))
            ->values();

        return view('axis.workspace', [
            'initialPage' => $initialPage,
            'projects' => $projects,
            'handlers' => $handlers,
            'teamMembers' => $teamMembers,
            'employees' => $activeEmployees->sortBy('name')->values(),
            'leaders' => $leaders,
            'axisProjects' => $axisProjects,
            'quickSwitchMembers' => $quickSwitchMembers->isNotEmpty() ? $quickSwitchMembers : $teamMembers->take(4),
            'currentUser' => $currentUser,
            'activeCodeNumber' => $activeProject ? preg_replace('/\D/', '', $activeProject->code) : '',
            'bootstrap' => [
                'initialPage' => $initialPage,
                'activeProjectId' => $activeProject?->id,
                'moduleProjectFilter' => $request->integer('project') ?: null,
                'currentUserId' => $currentUser?->id,
                'handlers' => $handlers,
                'routes' => collect(self::API_ROUTES)
                    ->mapWithKeys(fn (string $name): array => [$name => $this->routeTemplate('admin.api.'.$name)]),
                'pageUrls' => [
                    'dashboard' => route('admin.dashboard', absolute: false),
                    'projects' => route('admin.projects.index', absolute: false),
                    'modules' => route('admin.modules.index', absolute: false),
                    'task-detail' => $this->routeTemplate('admin.projects.tasks'),
                    'reports' => route('admin.reports.index', absolute: false),
                    'team' => route('admin.team.index', absolute: false),
                ],
                'projects' => ModuleResource::collection($projects)->resolve($request),
                'axisProjects' => ProjectResource::collection($axisProjects)->resolve($request),
                'ctoReports' => CtoReportResource::collection($ctoReports)->resolve($request),
                'teamMembers' => TeamMemberResource::collection($teamMembers)->resolve($request),
            ],
        ]);
    }

    /**
     * Relative URL for a named route, with any single route parameter replaced by `__ID__`.
     */
    private function routeTemplate(string $name): string
    {
        $route = app('router')->getRoutes()->getByName($name);
        $parameters = collect($route?->parameterNames() ?? [])->mapWithKeys(fn (string $p): array => [$p => '__ID__']);

        return route($name, $parameters->all(), absolute: false);
    }
}
