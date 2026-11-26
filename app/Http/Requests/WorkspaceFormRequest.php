<?php

namespace App\Http\Requests;

use App\Enums\WorkspacePermission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared authorization boundary for workspace administration endpoints.
 * Team members without a workspace role retain the original open-console access.
 */
abstract class WorkspaceFormRequest extends FormRequest
{
    /**
     * Whether the operating member may perform an action that needs the given permission.
     */
    protected function hasWorkspaceAccess(WorkspacePermission $permission): bool
    {
        $user = User::currentWorkspaceUser();

        return $user === null
            || ! $user->hasWorkspaceRole()
            || $user->hasWorkspacePermission($permission);
    }
}
