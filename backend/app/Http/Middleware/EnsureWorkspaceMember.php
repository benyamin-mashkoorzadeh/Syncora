<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceMember
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->route('workspace');

        if (! $workspace instanceof Workspace || ! $request->user()?->workspaceMemberships()
            ->whereBelongsTo($workspace)
            ->exists()) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
