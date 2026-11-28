<?php

namespace App\Http\Controllers;

use App\Enums\WorkspacePermission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

abstract class Controller
{
    /**
     * The team member operating the workspace: the switched-in session user,
     * or the first member with login access (the prototype "opens immediately").
     */
    protected function actingMember(): ?User
    {
        return static::currentWorkspaceUser();
    }

    /**
     * The member whose permissions apply, including legacy role-less sessions.
     */
    public static function currentWorkspaceUser(?User $fallbackUser = null): ?User
    {
        return $fallbackUser ?? User::currentWorkspaceUser();
    }

    /**
     * Require a workspace permission for role-assigned members.
     */
    protected function authorizeWorkspacePermission(WorkspacePermission $permission): void
    {
        $user = $this->actingMember();

        if ($user === null || ! $user->hasWorkspaceRole()) {
            return;
        }

        Gate::forUser($user)->authorize($permission->value);
    }

    /**
     * Whether the member may change the custom module order.
     *
     * Reordering is a view-level arrangement, so anyone who can see modules may
     * arrange the list they see. In the legacy open console the first login-enabled
     * member acts as the operator, so an unauthenticated request inherits that ability.
     */
    public static function canReorderModules(?User $user = null): bool
    {
        $user = $user ?? static::currentWorkspaceUser();

        if ($user === null) {
            return false;
        }

        if (! $user->hasWorkspaceRole()) {
            return true;
        }

        return collect([
            WorkspacePermission::RecordsManageAll,
            WorkspacePermission::RecordsViewAll,
            WorkspacePermission::RecordsViewShared,
        ])->contains(fn (WorkspacePermission $permission): bool => $user->hasWorkspacePermission($permission));
    }

    /**
     * Require a team-leader position for a privileged workspace action.
     */
    protected function authorizeTeamLeader(string $message = 'Only team leaders can perform this action.'): void
    {
        $user = $this->actingMember();

        if ($user === null || ! $user->isLeader()) {
            abort(403, $message);
        }
    }

    /**
     * Apply a workspace record gate only to members who have an assigned role.
     * Role-less members keep the original open-console behavior.
     */
    protected function authorizeWorkspaceRecord(string $ability, object $record): void
    {
        $user = $this->actingMember();

        if ($user === null || ! $user->hasWorkspaceRole()) {
            return;
        }

        Gate::forUser($user)->authorize($ability, $record);
    }
}
