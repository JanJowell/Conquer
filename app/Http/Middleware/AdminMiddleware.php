<?php

namespace App\Http\Middleware;

use App\Models\BannedIP;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (BannedIP::isActiveFor($request->ip())) {
            abort(403, 'This IP address has been blocked.');
        }

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

        if ($this->requiresTwoFactorSetup($request, $user)) {
            return redirect()->route('profile.edit')
                ->withErrors([
                    'two_factor' => 'Two-factor authentication is required for admin access. Enable and confirm 2FA to continue.',
                ]);
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

    private function requiresTwoFactorSetup(Request $request, $user): bool
    {
        if (! $user->two_factor_required || $user->two_factor_confirmed_at) {
            return false;
        }

        return ! $request->routeIs(
            'profile.*',
            'logout',
            'two-factor.*',
            'password.confirm',
            'password.confirmation',
            'password.confirmation.store',
        );
    }
}
