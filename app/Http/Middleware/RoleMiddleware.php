<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        if (! auth()->user()->hasAdminRole($roles)) {
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}
