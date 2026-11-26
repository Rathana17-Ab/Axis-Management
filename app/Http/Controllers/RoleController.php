<?php

namespace App\Http\Controllers;

use App\Enums\WorkspacePermission;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    /**
     * Return the role matrix with the permissions granted to each role.
     */
    public function index(): JsonResponse
    {
        $permissions = collect(WorkspacePermission::cases())->map(fn (WorkspacePermission $permission): array => [
            'key' => $permission->value,
            'label' => $permission->label(),
        ])->values();

        return response()->json([
            'data' => RoleResource::collection(Role::query()->with('permissions')->orderBy('id')->get())->resolve(),
            'permissions' => $permissions,
        ]);
    }
}
