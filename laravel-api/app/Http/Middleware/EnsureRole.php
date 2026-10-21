<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $role = $request->user()?->role;
        $allowed = in_array($role, $roles, true) || ($role === 'super_admin' && in_array('admin', $roles, true));

        if (! $request->user() || ! $allowed) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
