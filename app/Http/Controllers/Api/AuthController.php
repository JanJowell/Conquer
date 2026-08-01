<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BannedIP;
use App\Models\User;
use App\Services\InterestNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        if (BannedIP::isActiveFor($request->ip())) {
            return response()->json([
                'message' => 'This IP address has been blocked.',
            ], 403);
        }

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
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)],
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
        $this->normalizeInterests($validated);
        unset($validated['first_name'], $validated['last_name']);

        $user = User::create($validated);

        if (! $this->sendEmailVerificationCode($user->email)) {
            return response()->json([
                'message' => 'Registration successful, but we could not send the verification code right now. Please request a new code before logging in.',
                'email_verification_required' => true,
                'user' => $this->userPayload($user->fresh()),
            ], 201);
        }

        return response()->json([
            'message' => 'Registration successful. Please verify your email before logging in.',
            'email_verification_required' => true,
            'user' => $this->userPayload($user->fresh()),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        if (BannedIP::isActiveFor($request->ip())) {
            return response()->json([
                'message' => 'This IP address has been blocked.',
            ], 403);
        }

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

        if ($user->normalizedRole() !== User::ROLE_RUNNER) {
            return response()->json([
                'message' => 'This account can only sign in through the admin web portal.',
            ], 403);
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

        if ($user->email_verified_at === null) {
            return response()->json([
                'message' => 'Please verify your email before logging in.',
                'email_verification_required' => true,
            ], 403);
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

    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', Rule::exists(User::class, 'email')],
            'code' => ['required', 'digits:6'],
        ]);

        $user = User::where('email', $validated['email'])->firstOrFail();

        if ($user->email_verified_at !== null) {
            DB::table('email_verification_codes')
                ->where('email', $validated['email'])
                ->delete();

            return response()->json([
                'message' => 'Email is already verified.',
                'user' => $this->userPayload($user),
            ]);
        }

        if (! $this->emailVerificationCodeIsValid($validated['email'], $validated['code'])) {
            return response()->json([
                'message' => 'The verification code is invalid or has expired.',
            ], 422);
        }

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        DB::table('email_verification_codes')
            ->where('email', $validated['email'])
            ->delete();

        return response()->json([
            'message' => 'Email verified successfully. You can now log in.',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function resendVerificationCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', Rule::exists(User::class, 'email')],
        ]);

        $user = User::where('email', $validated['email'])->firstOrFail();

        if ($user->email_verified_at !== null) {
            return response()->json([
                'message' => 'Email is already verified.',
            ]);
        }

        if (! $this->sendEmailVerificationCode($user->email)) {
            return response()->json([
                'message' => 'Unable to send verification code right now. Please try again later.',
            ], 500);
        }

        return response()->json([
            'message' => 'Verification code sent.',
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

        $this->normalizeInterests($validated);

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
            'interests' => app(InterestNormalizer::class)->normalize($validated['interests']),
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
            'email' => ['required', 'email', Rule::exists(User::class, 'email')],
        ]);

        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $validated['email']],
            [
                'token' => Hash::make($code),
                'created_at' => now(),
            ]
        );

        try {
            Mail::raw(
                "Your RaceTech password reset code is {$code}.\n\nThis code expires in 15 minutes.",
                function ($message) use ($validated) {
                    $message->to($validated['email'])
                        ->subject('RaceTech Password Reset Code');
                }
            );
        } catch (\Throwable $e) {
            Log::error('Forgot password email failed.', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to send reset code right now. Please try again later.',
            ], 500);
        }

        return response()->json([
            'message' => 'Password reset code sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->merge([
            'password_confirmation' => $request->input('password_confirmation')
                ?? $request->input('confirm_password')
                ?? $request->input('re_enter_password'),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'email', Rule::exists(User::class, 'email')],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! $this->resetCodeIsValid($validated['email'], $validated['code'])) {
            return response()->json([
                'message' => 'The reset code is invalid or has expired.',
            ], 422);
        }

        User::where('email', $validated['email'])->update([
            'password' => Hash::make($validated['password']),
            'api_token' => null,
            'api_token_expires_at' => null,
        ]);

        DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->delete();

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }

    public function verifyResetCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', Rule::exists(User::class, 'email')],
            'code' => ['required', 'digits:6'],
        ]);

        if (! $this->resetCodeIsValid($validated['email'], $validated['code'])) {
            return response()->json([
                'message' => 'The reset code is invalid or has expired.',
            ], 422);
        }

        return response()->json([
            'message' => 'Reset code verified.',
        ]);
    }

    private function resetCodeIsValid(string $email, string $code): bool
    {
        $reset = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        return $reset
            && ! now()->subMinutes(15)->greaterThan($reset->created_at)
            && Hash::check($code, $reset->token);
    }

    private function sendEmailVerificationCode(string $email): bool
    {
        $code = (string) random_int(100000, 999999);

        DB::table('email_verification_codes')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($code),
                'created_at' => now(),
            ]
        );

        try {
            Mail::raw(
                "Your RaceTech email verification code is {$code}.\n\nThis code expires in 15 minutes.",
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('RaceTech Email Verification Code');
                }
            );
        } catch (\Throwable $e) {
            Log::error('Email verification code failed.', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    private function emailVerificationCodeIsValid(string $email, string $code): bool
    {
        $verification = DB::table('email_verification_codes')
            ->where('email', $email)
            ->first();

        return $verification
            && Carbon::parse($verification->created_at)->greaterThanOrEqualTo(now()->subMinutes(15))
            && Hash::check($code, $verification->token);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
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

    private function normalizeInterests(array &$data): void
    {
        if (array_key_exists('interests', $data)) {
            $data['interests'] = app(InterestNormalizer::class)->normalize($data['interests']);
        }
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
            'badges_count' => $user->issuedEBadges()->count(),
            'email_verified_at' => optional($user->email_verified_at)?->toISOString(),
        ];
    }
}
