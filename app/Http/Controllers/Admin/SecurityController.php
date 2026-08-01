<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\BannedIP;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class SecurityController extends Controller
{
    public function dashboard()
    {
        $suspiciousActivities = AdminActivityLog::where('created_at', '>=', now()->subHours(24))
            ->selectRaw('user_id, COUNT(*) as count, GROUP_CONCAT(DISTINCT ip_address) as ips')
            ->groupBy('user_id')
            ->having('count', '>', 50)
            ->with('user')
            ->get();

        $failedLogins = Cache::get('failed_logins', []);
        $bannedIPs = BannedIP::all();
        $recentActivities = AdminActivityLog::with('user')
            ->latest()
            ->take(50)
            ->get();

        return view('admin.security.dashboard', compact(
            'suspiciousActivities', 
            'failedLogins', 
            'bannedIPs', 
            'recentActivities'
        ));
    }

    public function activityLogs(Request $request)
    {
        $query = AdminActivityLog::with('user');

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->action) {
            $query->where('action', 'like', '%' . $request->action . '%');
        }

        if ($request->ip_address) {
            $query->where('ip_address', $request->ip_address);
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->latest()->paginate(50)->withQueryString();

        return view('admin.security.activity-logs', compact('logs'));
    }

    public function bannedIPs()
    {
        $bannedIPs = BannedIP::latest()->paginate(20)->withQueryString();

        return view('admin.security.banned-ips', compact('bannedIPs'));
    }

    public function banIP(Request $request)
    {
        $validated = $request->validate([
            'ip_address' => 'required|ip',
            'reason' => 'required|string|max:255',
            'permanent' => 'boolean',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $permanent = $request->boolean('permanent');

        if (! $permanent && empty($validated['expires_at'])) {
            throw ValidationException::withMessages([
                'expires_at' => 'An expiration date is required for temporary bans.',
            ]);
        }

        BannedIP::updateOrCreate(
            ['ip_address' => $validated['ip_address']],
            [
                'reason' => $validated['reason'],
                'permanent' => $permanent,
                'expires_at' => $permanent ? null : $validated['expires_at'],
            ]
        );

        return redirect()->route('admin.security.banned-ips')
            ->with('success', 'IP address banned successfully.');
    }

    public function unbanIP(BannedIP $bannedIP)
    {
        $bannedIP->delete();

        return redirect()->route('admin.security.banned-ips')
            ->with('success', 'IP address unbanned successfully.');
    }

    public function loginMonitoring()
    {
        $loginAttempts = Cache::get('login_attempts', []);
        $suspiciousLogins = $this->getSuspiciousLogins();
        $recentLogins = $this->getRecentLogins();

        return view('admin.security.login-monitoring', compact(
            'loginAttempts',
            'suspiciousLogins',
            'recentLogins'
        ));
    }

    public function enforce2FA()
    {
        $users = User::whereIn('role', User::storedAdminRoles())->get();

        foreach ($users as $user) {
            $user->update(['two_factor_required' => true]);
        }

        return redirect()->route('admin.security.dashboard')
            ->with('success', '2FA enforcement enabled for all admin users.');
    }

    public function dataAccessLogs()
    {
        $dataAccessLogs = AdminActivityLog::where('action', 'like', '%data%')
            ->orWhere('action', 'like', '%export%')
            ->orWhere('action', 'like', '%download%')
            ->with('user')
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.security.data-access-logs', compact('dataAccessLogs'));
    }

    private function getSuspiciousLogins()
    {
        return AdminActivityLog::where('created_at', '>=', now()->subHours(24))
            ->selectRaw('ip_address, COUNT(*) as count, GROUP_CONCAT(DISTINCT user_id) as users')
            ->groupBy('ip_address')
            ->having('count', '>', 10)
            ->get();
    }

    private function getRecentLogins()
    {
        return \App\Models\User::whereNotNull('last_login_at')
            ->latest('last_login_at')
            ->take(20)
            ->get(['id', 'name', 'email', 'last_login_at', 'last_login_ip']);
    }
}
