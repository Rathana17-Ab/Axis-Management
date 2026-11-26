<?php

namespace App\Http\Controllers;

use App\Enums\WorkspacePermission;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

/**
 * Top-level projects (e.g. "EOT Axis") that group modules.
 */
class ProjectController extends Controller
{
    /**
     * Create a project.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $this->authorizeWorkspacePermission(WorkspacePermission::RecordsManageAll);

        $project = Project::create($request->validated());

        return ProjectResource::make($project->loadCount('modules'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Delete an empty project. Projects that still hold modules are kept, because deleting
     * one would cascade to its modules and their tasks.
     */
    public function destroy(Project $project): JsonResponse
    {
        $this->authorizeWorkspaceRecord('update-record', $project);

        if ($project->modules()->exists()) {
            return response()->json([
                'message' => 'This project still has modules. Move or remove them before deleting it.',
            ], 422);
        }

        $project->delete();

        return response()->json(null, 204);
    }

    /**
     * Rename a project or change its dates.
     */
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorizeWorkspaceRecord('update-record', $project);

        $project->update($request->validated());

        return ProjectResource::make($project->loadCount('modules'));
    }
}
