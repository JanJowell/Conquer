<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'password_confirmation' => $request->input('password_confirmation')
                ?? $request->input('confirm_password')
                ?? $request->input('re_enter_password'),
        ]);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'max:120', Rule::unique(User::class)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'digits:11'],
            'gender' => ['nullable', 'string', 'max:50'],
            'birthdate' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_number' => ['nullable', 'digits:11'],
            'medical_conditions' => ['nullable', 'string'],
            'interests' => ['nullable', 'array'],
            'interests.*' => ['string', 'max:80'],
        ]);

        $validated['name'] = $validated['name']
            ?? trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? ''));

        if ($validated['name'] === '') {
            $validated['name'] = $validated['username'] ?? 'Runner';
        }

        if (empty($validated['email']) && ! empty($validated['username']) && filter_var($validated['username'], FILTER_VALIDATE_EMAIL)) {
            $validated['email'] = $validated['username'];
        }

        if (empty($validated['email']) && ! empty($validated['username'])) {
            $emailBase = Str::slug($validated['username']) ?: 'runner';
            $email = $emailBase.'@conquer.local';
            $suffix = 1;

            while (User::where('email', $email)->exists()) {
                $email = $emailBase.$suffix.'@conquer.local';
                $suffix++;
            }

            $validated['email'] = $email;
        }

        if (empty($validated['email'])) {
            return response()->json([
                'message' => 'Email or username is required.',
                'errors' => [
                    'email' => ['Email or username is required.'],
                ],
            ], 422);
        }

        $usernameBase = $validated['username'] ?? Str::before($validated['email'], '@');
        $username = Str::slug($usernameBase, '_') ?: 'runner';
        $suffix = 1;

        while (User::where('username', $username)->exists()) {
            $username = (Str::slug($usernameBase, '_') ?: 'runner').$suffix;
            $suffix++;
        }

        $validated['username'] = $username;
        $validated['role'] = User::ROLE_RUNNER;
        $validated['password'] = Hash::make($validated['password']);
        unset($validated['first_name'], $validated['last_name']);

        $user = User::create($validated);
        [$plainToken, $hashedToken] = $this->issueToken();

        $user->update([
            'api_token' => $hashedToken,
            'api_token_expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'message' => 'Registration successful.',
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user->fresh()),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'string'],
            'username' => ['nullable', 'string'],
            'identifier' => ['nullable', 'string'],
            'password' => ['required', 'string'],
        ]);

        $identifier = $validated['email'] ?? $validated['username'] ?? $validated['identifier'] ?? null;

        if (! $identifier) {
            return response()->json([
                'message' => 'Email or username is required.',
            ], 422);
        }

        $user = User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 422);
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

        [$plainToken, $hashedToken] = $this->issueToken();

        $user->update([
            'api_token' => $hashedToken,
            'api_token_expires_at' => now()->addDays(30),
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Login successful.',
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        if (! $request->filled('name') && ($request->filled('first_name') || $request->filled('last_name'))) {
            $request->merge([
                'name' => trim($request->input('first_name', '').' '.$request->input('last_name', '')),
            ]);
        }

        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'digits:11'],
            'gender' => ['nullable', 'string', 'max:50'],
            'birthdate' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_number' => ['nullable', 'digits:11'],
            'medical_conditions' => ['nullable', 'string'],
            'interests' => ['nullable', 'array'],
            'interests.*' => ['string', 'max:80'],
        ]);

        if ($user->normalizedRole() !== User::ROLE_RUNNER) {
            $validated['medical_conditions'] = null;
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function updateInterests(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'interests' => ['required', 'array', 'min:1'],
            'interests.*' => ['string', 'max:80'],
        ]);

        $request->user()->update([
            'interests' => array_values(array_unique($validated['interests'])),
        ]);

        return response()->json([
            'message' => 'Interests updated successfully.',
            'user' => $this->userPayload($request->user()->fresh()),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->merge([
            'password' => $request->input('password') ?? $request->input('new_password'),
            'password_confirmation' => $request->input('password_confirmation')
                ?? $request->input('confirm_password')
                ?? $request->input('re_enter_password'),
        ]);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => [
                    'current_password' => ['The current password is incorrect.'],
                ],
            ], 422);
        }

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($validated);

        return response()->json([
            'message' => __($status),
        ], $status === Password::RESET_LINK_SENT ? 200 : 422);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $validated['avatar']->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        return response()->json([
            'message' => 'Avatar updated successfully.',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->update([
            'api_token' => null,
            'api_token_expires_at' => null,
        ]);

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    private function issueToken(): array
    {
        $plainToken = Str::random(80);

        return [$plainToken, hash('sha256', $plainToken)];
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'role' => $user->normalizedRole(),
            'phone' => $user->phone,
            'gender' => $user->gender,
            'birthdate' => optional($user->birthdate)->format('Y-m-d'),
            'address' => $user->address,
            'emergency_contact_name' => $user->emergency_contact_name,
            'emergency_contact_number' => $user->emergency_contact_number,
            'medical_conditions' => $user->medical_conditions,
            'interests' => $user->interests ?? [],
            'avatar_url' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
            'email_verified_at' => optional($user->email_verified_at)?->toISOString(),
        ];
    }
}
