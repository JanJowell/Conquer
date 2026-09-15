<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Event;
use App\Models\RegistrationGroup;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GroupRegistrationService
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function create(User $leader, Event $event, Category $category, string $name): array
    {
        $this->ensureVerified($leader);

        $alreadyLeads = RegistrationGroup::query()
            ->where('category_id', $category->id)
            ->where('leader_user_id', $leader->id)
            ->whereIn('status', [RegistrationGroup::STATUS_FORMING, RegistrationGroup::STATUS_READY, RegistrationGroup::STATUS_LOCKED])
            ->exists();

        if ($alreadyLeads) {
            throw ValidationException::withMessages([
                'group_name' => 'You already lead a group for this category.',
            ]);
        }

        [$code, $hash] = $this->uniqueCode();
        $group = RegistrationGroup::create([
            'event_id' => $event->id,
            'category_id' => $category->id,
            'leader_user_id' => $leader->id,
            'name' => trim($name),
            'join_code_hash' => $hash,
            'status' => RegistrationGroup::STATUS_FORMING,
            'expires_at' => $event->registration_deadline?->endOfDay(),
        ]);

        return [$group, $code];
    }

    public function findForJoin(string $code, Event $event, Category $category, User $user): RegistrationGroup
    {
        $this->ensureVerified($user);
        $normalized = $this->normalizeCode($code);

        if (strlen($normalized) !== 10) {
            throw ValidationException::withMessages(['invitation_code' => 'The invitation code is invalid.']);
        }

        $group = RegistrationGroup::query()
            ->where('join_code_hash', hash('sha256', $normalized))
            ->lockForUpdate()
            ->first();

        if (! $group || $group->event_id !== $event->id || $group->category_id !== $category->id) {
            throw ValidationException::withMessages(['invitation_code' => 'The invitation code is invalid for this category.']);
        }

        $this->assertJoinable($group);

        return $group;
    }

    public function assertJoinable(RegistrationGroup $group): void
    {
        if ($group->expires_at?->isPast()) {
            $group->update(['status' => RegistrationGroup::STATUS_EXPIRED]);
        }

        if (! in_array($group->status, [RegistrationGroup::STATUS_FORMING, RegistrationGroup::STATUS_READY], true)) {
            throw ValidationException::withMessages(['invitation_code' => 'This group is no longer accepting members.']);
        }

        $maximum = $group->category()->value('group_max_members');
        if ($maximum && $group->activeRegistrations()->count() >= $maximum) {
            throw ValidationException::withMessages(['invitation_code' => 'This group has reached its maximum size.']);
        }
    }

    public function refreshStatus(RegistrationGroup $group): RegistrationGroup
    {
        $group->refresh()->loadMissing('category');

        if (in_array($group->status, [RegistrationGroup::STATUS_LOCKED, RegistrationGroup::STATUS_EXPIRED], true)) {
            return $group;
        }

        $memberCount = $group->activeRegistrations()->count();
        $minimum = max((int) ($group->category?->group_min_members ?? 2), 2);
        $group->update([
            'status' => $memberCount >= $minimum
                ? RegistrationGroup::STATUS_READY
                : RegistrationGroup::STATUS_FORMING,
        ]);

        return $group->refresh();
    }

    public function isReady(RegistrationGroup $group): bool
    {
        $group = $this->refreshStatus($group);

        return $group->status === RegistrationGroup::STATUS_LOCKED
            || ($group->status === RegistrationGroup::STATUS_READY
                && $group->activeRegistrations()->count() >= max((int) $group->category->group_min_members, 2));
    }

    public function permissionsFor(RegistrationGroup $group, ?User $user): array
    {
        $group->loadMissing(['category', 'activeRegistrations']);

        $members = $group->activeRegistrations;
        $currentRegistration = $user
            ? $members->firstWhere('user_id', $user->id)
            : null;
        $isLeader = $user && $group->leader_user_id === $user->id;
        $isMember = $currentRegistration !== null;
        $minimum = max((int) ($group->category?->group_min_members ?? 2), 2);
        $isReady = $group->status === RegistrationGroup::STATUS_LOCKED
            || ($group->status === RegistrationGroup::STATUS_READY && $members->count() >= $minimum);
        $rosterMutable = $this->rosterIsMutable($group);
        $paymentStatus = $currentRegistration?->payment_status;
        $paymentBlockedReason = null;

        if ($isMember && $currentRegistration->payment_required && (int) ($currentRegistration->payment_amount_cents ?? 0) > 0) {
            if ($group->status === RegistrationGroup::STATUS_EXPIRED) {
                $paymentBlockedReason = 'This group has expired and cannot proceed to payment.';
            } elseif (! $isReady) {
                $membersNeeded = max($minimum - $members->count(), 0);
                $paymentBlockedReason = $membersNeeded === 1
                    ? '1 more member must join before payment can begin.'
                    : "{$membersNeeded} more members must join before payment can begin.";
            } elseif ($paymentStatus === 'paid') {
                $paymentBlockedReason = 'This registration is already paid.';
            } elseif ($currentRegistration->status !== 'pending') {
                $paymentBlockedReason = 'Payment is only available while the registration is pending.';
            } elseif (! in_array($paymentStatus, ['unpaid', 'pending', 'submitted', 'failed'], true)) {
                $paymentBlockedReason = 'Payment can no longer be changed for this registration.';
            }
        }

        $canPay = $isMember
            && $isReady
            && $currentRegistration->status === 'pending'
            && $currentRegistration->payment_required
            && (int) ($currentRegistration->payment_amount_cents ?? 0) > 0
            && in_array($paymentStatus, ['unpaid', 'pending', 'submitted', 'failed'], true);

        return [
            'can_rotate_code' => (bool) ($isLeader && $rosterMutable),
            'can_lock' => (bool) ($isLeader
                && $group->status === RegistrationGroup::STATUS_READY
                && $members->count() >= $minimum),
            'can_remove_members' => (bool) ($isLeader
                && $rosterMutable
                && $members->contains(fn ($registration) => $registration->user_id !== $group->leader_user_id)),
            'can_leave' => (bool) ($isMember
                && $rosterMutable
                && (! $isLeader || $members->count() === 1)),
            'can_pay' => (bool) $canPay,
            'payment_blocked_reason' => $canPay ? null : $paymentBlockedReason,
        ];
    }

    public function rosterIsMutable(RegistrationGroup $group): bool
    {
        return in_array($group->status, [RegistrationGroup::STATUS_FORMING, RegistrationGroup::STATUS_READY], true)
            && ! $group->activeRegistrations()->whereIn('status', ['approved', 'checked_in', 'completed'])->exists()
            && ! $group->activeRegistrations()->whereNotIn('payment_status', ['unpaid', 'waived'])->exists();
    }

    public function rotateCode(RegistrationGroup $group, User $user): string
    {
        $this->assertLeader($group, $user);
        $this->assertRosterMutable($group);
        [$code, $hash] = $this->uniqueCode();
        $group->update(['join_code_hash' => $hash]);

        return $code;
    }

    public function lock(RegistrationGroup $group, User $user): RegistrationGroup
    {
        $this->assertLeader($group, $user);

        if (! $this->isReady($group)) {
            throw ValidationException::withMessages(['group' => 'The group must reach its minimum size before it can be locked.']);
        }

        $group->update(['status' => RegistrationGroup::STATUS_LOCKED, 'locked_at' => now()]);

        return $group->refresh();
    }

    public function assertLeader(RegistrationGroup $group, User $user): void
    {
        if ($group->leader_user_id !== $user->id) {
            abort(403, 'Only the group leader can perform this action.');
        }
    }

    public function assertMember(RegistrationGroup $group, User $user): void
    {
        if (! $group->activeRegistrations()->where('user_id', $user->id)->exists()) {
            abort(403, 'You are not a member of this group.');
        }
    }

    public function assertRosterMutable(RegistrationGroup $group): void
    {
        if (! $this->rosterIsMutable($group)) {
            throw ValidationException::withMessages(['group' => 'This group roster can no longer be changed.']);
        }
    }

    private function ensureVerified(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => 'Email verification is required before joining a group.']);
        }
    }

    private function uniqueCode(): array
    {
        do {
            $plain = collect(range(1, 10))
                ->map(fn () => self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)])
                ->implode('');
            $hash = hash('sha256', $plain);
        } while (RegistrationGroup::where('join_code_hash', $hash)->exists());

        return [substr($plain, 0, 5).'-'.substr($plain, 5), $hash];
    }

    private function normalizeCode(string $code): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', trim($code)) ?? '');
    }
}
