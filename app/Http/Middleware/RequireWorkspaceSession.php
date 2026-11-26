<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires an actual signed-in session for the new administration endpoints.
 * The legacy workspace pages still open without login, as the prototype does.
 */
class RequireWorkspaceSession
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sign in to continue.'], 401);
            }

            abort(401, 'Sign in to continue.');
        }

        return $next($request);
    }
}
