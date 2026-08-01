<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class MobileRecommendationContext
{
    public function userFromBearerToken(Request $request): ?User
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        $user = User::where('api_token', hash('sha256', $token))
            ->where(function ($query) {
                $query->whereNull('api_token_expires_at')
                    ->orWhere('api_token_expires_at', '>', now());
            })
            ->first();

        if (! $this->userCanReceiveRecommendations($user)) {
            return null;
        }

        return $user;
    }

    public function requestedInterests(Request $request): array
    {
        return $this->normalizeInterests(
            $request->input('interests', $request->input('interest', []))
        );
    }

    public function recommendedInterests(Request $request): array
    {
        $user = $this->userFromBearerToken($request);

        return $this->userInterests($user);
    }

    public function userInterests(?User $user): array
    {
        if (! $this->userCanReceiveRecommendations($user)) {
            return [];
        }

        return $this->normalizeInterests($user->interests ?? []);
    }

    public function normalizeInterests(mixed $raw): array
    {
        $values = is_array($raw) ? $raw : explode(',', (string) $raw);
        $allowed = config('conquer.event_interest_types', []);

        return collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->filter(fn ($value) => in_array($value, $allowed, true))
            ->values()
            ->all();
    }

    private function userCanReceiveRecommendations(?User $user): bool
    {
        return $user !== null
            && ! $user->isBanned()
            && ! $user->isSuspended();
    }
}
