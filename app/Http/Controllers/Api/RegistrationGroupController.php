<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\RegistrationGroupResource;
use App\Models\Registration;
use App\Models\RegistrationGroup;
use App\Services\GroupRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegistrationGroupController extends Controller
{
    public function __construct(private readonly GroupRegistrationService $groups) {}

    public function show(Request $request, RegistrationGroup $registrationGroup): JsonResponse
    {
        $this->groups->assertMember($registrationGroup, $request->user());

        return response()->json(['data' => new RegistrationGroupResource($this->loadGroup($registrationGroup))]);
    }

    public function rotateInvitationCode(Request $request, RegistrationGroup $registrationGroup): JsonResponse
    {
        $code = DB::transaction(fn () => $this->groups->rotateCode(
            RegistrationGroup::lockForUpdate()->findOrFail($registrationGroup->id),
            $request->user()
        ));

        return response()->json([
            'message' => 'A new invitation code was created. The previous code no longer works.',
            'invitation_code' => $code,
            'data' => new RegistrationGroupResource($this->loadGroup($registrationGroup)),
        ]);
    }

    public function lock(Request $request, RegistrationGroup $registrationGroup): JsonResponse
    {
        $group = DB::transaction(fn () => $this->groups->lock(
            RegistrationGroup::lockForUpdate()->findOrFail($registrationGroup->id),
            $request->user()
        ));

        return response()->json([
            'message' => 'Group roster locked successfully.',
            'data' => new RegistrationGroupResource($this->loadGroup($group)),
        ]);
    }

    public function removeMember(Request $request, RegistrationGroup $registrationGroup, Registration $registration): JsonResponse
    {
        DB::transaction(function () use ($request, $registrationGroup, $registration) {
            $group = RegistrationGroup::lockForUpdate()->findOrFail($registrationGroup->id);
            $this->groups->assertLeader($group, $request->user());
            $this->groups->assertRosterMutable($group);

            abort_unless($registration->registration_group_id === $group->id, 404);
            abort_if($registration->user_id === $group->leader_user_id, 422, 'The leader cannot remove themselves.');

            $registration->update([
                'registration_group_id' => null,
                'status' => 'rejected',
                'rejection_reason' => 'Removed from group by the group leader.',
            ]);
            $this->groups->refreshStatus($group);
        });

        return response()->json([
            'message' => 'Member removed from the group.',
            'data' => new RegistrationGroupResource($this->loadGroup($registrationGroup)),
        ]);
    }

    public function leave(Request $request, RegistrationGroup $registrationGroup): JsonResponse
    {
        DB::transaction(function () use ($request, $registrationGroup) {
            $group = RegistrationGroup::lockForUpdate()->findOrFail($registrationGroup->id);
            $this->groups->assertMember($group, $request->user());
            $this->groups->assertRosterMutable($group);

            $registration = $group->activeRegistrations()
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($group->leader_user_id === $request->user()->id
                && $group->activeRegistrations()->where('id', '!=', $registration->id)->exists()) {
                abort(422, 'The group leader cannot leave while other members remain. Remove the members first.');
            }

            $registration->update([
                'registration_group_id' => null,
                'status' => 'rejected',
                'rejection_reason' => 'Participant left the registration group.',
            ]);

            if ($group->leader_user_id === $request->user()->id) {
                $group->update(['status' => RegistrationGroup::STATUS_EXPIRED]);
            } else {
                $this->groups->refreshStatus($group);
            }
        });

        return response()->json(['message' => 'You left the group. Your category registration was withdrawn.']);
    }

    private function loadGroup(RegistrationGroup $group): RegistrationGroup
    {
        return $group->fresh()->load(['category', 'activeRegistrations.user']);
    }
}
