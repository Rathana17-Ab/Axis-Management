<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\WorkspacePermission;
use App\Enums\WorkStatus;
use App\Http\Requests\StoreModuleRequest;
use App\Http\Requests\UpdateModuleRequest;
use App\Http\Resources\ModuleResource;
use App\Models\Module;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModuleController extends Controller
{
    /**
     * Relations returned with a module (matches the workspace hydration).
     *
     * @var list<string>
     */
    private const RELATIONS = ['project', 'tasks.issueNotes', 'tasks.employee', 'tasks.assignedBy'];

    /**
     * Create a module under a project, with its initial architecture task.
     */
    public function store(StoreModuleRequest $request): JsonResponse
    {
        $this->authorizeWorkspacePermission(WorkspacePermission::RecordsManageAll);

        $validated = $request->validated();
        $deadline = Carbon::parse($validated['deadline']);
        $actingMember = $this->actingMember();
        $actingName = $actingMember?->name;
        $lead = User::query()->firstWhere('name', $validated['manager_name']);

        $module = DB::transaction(function () use ($validated, $deadline, $actingName, $actingMember, $lead): Module {
            $module = Module::create([
                'project_id' => $validated['project_id'],
                'sort_order' => ((int) Module::max('sort_order')) + 1,
                'code' => Module::nextCode(),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'proj_badge' => Str::upper(Str::substr($validated['name'], 0, 1)),
                'badge_class' => 'bg-sky-500/20 text-sky-300 border border-sky-400/30',
                'customer_name' => $validated['customer_name'],
                'customer_sub' => '(Axis Client)',
                'cust_badge' => Str::upper(Str::substr($validated['customer_name'], 0, 1)),
                'product_domain' => 'Axis Core Suite',
                'start_date' => $validated['start_date'] ?? today(),
                'deadline' => $deadline,
                'manager_name' => $validated['manager_name'],
                'team_members' => collect([$validated['manager_name'], $actingName])->filter()->unique()->values()->all(),
                'status' => WorkStatus::Processing,
                'overdue_label' => Module::overdueLabel($deadline),
            ]);

            $module->tasks()->create([
                'name' => 'Module Architecture & Setup',
                'description' => "*Initialize module routes & controllers\n*Configure RBAC permissions",
                'priority' => TaskPriority::High,
                'start_date' => today(),
                'deadline' => $deadline,
                'emp_id' => $lead?->id,
                'assigned_by_id' => $actingMember?->isLeader() ? $actingMember->id : null,
                'status' => WorkStatus::Processing,
                'progress' => 25,
                'est_hours' => 16,
                'act_seconds' => 3600,
                'sort_order' => 1,
            ]);

            return $module;
        });

        return ModuleResource::make($module->load(self::RELATIONS))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a module's details: project, name, customer, lead, status, dates, description or team.
     */
    public function update(UpdateModuleRequest $request, Module $module): ModuleResource
    {
        $this->authorizeWorkspaceRecord('update-record', $module);

        $module->fill($request->validated());

        // The list avatars are the first letter of the name / customer
        if ($module->isDirty('name')) {
            $module->proj_badge = Str::upper(Str::substr($module->name, 0, 1));
        }

        if ($module->isDirty('customer_name')) {
            $module->cust_badge = Str::upper(Str::substr($module->customer_name, 0, 1));
        }

        if ($module->isDirty('deadline')) {
            $module->overdue_label = $module->deadline ? Module::overdueLabel($module->deadline) : null;
        }

        $module->save();

        return ModuleResource::make($module->load(self::RELATIONS));
    }
}
