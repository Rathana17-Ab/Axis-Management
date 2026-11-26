<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserRolesRequest;
use App\Http\Resources\TeamMemberResource;
use App\Models\Role;
use App\Models\User;

class UserRoleController extends Controller
{
    /**
     * Replace a user's workspace roles with the selected seeded roles.
     */
    public function update(UpdateUserRolesRequest $request, User $user): TeamMemberResource
    {
        $roleIds = Role::query()
            ->whereIn('slug', $request->validated('roles'))
            ->pluck('id')
            ->all();

        $user->roles()->sync($roleIds);

        return TeamMemberResource::make($user->load('roles', 'teams'));
    }
}
