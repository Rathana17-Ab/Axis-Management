<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeStatus;
use App\Enums\FeatureUpgradeState;
use App\Enums\WorkspacePermission;
use App\Http\Resources\CtoReportResource;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\FeatureUpgradeResource;
use App\Http\Resources\ModuleResource;
use App\Http\Resources\PositionResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\RecordShareResource;
use App\Http\Resources\RoleResource;
use App\Http\Resources\TeamMemberResource;
use App\Http\Resources\TeamResource;
use App\Http\Resources\WorkQueueResource;
use App\Models\CtoReport;
use App\Models\Department;
use App\Models\FeatureUpgrade;
use App\Models\Module;
use App\Models\Position;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\RecordShare;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        'admin.profile' => 'profile',
        'admin.projects.index' => 'projects',
        'admin.modules.index' => 'modules',
        'admin.projects.tasks' => 'task-detail',
        'admin.reports.index' => 'reports',
        'admin.team.index' => 'team',
        'admin.roles.index' => 'roles',
        'admin.work-queues.index' => 'work-queues',
        'admin.sharing.index' => 'sharing',
        'admin.p18.index' => 'p18',
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
        $this->authorizeWorkspaceRecord('view-record', $module);

        return $this->workspace('task-detail', $module->id);
    }

    /**
     * Named API routes exposed to `window.AxisAPI` (the `__ID__` placeholder is filled client-side).
     *
     * @var list<string>
     */
    private const API_ROUTES = [
        'projects.store', 'projects.update', 'projects.destroy',
        'modules.store', 'modules.update', 'modules.reorder',
        'tasks.store', 'tasks.update', 'tasks.timer.toggle', 'tasks.timer.reset', 'tasks.destroy',
        'issues.store', 'issues.update', 'issues.cycle', 'issues.destroy',
        'reports.store', 'reports.show', 'reports.update', 'reports.destroy',
        'team.store', 'team.update', 'team.toggle-login', 'team.password', 'team.destroy', 'user-roles.update',
        'roles.index',
        'departments.index', 'departments.store', 'departments.update', 'departments.destroy',
        'positions.index', 'positions.store', 'positions.update', 'positions.destroy',
        'teams.index', 'teams.store', 'teams.update', 'teams.members', 'teams.destroy',
        'work-queues.index', 'work-queues.store', 'work-queues.update', 'work-queues.members', 'work-queues.destroy',
        'record-shares.index', 'record-shares.store', 'record-shares.destroy',
        'feature-upgrades.index', 'feature-upgrades.update',
    ];

    /**
     * Build the hydrated workspace view.
     */
    private function workspace(string $initialPage, ?int $activeProjectId = null): View
    {
        $request = request();
        $currentUser = $this->actingMember();
        $currentUser?->loadMissing('roles.permissions');
        $isSignedIn = Auth::check();
        $hasWorkspaceRole = $currentUser?->hasWorkspaceRole() ?? false;
        $restrictRecords = $hasWorkspaceRole
            && ! $currentUser->hasWorkspacePermission(WorkspacePermission::RecordsViewAll);
        $hasLegacyAccess = ! $isSignedIn || ! $hasWorkspaceRole;
        $canAccessAdministration = $isSignedIn && ($hasLegacyAccess
            || $currentUser->hasWorkspacePermission(WorkspacePermission::AdministrationAccess));
        $canViewWorkQueues = $isSignedIn && ($hasLegacyAccess
            || $currentUser->hasWorkspacePermission(WorkspacePermission::QueuesViewAll)
            || $currentUser->hasWorkspacePermission(WorkspacePermission::QueuesViewShared));
        $canViewSharing = $isSignedIn && ($hasLegacyAccess
            || $currentUser->hasWorkspacePermission(WorkspacePermission::RecordsViewAll)
            || $currentUser->hasWorkspacePermission(WorkspacePermission::RecordsViewShared));
        $canViewP18 = $isSignedIn && ($hasLegacyAccess
            || $currentUser->hasWorkspacePermission(WorkspacePermission::P18View));
        $canManageWorkQueues = $isSignedIn && ($hasLegacyAccess
            || $currentUser->hasWorkspacePermission(WorkspacePermission::WorkQueuesManage));
        $canManageP18 = $isSignedIn && ($hasLegacyAccess
            || $currentUser->hasWorkspacePermission(WorkspacePermission::P18Manage));
        $canManageRecords = $isSignedIn && ($hasLegacyAccess
            || $currentUser->hasWorkspacePermission(WorkspacePermission::RecordsManageAll));

        // `$projects` / bootstrap `projects` hold the *modules* (Axis Core System, Axis Sales, …) —
        // the name predates the Project → Module split and is used throughout the views and JS.
        // Top-level projects (e.g. "EOT Axis") are exposed separately as `axisProjects`.
        // Custom order starts alphabetically (see the sort-order backfill migration) and is updated by drag-and-drop.
        $sharedIdsByType = $restrictRecords ? RecordShare::idsByTypeFor($currentUser) : [];
        $sharedProjectIds = $sharedIdsByType[(new Project)->getMorphClass()] ?? [];
        $sharedTaskIds = $sharedIdsByType[(new ProjectTask)->getMorphClass()] ?? [];
        $sharedReportIds = $sharedIdsByType[(new CtoReport)->getMorphClass()] ?? [];

        $moduleQuery = Module::query()
            ->visibleTo($currentUser, $restrictRecords ? $sharedIdsByType : null)
            ->with(['project', 'tasks.issueNotes', 'tasks.employee', 'tasks.assignedBy']);
        if ($restrictRecords) {
            $moduleQuery
                ->with(['tasks' => function ($query) use ($sharedTaskIds, $currentUser): void {
                    $query->where(function ($taskQuery) use ($sharedTaskIds, $currentUser): void {
                        $taskQuery->whereIn('project_tasks.id', $sharedTaskIds)
                            ->orWhere('project_tasks.emp_id', $currentUser->id);
                    });
                }]);
        }
        $projects = $moduleQuery->orderBy('sort_order')->orderBy('name')->get();

        $projectQuery = Project::query()->withCount('modules');
        if ($restrictRecords) {
            $projectQuery->where(function ($query) use ($sharedProjectIds, $projects): void {
                $query->whereIn('id', $sharedProjectIds)
                    ->orWhereHas('modules', fn ($moduleQuery) => $moduleQuery->whereIn('modules.id', $projects->pluck('id')->all()));
            });
        }
        $axisProjects = $projectQuery->orderBy('name')->get();

        $reportQuery = CtoReport::query();
        if ($restrictRecords) {
            $reportQuery->whereIn('id', $sharedReportIds);
        }
        $ctoReports = $reportQuery->latest('report_date')->orderBy('id')->get();

        $memberQuery = User::query();
        $memberQuery->with(['departmentRecord', 'positionRecord']);

        if ($canAccessAdministration) {
            $memberQuery->with(['roles', 'teams']);
        }
        $teamMembers = $memberQuery->orderBy('id')->get();
        $activeEmployees = $teamMembers->filter(fn (User $member): bool => $member->status === EmployeeStatus::Active);
        $leaders = $activeEmployees->filter(fn (User $member): bool => $member->isLeader())->values();
        $developers = $activeEmployees->filter(fn (User $member): bool => $member->isDeveloper())->sortBy('name')->values();

        $roles = $canAccessAdministration
            ? Role::query()->with('permissions')->orderBy('id')->get()
            : collect();
        $teams = $canAccessAdministration
            ? Team::query()->with('users')->orderBy('name')->get()
            : collect();

        $workQueueQuery = WorkQueue::query()->with(['team', 'owner', 'users']);
        if ($hasWorkspaceRole) {
            $workQueueQuery->visibleTo($currentUser);
        }
        $workQueues = $workQueueQuery->orderBy('name')->get();

        $shareQuery = RecordShare::query()->with(['user', 'shareable']);
        if ($restrictRecords) {
            $shareQuery->where('user_id', $currentUser->id);
        }
        $recordShares = $shareQuery->orderBy('id')->get();
        $featureUpgrades = FeatureUpgrade::query()->orderBy('id')->get();
        $departments = Department::query()->withCount('users')->orderBy('name')->get();
        $positions = Position::query()->withCount('users')->orderBy('id')->get();

        $activeProject = $projects->firstWhere('id', $activeProjectId) ?? $projects->sortBy('id')->first();

        $handlers = collect(config('axis.handlers'))
            ->merge($teamMembers->pluck('name'))
            ->unique()
            ->values();

        return view('axis.workspace', [
            'initialPage' => $initialPage,
            'projects' => $projects,
            'handlers' => $handlers,
            'teamMembers' => $teamMembers,
            'employees' => $activeEmployees->sortBy('name')->values(),
            'leaders' => $leaders,
            'developers' => $developers,
            'axisProjects' => $axisProjects,
            'currentUser' => $currentUser,
            'canAccessAdministration' => $canAccessAdministration,
            'canViewWorkQueues' => $canViewWorkQueues,
            'canViewSharing' => $canViewSharing,
            'canViewP18' => $canViewP18,
            'canManageWorkQueues' => $canManageWorkQueues,
            'canManageP18' => $canManageP18,
            'canManageRecords' => $canManageRecords,
            'activeCodeNumber' => $activeProject ? preg_replace('/\D/', '', $activeProject->code) : '',
            'bootstrap' => [
                'initialPage' => $initialPage,
                'activeProjectId' => $activeProject?->id,
                'moduleProjectFilter' => $request->integer('project') ?: null,
                'currentUserId' => $currentUser?->id,
                'isSignedIn' => $isSignedIn,
                'roleKeys' => $isSignedIn ? ($currentUser?->roles->pluck('slug')->all() ?? []) : [],
                'permissionLabels' => collect(WorkspacePermission::cases())
                    ->mapWithKeys(fn (WorkspacePermission $permission): array => [$permission->value => $permission->label()])
                    ->all(),
                'upgradeStateLabels' => collect(FeatureUpgradeState::cases())
                    ->mapWithKeys(fn (FeatureUpgradeState $state): array => [$state->value => $state->label()])
                    ->all(),
                'permissions' => $isSignedIn ? collect(WorkspacePermission::cases())
                    ->filter(fn (WorkspacePermission $permission): bool => $currentUser?->hasWorkspacePermission($permission) ?? false)
                    ->map(fn (WorkspacePermission $permission): string => $permission->value)
                    ->values()
                    ->all() : [],
                'canAccessAdministration' => $canAccessAdministration,
                'canViewWorkQueues' => $canViewWorkQueues,
                'canViewSharing' => $canViewSharing,
                'canViewP18' => $canViewP18,
                'canManageWorkQueues' => $canManageWorkQueues,
                'canManageP18' => $canManageP18,
                'canManageRecords' => $canManageRecords,
                'canReorderModules' => static::canReorderModules($currentUser),
                'handlers' => $handlers,
                'routes' => collect(self::API_ROUTES)
                    ->mapWithKeys(fn (string $name): array => [$name => $this->routeTemplate('admin.api.'.$name)]),
                'pageUrls' => [
                    'dashboard' => route('admin.dashboard', absolute: false),
                    'profile' => route('admin.profile', absolute: false),
                    'projects' => route('admin.projects.index', absolute: false),
                    'modules' => route('admin.modules.index', absolute: false),
                    'task-detail' => $this->routeTemplate('admin.projects.tasks'),
                    'reports' => route('admin.reports.index', absolute: false),
                    'team' => route('admin.team.index', absolute: false),
                    'roles' => route('admin.roles.index', absolute: false),
                    'work-queues' => route('admin.work-queues.index', absolute: false),
                    'sharing' => route('admin.sharing.index', absolute: false),
                    'p18' => route('admin.p18.index', absolute: false),
                ],
                'projects' => ModuleResource::collection($projects)->resolve($request),
                'axisProjects' => ProjectResource::collection($axisProjects)->resolve($request),
                'ctoReports' => CtoReportResource::collection($ctoReports)->resolve($request),
                'teamMembers' => TeamMemberResource::collection($teamMembers)->resolve($request),
                'departments' => DepartmentResource::collection($departments)->resolve($request),
                'positions' => PositionResource::collection($positions)->resolve($request),
                'roles' => RoleResource::collection($roles)->resolve($request),
                'teams' => TeamResource::collection($teams)->resolve($request),
                'workQueues' => WorkQueueResource::collection($workQueues)->resolve($request),
                'recordShares' => RecordShareResource::collection($recordShares)->resolve($request),
                'featureUpgrades' => FeatureUpgradeResource::collection($featureUpgrades)->resolve($request),
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
