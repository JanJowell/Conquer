<?php

namespace App\Http\Controllers;

use App\Models\AdminActivityLog;
use App\Models\User;
use App\Services\AdminInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profiles.edit', [
            'user' => Auth::user(),
        ]);
    }

    public function update(Request $request, AdminInvitationService $invitations): RedirectResponse
    {
        $user = $request->user();
        $originalEmail = $user->email;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'phone' => ['nullable', 'digits:11'],
            'gender' => ['nullable', 'string', 'max:50'],
            'birthdate' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_number' => ['nullable', 'digits:11'],
            'medical_conditions' => ['nullable', 'string'],
        ]);

        if ($user->normalizedRole() !== User::ROLE_RUNNER) {
            $validated['medical_conditions'] = null;
        }

        $user->update($validated);

        if ($user->isAdmin() && $originalEmail !== $user->email) {
            $user->forceFill([
                'email_verified_at' => null,
                'api_token' => null,
                'api_token_expires_at' => null,
                'admin_invitation_token' => null,
                'admin_invitation_sent_at' => null,
                'admin_invitation_expires_at' => null,
            ])->save();

            try {
                $invitations->send($user, $user);
                $status = 'Your email was changed. A 24-hour verification invitation was sent to the new address.';
            } catch (Throwable $exception) {
                report($exception);
                $status = 'Your email was changed and now requires verification, but the invitation could not be delivered. Ask another Super Admin to resend it.';
            }

            AdminActivityLog::create([
                'user_id' => $user->getKey(),
                'action' => 'Changed administrator email; re-verification required',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', $status);
        }

        return redirect()->route('profile.edit')->with('status', 'profile-updated');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('profile.edit')->with('status', 'password-updated');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'avatar.required' => 'Please choose a profile photo to upload.',
            'avatar.image' => 'The avatar must be a valid image file.',
            'avatar.mimes' => 'The avatar must be a JPG, PNG, or WebP image.',
            'avatar.max' => 'The avatar must not be larger than 10 MB.',
            'avatar.uploaded' => 'The avatar could not be uploaded. Make sure it is a JPG, PNG, or WebP image under 10 MB.',
        ]);

        $user = $request->user();
        $path = $request->file('avatar')->store('avatars', 'public');

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->update([
            'avatar_path' => $path,
        ]);

        return redirect()->route('profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
