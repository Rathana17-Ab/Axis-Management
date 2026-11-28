<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts team-directory changes to signed-in members whose position is Leader.
 */
class RequireTeamLeader
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isLeader()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Only team leaders can manage the team directory.'], 403);
            }

            abort(403, 'Only team leaders can manage the team directory.');
        }

        return $next($request);
    }
}
