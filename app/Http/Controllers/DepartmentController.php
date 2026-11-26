<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DepartmentController extends Controller
{
    /**
     * List departments with the number of members linked to each one.
     */
    public function index(): JsonResponse
    {
        return DepartmentResource::collection(
            Department::query()->withCount('users')->orderBy('name')->get()
        )->response()->setStatusCode(200);
    }

    /**
     * Create a department.
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());

        return DepartmentResource::make($department->loadCount('users'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a department and keep linked members' labels in sync.
     */
    public function update(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        $department->update($request->validated());

        if ($request->has('name')) {
            User::query()->where('department_id', $department->id)->update(['department' => $department->name]);
        }

        return DepartmentResource::make($department->loadCount('users'));
    }

    /**
     * Delete a department that has no members linked to it.
     */
    public function destroy(Department $department): Response|JsonResponse
    {
        if ($department->users()->exists() || User::query()->where('department', $department->name)->exists()) {
            return response()->json([
                'message' => 'Move or unlink this department\'s team members before deleting it.',
            ], 409);
        }

        $department->delete();

        return response()->noContent();
    }
}
