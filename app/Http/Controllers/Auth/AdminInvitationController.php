<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AdminInvitationController extends Controller
{
    public function show(User $user, string $token)
    {
        if (! $user->isAdmin()) {
            abort(404);
        }

        if ($user->email_verified_at !== null) {
            return redirect()->route('login')->with('status', 'This administrator account is already active.');
        }

        if (! $this->tokenMatches($user, $token)) {
            abort(404);
        }

        if ($user->admin_invitation_expires_at?->isPast()) {
            return response()->view('pages.auth.admin-invitation', [
                'user' => $user,
                'token' => $token,
                'expired' => true,
            ], 410);
        }

        return view('pages.auth.admin-invitation', [
            'user' => $user,
            'token' => $token,
            'expired' => false,
        ]);
    }

    public function accept(Request $request, User $user, string $token)
    {
        $validated = $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->letters()->numbers()->symbols(),
            ],
        ]);

        $activated = DB::transaction(function () use ($user, $token, $validated): bool {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $lockedUser->isAdmin()
                || $lockedUser->email_verified_at !== null
                || ! $this->tokenMatches($lockedUser, $token)
                || ! $lockedUser->admin_invitation_expires_at
                || $lockedUser->admin_invitation_expires_at->isPast()) {
                return false;
            }

            $lockedUser->forceFill([
                'password' => $validated['password'],
                'email_verified_at' => now(),
                'admin_invitation_token' => null,
                'admin_invitation_sent_at' => null,
                'admin_invitation_expires_at' => null,
                'remember_token' => null,
            ])->save();

            event(new Verified($lockedUser));

            return true;
        });

        if (! $activated) {
            return back()->withErrors([
                'invitation' => 'This invitation is invalid, expired, or has already been used. Ask a Super Admin to send a new invitation.',
            ]);
        }

        AdminActivityLog::create([
            'user_id' => $user->getKey(),
            'action' => 'Accepted administrator invitation',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('login')->with('status', 'Administrator account activated. You can now sign in.');
    }

    private function tokenMatches(User $user, string $token): bool
    {
        return filled($user->admin_invitation_token)
            && hash_equals($user->admin_invitation_token, hash('sha256', $token));
    }
}
