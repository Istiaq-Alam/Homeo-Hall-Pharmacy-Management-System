<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role;

        abort_unless($role && in_array($role->value, $roles, true), 403, 'You do not have permission to access this area.');

        return $next($request);
    }
}
