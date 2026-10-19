<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AdministrativeAccountPolicy
{
    /**
     * @return array<string, array{role: string, label: string, count: int, limit: int|null, remaining: int|null, limited: bool, at_limit: bool}>
     */
    public function capacity(): array
    {
        $labels = User::roleLabels();
        $capacity = [];

        foreach ($this->administrativeRoles() as $role) {
            $count = $this->countFor($role);
            $limit = $this->limitFor($role);

            $capacity[$role] = [
                'role' => $role,
                'label' => $labels[$role] ?? str($role)->replace('_', ' ')->title()->toString(),
                'count' => $count,
                'limit' => $limit,
                'remaining' => $limit === null ? null : max(0, $limit - $count),
                'limited' => $limit !== null,
                'at_limit' => $limit !== null && $count >= $limit,
            ];
        }

        return $capacity;
    }

    /**
     * Lock the shared administrative-account set before checking a limit.
     * This prevents two concurrent requests from consuming the same final slot.
     *
     * @return Collection<int, User>
     */
    public function lockAdministrativeAccounts(): Collection
    {
        return User::query()
            ->whereIn('role', User::storedAdminRoles())
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    public function assertRoleAvailable(string $role, ?User $currentUser = null): void
    {
        $role = $this->canonicalRole($role);

        if (! in_array($role, $this->administrativeRoles(), true)) {
            return;
        }

        if ($currentUser !== null && $this->canonicalRole($currentUser->role) === $role) {
            return;
        }

        $limit = $this->limitFor($role);

        if ($limit === null || $this->countFor($role) < $limit) {
            return;
        }

        $label = User::roleLabels()[$role] ?? str($role)->replace('_', ' ')->title()->toString();

        throw ValidationException::withMessages([
            'role' => "The {$label} account limit of {$limit} has been reached. Delete an existing {$label} account or change its role before adding another.",
        ]);
    }

    public function assertLastActiveAdminRemains(
        User $user,
        string $newRole,
        string $newEmail
    ): void {
        if (! $this->isActiveAdmin($user)) {
            return;
        }

        $removesActiveAccess = $this->canonicalRole($newRole) !== User::ROLE_SUPER_ADMIN
            || $newEmail !== $user->email;

        if (! $removesActiveAccess || $this->activeAdminCount() > 1) {
            return;
        }

        $field = $this->canonicalRole($newRole) !== User::ROLE_SUPER_ADMIN ? 'role' : 'email';

        throw ValidationException::withMessages([
            $field => 'At least one verified, active Admin account must remain. Verify or reactivate another Admin before changing this account.',
        ]);
    }

    public function canDeactivate(User $user): bool
    {
        return ! $this->isActiveAdmin($user) || $this->activeAdminCount() > 1;
    }

    public function countFor(string $role): int
    {
        $role = $this->canonicalRole($role);
        $query = User::query();

        if ($role === User::ROLE_EVENT_MANAGER) {
            return $query->whereIn('role', [User::ROLE_EVENT_MANAGER, User::ROLE_LEGACY_ADMIN])->count();
        }

        return $query->where('role', $role)->count();
    }

    public function activeAdminCount(): int
    {
        return User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->whereNotNull('email_verified_at')
            ->whereNull('suspended_at')
            ->whereNull('banned_at')
            ->count();
    }

    public function limitFor(string $role): ?int
    {
        $configured = config('admin_accounts.limits.'.$this->canonicalRole($role));

        if ($configured === null || $configured === '') {
            return null;
        }

        return max(0, (int) $configured);
    }

    private function isActiveAdmin(User $user): bool
    {
        return $this->canonicalRole($user->role) === User::ROLE_SUPER_ADMIN
            && $user->email_verified_at !== null
            && $user->suspended_at === null
            && $user->banned_at === null;
    }

    /** @return list<string> */
    private function administrativeRoles(): array
    {
        return [
            User::ROLE_SUPER_ADMIN,
            User::ROLE_EXECUTIVE,
            User::ROLE_CONTENT_MODERATOR,
            User::ROLE_EVENT_MANAGER,
        ];
    }

    private function canonicalRole(string $role): string
    {
        return $role === User::ROLE_LEGACY_ADMIN ? User::ROLE_EVENT_MANAGER : $role;
    }
}
