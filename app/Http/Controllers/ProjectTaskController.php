<?php

namespace App\Http\Controllers;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\TaskPriority;
use App\Enums\WorkspacePermission;
use App\Enums\WorkStatus;
use App\Http\Requests\StoreProjectTaskRequest;
use App\Http\Requests\UpdateProjectTaskRequest;
use App\Http\Resources\ProjectTaskResource;
use App\Models\Module;
use App\Models\ProjectTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ProjectTaskController extends Controller
{
    /**
     * Relations returned with a task.
     *
     * @var list<string>
     */
    private const RELATIONS = ['issueNotes', 'employee', 'assignedBy'];

    /**
     * Create a feature task (with an optional initial developer issue note).
     */
    public function store(StoreProjectTaskRequest $request, Module $module): JsonResponse
    {
        $this->authorizeTeamLeader('Only team leaders can manage tasks.');
        $this->authorizeWorkspacePermission(WorkspacePermission::RecordsManageAll);

        $validated = $request->validated();

        $task = DB::transaction(function () use ($validated, $module): ProjectTask {
            $progress = (float) ($validated['progress'] ?? 0);

            $task = $module->tasks()->make([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'priority' => $validated['priority'] ?? TaskPriority::High,
                'start_date' => $validated['start_date'] ?? null,
                'deadline' => $validated['deadline'] ?? null,
                'emp_id' => $validated['emp_id'],
                'assigned_by_id' => $validated['assigned_by_id'] ?? null,
                'est_hours' => $validated['est_hours'] ?? 8,
                'sort_order' => (int) $module->tasks()->max('sort_order') + 1,
            ]);
            // Status follows the progress, unless one was chosen explicitly (then progress follows the status)
            $task->applyProgress($progress);
            if (isset($validated['status'])) {
                $task->applyStatus(WorkStatus::from($validated['status']));
            }
            $task->save();

            $initialIssue = $validated['initial_issue'] ?? null;

            if (filled($initialIssue['encountered'] ?? null)) {
                $resolution = $initialIssue['resolution'] ?? null;

                $task->issueNotes()->create([
                    'category' => 'Bug / Blocker',
                    'severity' => IssueSeverity::High,
                    'status' => filled($resolution) ? IssueStatus::Resolved : IssueStatus::Open,
                    'encountered' => $initialIssue['encountered'],
                    'resolution' => filled($resolution) ? $resolution : null,
                    'author_name' => $task->employee?->name ?? 'Unknown',
                ]);
            }

            return $task;
        });

        return ProjectTaskResource::make($task->load(self::RELATIONS))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Inline edit of a task field (status, progress %, est hours, handler, dates, …).
     */
    public function update(UpdateProjectTaskRequest $request, ProjectTask $task): ProjectTaskResource
    {
        $this->authorizeWorkspaceRecord('update-record', $task);

        $validated = $request->validated();

        $task->fill(collect($validated)->except(['status', 'progress'])->all());

        if (array_key_exists('progress', $validated)) {
            $task->applyProgress((float) $validated['progress']);
        }

        if (array_key_exists('status', $validated)) {
            $task->applyStatus(WorkStatus::from($validated['status']));
        }

        $task->save();

        return ProjectTaskResource::make($task->load(self::RELATIONS));
    }

    /**
     * Start or pause the server-accurate live stopwatch.
     */
    public function toggleTimer(ProjectTask $task): ProjectTaskResource
    {
        $this->authorizeWorkspaceRecord('update-record', $task);

        $task = DB::transaction(function () use ($task): ProjectTask {
            $locked = ProjectTask::query()->lockForUpdate()->findOrFail($task->id);

            $locked->is_running ? $locked->pauseTimer() : $locked->startTimer();
            $locked->save();

            return $locked;
        });

        return ProjectTaskResource::make($task->load(self::RELATIONS));
    }

    /**
     * Stop the stopwatch and clear the logged time.
     */
    public function resetTimer(ProjectTask $task): ProjectTaskResource
    {
        $this->authorizeTeamLeader('Only team leaders can manage tasks.');
        $this->authorizeWorkspaceRecord('update-record', $task);

        $task->resetTimer();
        $task->save();

        return ProjectTaskResource::make($task->load(self::RELATIONS));
    }

    /**
     * Delete a task and its issue notes.
     */
    public function destroy(ProjectTask $task): Response
    {
        $this->authorizeTeamLeader('Only team leaders can manage tasks.');
        $this->authorizeWorkspaceRecord('update-record', $task);

        $task->delete();

        return response()->noContent();
    }
}
