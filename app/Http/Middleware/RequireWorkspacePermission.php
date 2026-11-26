<?php

namespace App\Http\Middleware;

use App\Enums\WorkspacePermission;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Denies restricted workspace routes unless the operating team member holds the
 * database-backed permission. Users without a workspace role keep the legacy
 * open-console behavior so existing flows are not broken by the new ACL.
 */
class RequireWorkspacePermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $workspacePermissions = collect($permissions)
            ->map(fn (string $permission): ?WorkspacePermission => WorkspacePermission::tryFrom($permission));

        $validPermissions = $workspacePermissions->filter()->values();

        if ($validPermissions->count() !== $workspacePermissions->count()) {
            abort(403, 'This workspace permission is not recognised.');
        }

        $user = User::currentWorkspaceUser();

        if ($user === null || ! $user->hasWorkspaceRole()) {
            return $next($request);
        }

        if (! $validPermissions->contains(fn (WorkspacePermission $permission): bool => $user->hasWorkspacePermission($permission))) {
            abort(403, 'You do not have permission to open this section.');
        }

        return $next($request);
    }
}
