<?php

namespace App\Http\Controllers;

use App\Enums\WorkspacePermission;
use App\Enums\WorkStatus;
use App\Http\Requests\ReorderModulesRequest;
use App\Http\Requests\StoreModuleRequest;
use App\Http\Requests\UpdateModuleRequest;
use App\Http\Resources\ModuleResource;
use App\Models\Module;
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
     * Create a module under a project.
     */
    public function store(StoreModuleRequest $request): JsonResponse
    {
        $this->authorizeWorkspacePermission(WorkspacePermission::RecordsManageAll);

        $validated = $request->validated();
        $deadline = Carbon::parse($validated['deadline']);
        $actingName = $this->actingMember()?->name;

        $module = DB::transaction(function () use ($validated, $deadline, $actingName): Module {
            $this->makeRoomAtTopOfModuleOrder();

            $module = Module::create([
                'project_id' => $validated['project_id'],
                'sort_order' => 1,
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

            return $module;
        });

        return ModuleResource::make($module->load(self::RELATIONS))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Shift the existing modules down so a newly created module can take the first position.
     */
    private function makeRoomAtTopOfModuleOrder(): void
    {
        Module::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->each(function (mixed $id, int $index): void {
                Module::whereKey($id)->update(['sort_order' => $index + 2]);
            });
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

    /**
     * Delete an empty module. Tasks must be removed first so their notes and logged time are not lost.
     */
    public function destroy(Module $module): JsonResponse
    {
        $this->authorizeTeamLeader('Only team leaders can delete modules.');
        $this->authorizeWorkspaceRecord('update-record', $module);

        if ($module->tasks()->exists()) {
            return response()->json([
                'message' => 'This module still has tasks. Remove them before deleting it.',
            ], 422);
        }

        $module->delete();

        return response()->json(null, 204);
    }

    /**
     * Persist the drag-and-drop order of the Modules page.
     */
    public function reorder(ReorderModulesRequest $request): JsonResponse
    {
        $visibleIds = collect($request->validated()['ordered_ids'])
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
        $fullOrder = DB::transaction(function () use ($visibleIds): array {
            $currentOrder = Module::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
            $fullOrder = $this->mergeVisibleModuleOrder($currentOrder, $visibleIds);

            collect($fullOrder)->each(fn (int $id, int $index) => Module::whereKey($id)->update(['sort_order' => $index + 1]));

            return $fullOrder;
        });

        $sortOrders = array_flip($fullOrder);

        return response()->json([
            'data' => collect($visibleIds)->map(fn (int $id): array => [
                'id' => $id,
                'sortOrder' => $sortOrders[$id] + 1,
            ])->all(),
        ]);
    }

    /**
     * Place the reordered visible modules back into the full list while leaving
     * modules the member cannot see in their existing positions.
     *
     * @param  list<int>  $currentOrder
     * @param  list<int>  $visibleOrder
     * @return list<int>
     */
    private function mergeVisibleModuleOrder(array $currentOrder, array $visibleOrder): array
    {
        if (count($currentOrder) === count($visibleOrder)) {
            return array_values($visibleOrder);
        }

        $visibleSet = array_flip($visibleOrder);
        $visibleIndexes = [];

        foreach ($currentOrder as $index => $id) {
            if (isset($visibleSet[$id])) {
                $visibleIndexes[] = $index;
            }
        }

        $merged = $currentOrder;

        foreach ($visibleIndexes as $offset => $index) {
            $merged[$index] = $visibleOrder[$offset];
        }

        return $merged;
    }
}
