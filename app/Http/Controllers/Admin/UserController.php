<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\User;
use App\Services\AdminInvitationService;
use App\Support\AdministrativeAccountPolicy;
use App\Support\ParticipantBirthdate;
use App\Support\ParticipantHealthProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class UserController extends Controller
{
    public function index(Request $request, AdministrativeAccountPolicy $accountPolicy)
    {
        $query = User::query();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->role) {
            if ($request->role === User::ROLE_EVENT_MANAGER) {
                $query->whereIn('role', [User::ROLE_EVENT_MANAGER, User::ROLE_LEGACY_ADMIN]);
            } else {
                $query->where('role', $request->role);
            }
        }

        if ($request->status === 'active') {
            $query->whereNotNull('api_token')
                ->whereNull('suspended_at')
                ->whereNull('banned_at')
                ->where(function ($q) {
                    $q->whereNull('api_token_expires_at')
                        ->orWhere('api_token_expires_at', '>', now());
                });
        } elseif ($request->status === 'inactive') {
            $query->whereNull('suspended_at')
                ->whereNull('banned_at')
                ->where(function ($q) {
                    $q->whereNull('api_token')
                        ->orWhere('api_token_expires_at', '<=', now());
                });
        } elseif ($request->status === 'suspended') {
            $query->whereNotNull('suspended_at')
                ->whereNull('banned_at');
        } elseif ($request->status === 'banned') {
            $query->whereNotNull('banned_at');
        } elseif ($request->status === 'pending_verification') {
            $query->whereIn('role', User::storedAdminRoles())->whereNull('email_verified_at');
        } elseif ($request->status === 'verified') {
            $query->whereIn('role', User::storedAdminRoles())->whereNotNull('email_verified_at');
        }

        $users = $query->latest()->paginate(10);

        $administratorCapacity = $accountPolicy->capacity();

        return view('admin.users.index', compact('users', 'administratorCapacity'));
    }

    public function create(AdministrativeAccountPolicy $accountPolicy)
    {
        $administratorCapacity = $accountPolicy->capacity();

        return view('admin.users.create', compact('administratorCapacity'));
    }

    public function store(
        Request $request,
        AdminInvitationService $invitations,
        AdministrativeAccountPolicy $accountPolicy
    )
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => [
                Rule::requiredIf(fn () => $request->input('role') === User::ROLE_RUNNER),
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
            'role' => ['required', Rule::in(User::manageableRoles())],
            'phone' => ['nullable', 'digits:11'],
            'gender' => 'nullable|string|max:50',
            'birthdate' => ParticipantBirthdate::rules(
                required: $request->input('role') === User::ROLE_RUNNER
            ),
            'address' => 'nullable|string|max:500',
            'medical_conditions' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_number' => ['nullable', 'digits:11'],
            ...ParticipantHealthProfile::validationRules(),
        ], ParticipantBirthdate::messages());

        if (($validated['role'] ?? null) !== User::ROLE_RUNNER) {
            foreach ([
                'address',
                'medical_conditions',
                'emergency_contact',
                'emergency_contact_name',
                'emergency_contact_number',
                'height_cm',
                'weight_kg',
                'bmi_measured_at',
            ] as $participantProfileField) {
                $validated[$participantProfileField] = null;
            }
        }

        if (($validated['role'] ?? null) === User::ROLE_RUNNER) {
            $validated = ParticipantHealthProfile::normalize($validated);
            if ($request->exists('height_cm') || $request->exists('weight_kg')) {
                $validated['health_profile_updated_at'] = now();
            }
        }

        $validated['password'] = Hash::make($validated['password'] ?? Str::random(64));

        $user = DB::transaction(function () use ($accountPolicy, $validated): User {
            if ($validated['role'] !== User::ROLE_RUNNER) {
                $accountPolicy->lockAdministrativeAccounts();
            }

            $accountPolicy->assertRoleAvailable($validated['role']);

            return User::create($validated);
        });

        if ($user->isAdmin()) {
            try {
                $invitations->send($user, $request->user());
            } catch (Throwable $exception) {
                report($exception);

                return redirect()->route('admin.users.index')->with(
                    'error',
                    'The administrator account was created as pending, but the invitation email could not be delivered. Check the mail configuration and use Resend Invitation.'
                );
            }

            $this->logAccountAction($request, 'Invited administrator '.$user->email);

            return redirect()->route('admin.users.index')
                ->with('success', 'Administrator created. A 24-hour activation invitation was sent to '.$user->email.'.');
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function show(User $user)
    {
        $user->load(['registrations.event', 'raceResults.event']);
        $activities = AdminActivityLog::where('user_id', $user->id)
            ->latest()
            ->take(20)
            ->get();

        return view('admin.users.show', compact('user', 'activities'));
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(
        Request $request,
        User $user,
        AdminInvitationService $invitations,
        AdministrativeAccountPolicy $accountPolicy
    )
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(User::manageableRoles())],
            'phone' => ['nullable', 'digits:11'],
            'gender' => 'nullable|string|max:50',
            'birthdate' => ParticipantBirthdate::rules(),
            'address' => 'nullable|string|max:500',
            'medical_conditions' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_number' => ['nullable', 'digits:11'],
            ...ParticipantHealthProfile::validationRules(),
        ], ParticipantBirthdate::messages());

        if (($validated['role'] ?? null) !== User::ROLE_RUNNER) {
            $validated['medical_conditions'] = null;
            $validated['height_cm'] = null;
            $validated['weight_kg'] = null;
            $validated['bmi_measured_at'] = null;
        } else {
            $validated = ParticipantHealthProfile::normalize($validated, $user);
            if ($request->exists('height_cm') || $request->exists('weight_kg')) {
                $validated['health_profile_updated_at'] = now();
            }
        }

        if ($user->id === auth()->id()
            && $user->isSuperAdmin()
            && $validated['role'] !== User::ROLE_SUPER_ADMIN) {
            return back()
                ->withInput()
                ->with('error', 'You cannot remove your own Admin access.');
        }

        $updateState = DB::transaction(function () use ($accountPolicy, $user, $validated): array {
            $accountPolicy->lockAdministrativeAccounts();
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            $accountPolicy->assertRoleAvailable($validated['role'], $lockedUser);
            $accountPolicy->assertLastActiveAdminRemains(
                $lockedUser,
                $validated['role'],
                $validated['email']
            );

            $wasAdmin = $lockedUser->isAdmin();
            $originalEmail = $lockedUser->email;
            $lockedUser->update($validated);
            $emailChanged = $originalEmail !== $lockedUser->email;

            if (! $lockedUser->isAdmin()) {
                $lockedUser->forceFill([
                    'admin_invitation_token' => null,
                    'admin_invitation_sent_at' => null,
                    'admin_invitation_expires_at' => null,
                    'admin_invited_by' => null,
                ])->save();
            } elseif ($emailChanged) {
                $lockedUser->forceFill([
                    'email_verified_at' => null,
                    'api_token' => null,
                    'api_token_expires_at' => null,
                    'admin_invitation_token' => null,
                    'admin_invitation_sent_at' => null,
                    'admin_invitation_expires_at' => null,
                ])->save();
            }

            return [
                'user' => $lockedUser,
                'was_admin' => $wasAdmin,
                'email_changed' => $emailChanged,
            ];
        });

        /** @var User $user */
        $user = $updateState['user'];
        $wasAdmin = $updateState['was_admin'];
        $emailChanged = $updateState['email_changed'];

        if ($emailChanged && $user->isAdmin()) {

            try {
                $invitations->send($user, $request->user());
            } catch (Throwable $exception) {
                report($exception);

                return redirect()->route('admin.users.index')->with(
                    'error',
                    'The account was updated and its new email requires verification, but the invitation could not be delivered. Use Resend Invitation.'
                );
            }
        } elseif (! $wasAdmin && $user->isAdmin() && $user->email_verified_at === null) {
            try {
                $invitations->send($user, $request->user());
            } catch (Throwable $exception) {
                report($exception);

                return redirect()->route('admin.users.index')->with(
                    'error',
                    'The account was promoted but its invitation could not be delivered. Use Resend Invitation.'
                );
            }
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function resendInvitation(Request $request, User $user, AdminInvitationService $invitations)
    {
        if (! $user->isAdmin()) {
            return back()->with('error', 'Invitations are only available for administrator accounts.');
        }

        if ($user->email_verified_at !== null) {
            return back()->with('error', 'This administrator account is already verified.');
        }

        $cooldownSeconds = max(1, (int) config('admin_invitations.resend_cooldown_seconds', 120));
        $resendAvailableAt = $user->admin_invitation_sent_at?->copy()->addSeconds($cooldownSeconds);

        if ($resendAvailableAt?->isFuture()) {
            $secondsRemaining = max(1, $resendAvailableAt->getTimestamp() - now()->getTimestamp());

            return back()->with(
                'error',
                'Please wait '.$this->formatInvitationWait($secondsRemaining).' before resending this invitation.'
            );
        }

        $hourlyLimit = max(1, (int) config('admin_invitations.resend_max_per_hour', 5));
        $rateLimitKey = 'admin-invitation-resend:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($rateLimitKey, $hourlyLimit)) {
            return back()->with(
                'error',
                'The hourly resend limit has been reached for this administrator. Try again in '
                    .$this->formatInvitationWait(RateLimiter::availableIn($rateLimitKey)).'.'
            );
        }

        try {
            $invitations->send($user, $request->user());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'The invitation could not be delivered. Check the mail configuration and try again.');
        }

        RateLimiter::hit($rateLimitKey, 3600);

        $this->logAccountAction($request, 'Resent administrator invitation to '.$user->email);

        return back()->with('success', 'A new 24-hour invitation was sent to '.$user->email.'. Previous invitation links are no longer valid.');
    }

    public function destroy(User $user, AdministrativeAccountPolicy $accountPolicy)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $deleted = DB::transaction(function () use ($accountPolicy, $user): bool {
            $accountPolicy->lockAdministrativeAccounts();
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $accountPolicy->canDeactivate($lockedUser)) {
                return false;
            }

            $lockedUser->delete();

            return true;
        });

        if (! $deleted) {
            return redirect()->route('admin.users.index')
                ->with('error', $this->lastAdminError('delete'));
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    public function suspend(User $user, AdministrativeAccountPolicy $accountPolicy)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot suspend your own account.');
        }

        $suspended = DB::transaction(function () use ($accountPolicy, $user): bool {
            $accountPolicy->lockAdministrativeAccounts();
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $accountPolicy->canDeactivate($lockedUser)) {
                return false;
            }

            $lockedUser->update(['suspended_at' => now()]);

            return true;
        });

        if (! $suspended) {
            return redirect()->route('admin.users.index')
                ->with('error', $this->lastAdminError('suspend'));
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User suspended successfully.');
    }

    public function unsuspend(User $user)
    {
        $user->update(['suspended_at' => null]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User unsuspended successfully.');
    }

    public function ban(User $user, AdministrativeAccountPolicy $accountPolicy)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot ban your own account.');
        }

        $banned = DB::transaction(function () use ($accountPolicy, $user): bool {
            $accountPolicy->lockAdministrativeAccounts();
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $accountPolicy->canDeactivate($lockedUser)) {
                return false;
            }

            $lockedUser->update(['banned_at' => now()]);

            return true;
        });

        if (! $banned) {
            return redirect()->route('admin.users.index')
                ->with('error', $this->lastAdminError('ban'));
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User banned successfully.');
    }

    public function unban(User $user)
    {
        $user->update(['banned_at' => null]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User unbanned successfully.');
    }

    private function logAccountAction(Request $request, string $action): void
    {
        AdminActivityLog::create([
            'user_id' => $request->user()->getKey(),
            'action' => $action,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function formatInvitationWait(int $seconds): string
    {
        $seconds = max(1, $seconds);

        if ($seconds < 60) {
            return $seconds.' second'.($seconds === 1 ? '' : 's');
        }

        $minutes = (int) ceil($seconds / 60);

        return $minutes.' minute'.($minutes === 1 ? '' : 's');
    }

    private function lastAdminError(string $action): string
    {
        return 'At least one verified, active Admin account must remain. Verify or reactivate another Admin before you '
            .$action.' this account.';
    }
}
