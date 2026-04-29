<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        $user = auth()->user();

        if ($user->isBanned()) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors([
                'email' => 'This account has been banned. Please contact the super administrator for assistance.',
            ]);
        }
        
        if (! $user->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        // Log admin activity
        $this->logAdminActivity($user, $request);

        return $next($request);
    }

    private function logAdminActivity($user, $request)
    {
        \App\Models\AdminActivityLog::create([
            'user_id' => $user->id,
            'action' => $request->method() . ' ' . $request->path(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
