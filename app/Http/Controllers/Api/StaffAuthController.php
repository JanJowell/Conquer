<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BannedIP;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StaffAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        if (BannedIP::isActiveFor($request->ip())) {
            return response()->json(['message' => 'This IP address has been blocked.'], 403);
        }

        $validated = $request->validate([
            'email' => ['nullable', 'string'],
            'username' => ['nullable', 'string'],
            'identifier' => ['nullable', 'string'],
            'password' => ['required', 'string'],
        ]);

        $identifier = $validated['email'] ?? $validated['username'] ?? $validated['identifier'] ?? null;

        if (! $identifier) {
            return response()->json(['message' => 'Email or username is required.'], 422);
        }

        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        if (! $user->hasAdminRole([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER])) {
            return response()->json(['message' => 'This account is not authorized to use the finish scanner.'], 403);
        }

        if ($user->isBanned()) {
            return response()->json(['message' => 'This account has been banned.'], 403);
        }

        if ($user->isSuspended()) {
            return response()->json(['message' => 'This account is currently suspended.'], 423);
        }

        if ($user->email_verified_at === null) {
            return response()->json([
                'message' => 'Activate your administrator account using the invitation sent to your email.',
            ], 403);
        }

        $plainToken = Str::random(64);
        $expiresAt = now()->addHours(12);

        $user->forceFill([
            'api_token' => hash('sha256', $plainToken),
            'api_token_expires_at' => $expiresAt,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return response()->json([
            'message' => 'Scanner login successful.',
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->normalizedRole(),
            ],
        ]);
    }
}
