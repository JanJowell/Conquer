<?php

namespace App\Http\Middleware;

use App\Models\BannedIP;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (BannedIP::isActiveFor($request->ip())) {
            return response()->json([
                'message' => 'This IP address has been blocked.',
            ], 403);
        }

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

        if ($user->isBanned()) {
            return response()->json([
                'message' => 'This account has been banned.',
            ], 403);
        }

        if ($user->isSuspended()) {
            return response()->json([
                'message' => 'This account is currently suspended.',
            ], 423);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
