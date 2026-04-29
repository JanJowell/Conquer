<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user = User::where('api_token', hash('sha256', $token))
            ->where(function ($query) {
                $query->whereNull('api_token_expires_at')
                    ->orWhere('api_token_expires_at', '>', now());
            })
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
