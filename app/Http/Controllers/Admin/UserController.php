<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AdminActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
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
            $query->whereNotNull('email_verified_at');
        } elseif ($request->status === 'inactive') {
            $query->whereNull('email_verified_at');
        }

        $users = $query->latest()->paginate(10);

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::in(User::manageableRoles())],
            'phone' => ['nullable', 'digits:11'],
            'gender' => 'nullable|string|max:50',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string|max:500',
            'medical_conditions' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_number' => ['nullable', 'digits:11'],
        ]);

        if (($validated['role'] ?? null) !== User::ROLE_RUNNER) {
            $validated['medical_conditions'] = null;
        }

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

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

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(User::manageableRoles())],
            'phone' => ['nullable', 'digits:11'],
            'gender' => 'nullable|string|max:50',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string|max:500',
            'medical_conditions' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_number' => ['nullable', 'digits:11'],
        ]);

        if (($validated['role'] ?? null) !== User::ROLE_RUNNER) {
            $validated['medical_conditions'] = null;
        }

        $user->update($validated);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    public function suspend(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot suspend your own account.');
        }

        $user->update(['suspended_at' => now()]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User suspended successfully.');
    }

    public function unsuspend(User $user)
    {
        $user->update(['suspended_at' => null]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User unsuspended successfully.');
    }

    public function ban(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot ban your own account.');
        }

        $user->update(['banned_at' => now()]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User banned successfully.');
    }

    public function unban(User $user)
    {
        $user->update(['banned_at' => null]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User unbanned successfully.');
    }
}
