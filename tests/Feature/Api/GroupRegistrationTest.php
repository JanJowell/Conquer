<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\RegistrationGroup;
use App\Models\User;

function groupRunner(string $token): User
{
    return User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', $token),
        'api_token_expires_at' => now()->addDay(),
    ]);
}

function groupEventAndCategory(array $categoryOverrides = []): array
{
    $event = Event::create([
        'title' => 'Community Team Run',
        'slug' => 'community-team-run-'.uniqid(),
        'description' => 'A group registration test event.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'event_end_date' => now()->addMonth()->toDateString(),
        'start_time' => '06:00',
        'end_time' => '12:00',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'status' => 'upcoming',
        'banner_image' => 'events/banners/test.jpg',
        'organized_by' => 'Racetech',
        'interest_type' => config('conquer.event_interest_types.0'),
    ]);

    $category = Category::create([
        'event_id' => $event->id,
        'name' => '5K Group Run',
        'distance_km' => 5,
        'slot_limit' => 20,
        'status' => 'open',
        'scheduled_start_date' => $event->event_date,
        'scheduled_start_time' => '06:00',
        'scheduled_end_date' => $event->event_date,
        'scheduled_end_time' => '08:00',
        'participation_mode' => Category::PARTICIPATION_GROUP,
        'group_min_members' => 2,
        'group_max_members' => 4,
        ...$categoryOverrides,
    ]);

    return [$event, $category];
}

function groupRegistrationPayload(array $overrides = []): array
{
    return [
        'shirt_size' => 'M',
        'first_aid_kit_confirmed' => true,
        'waiver_accepted' => true,
        ...$overrides,
    ];
}

test('individual categories keep the existing registration flow', function () {
    $token = 'individual-group-compatibility';
    $user = groupRunner($token);
    [$event, $category] = groupEventAndCategory([
        'participation_mode' => Category::PARTICIPATION_INDIVIDUAL,
        'group_min_members' => null,
        'group_max_members' => null,
    ]);

    $this->withToken($token)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload())
        ->assertCreated()
        ->assertJsonPath('data.category.participation_mode', 'individual')
        ->assertJsonPath('data.registration_group', null);

    expect($user->registrations()->first()->registration_group_id)->toBeNull();
});

test('a verified participant can create a group and receives a one-time invitation code', function () {
    $token = 'group-leader-token';
    $leader = groupRunner($token);
    [$event, $category] = groupEventAndCategory();

    $response = $this->withToken($token)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload([
            'group_action' => 'create',
            'group_name' => 'Weekend Warriors',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.registration_group.name', 'Weekend Warriors')
        ->assertJsonPath('data.registration_group.status', RegistrationGroup::STATUS_FORMING)
        ->assertJsonPath('data.registration_group.is_leader', true)
        ->assertJsonPath('data.registration_group.can_rotate_code', true)
        ->assertJsonPath('data.registration_group.can_lock', false)
        ->assertJsonPath('data.registration_group.can_remove_members', false)
        ->assertJsonPath('data.registration_group.can_leave', true)
        ->assertJsonPath('data.registration_group.can_pay', false)
        ->assertJsonPath('data.registration_group.payment_blocked_reason', null);

    $code = $response->json('invitation_code');
    $group = RegistrationGroup::firstOrFail();

    expect($code)->toMatch('/^[A-Z2-9]{5}-[A-Z2-9]{5}$/')
        ->and($group->join_code_hash)->toBe(hash('sha256', str_replace('-', '', $code)))
        ->and($group->join_code_hash)->not->toContain($code)
        ->and($leader->registrations()->first()->registration_group_id)->toBe($group->id);
});

test('a verified participant can join with the code and make the group ready', function () {
    $leaderToken = 'ready-group-leader';
    $memberToken = 'ready-group-member';
    groupRunner($leaderToken);
    groupRunner($memberToken);
    [$event, $category] = groupEventAndCategory();

    $create = $this->withToken($leaderToken)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload([
            'group_action' => 'create',
            'group_name' => 'Fast Friends',
        ]));

    $this->withToken($memberToken)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload([
            'group_action' => 'join',
            'invitation_code' => strtolower($create->json('invitation_code')),
        ]))
        ->assertCreated()
        ->assertJsonPath('data.registration_group.status', RegistrationGroup::STATUS_READY)
        ->assertJsonPath('data.registration_group.member_count', 2)
        ->assertJsonPath('data.registration_group.can_rotate_code', false)
        ->assertJsonPath('data.registration_group.can_lock', false)
        ->assertJsonPath('data.registration_group.can_remove_members', false)
        ->assertJsonPath('data.registration_group.can_leave', true)
        ->assertJsonPath('data.readiness.steps.group.completed', true);

    $groupId = $create->json('data.registration_group.id');

    $this->withToken($leaderToken)
        ->getJson("/api/registration-groups/{$groupId}")
        ->assertOk()
        ->assertJsonPath('data.can_rotate_code', true)
        ->assertJsonPath('data.can_lock', true)
        ->assertJsonPath('data.can_remove_members', true)
        ->assertJsonPath('data.can_leave', false);
});

test('invalid codes and unverified accounts cannot join groups', function () {
    $leaderToken = 'verification-leader';
    $unverifiedToken = 'unverified-member';
    groupRunner($leaderToken);
    User::factory()->unverified()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', $unverifiedToken),
        'api_token_expires_at' => now()->addDay(),
    ]);
    [$event, $category] = groupEventAndCategory();

    $create = $this->withToken($leaderToken)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload([
            'group_action' => 'create',
            'group_name' => 'Verified Runners',
        ]));

    $this->withToken($unverifiedToken)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload([
            'group_action' => 'join',
            'invitation_code' => $create->json('invitation_code'),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $otherToken = 'bad-code-member';
    groupRunner($otherToken);
    $this->withToken($otherToken)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload([
            'group_action' => 'join',
            'invitation_code' => 'AAAAA-AAAAA',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invitation_code');
});

test('forming groups cannot pay or be approved', function () {
    $token = 'forming-group-leader';
    $memberToken = 'forming-group-member';
    $leader = groupRunner($token);
    groupRunner($memberToken);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    [$event, $category] = groupEventAndCategory(['price_cents' => 10000, 'price_currency' => 'PHP']);
    $event->paymentMethods()->create([
        'provider' => 'GCash',
        'account_name' => 'Racetech',
        'account_number' => '09171234567',
        'is_enabled' => true,
        'sort_order' => 0,
    ]);

    $create = $this->withToken($token)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload([
            'group_action' => 'create',
            'group_name' => 'Incomplete Team',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.registration_group.can_pay', false)
        ->assertJsonPath(
            'data.registration_group.payment_blocked_reason',
            '1 more member must join before payment can begin.'
        );

    $registration = $leader->registrations()->firstOrFail();

    $this->withToken($token)
        ->postJson("/api/registrations/{$registration->id}/payment-proof", [
            'provider_reference' => 'REF-123',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Your group must reach its minimum size before payment can begin.');

    $this->actingAs($admin)
        ->from(route('admin.participants.index'))
        ->patch(route('admin.participants.update', $registration), ['status' => 'approved'])
        ->assertRedirect(route('admin.participants.index'))
        ->assertSessionHas('error');

    expect($registration->fresh()->status)->toBe('pending');

    $this->withToken($memberToken)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload([
            'group_action' => 'join',
            'invitation_code' => $create->json('invitation_code'),
        ]))
        ->assertCreated()
        ->assertJsonPath('data.registration_group.can_pay', true)
        ->assertJsonPath('data.registration_group.payment_blocked_reason', null);

    $this->withToken($token)
        ->getJson('/api/registration-groups/'.$create->json('data.registration_group.id'))
        ->assertOk()
        ->assertJsonPath('data.can_pay', true)
        ->assertJsonPath('data.payment_blocked_reason', null);

    $registration->update([
        'payment_status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->withToken($token)
        ->getJson('/api/registration-groups/'.$create->json('data.registration_group.id'))
        ->assertOk()
        ->assertJsonPath('data.can_pay', false)
        ->assertJsonPath('data.payment_blocked_reason', 'This registration is already paid.');
});

test('only a leader can rotate a code and locking prevents new members', function () {
    $leaderToken = 'rotation-leader';
    $memberToken = 'rotation-member';
    $lateToken = 'rotation-late-member';
    groupRunner($leaderToken);
    groupRunner($memberToken);
    groupRunner($lateToken);
    [$event, $category] = groupEventAndCategory();

    $create = $this->withToken($leaderToken)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload([
            'group_action' => 'create',
            'group_name' => 'Locked Team',
        ]));
    $groupId = $create->json('data.registration_group.id');

    $this->withToken($memberToken)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload([
            'group_action' => 'join',
            'invitation_code' => $create->json('invitation_code'),
        ]))->assertCreated();

    $this->withToken($memberToken)
        ->postJson("/api/registration-groups/{$groupId}/invitation-code")
        ->assertForbidden();

    $this->withToken($leaderToken)
        ->postJson("/api/registration-groups/{$groupId}/lock")
        ->assertOk()
        ->assertJsonPath('data.status', RegistrationGroup::STATUS_LOCKED)
        ->assertJsonPath('data.can_rotate_code', false)
        ->assertJsonPath('data.can_lock', false)
        ->assertJsonPath('data.can_remove_members', false)
        ->assertJsonPath('data.can_leave', false);

    $this->withToken($lateToken)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload([
            'group_action' => 'join',
            'invitation_code' => $create->json('invitation_code'),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invitation_code');
});

test('rotating an invitation code immediately invalidates the previous code', function () {
    $leaderToken = 'code-owner';
    $oldCodeToken = 'old-code-runner';
    $newCodeToken = 'new-code-runner';
    groupRunner($leaderToken);
    groupRunner($oldCodeToken);
    groupRunner($newCodeToken);
    [$event, $category] = groupEventAndCategory();

    $create = $this->withToken($leaderToken)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload([
            'group_action' => 'create',
            'group_name' => 'Rotating Code Team',
        ]));
    $oldCode = $create->json('invitation_code');
    $groupId = $create->json('data.registration_group.id');

    $rotate = $this->withToken($leaderToken)
        ->postJson("/api/registration-groups/{$groupId}/invitation-code")
        ->assertOk();

    expect($rotate->json('invitation_code'))->not->toBe($oldCode);

    $this->withToken($oldCodeToken)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload([
            'group_action' => 'join',
            'invitation_code' => $oldCode,
        ]))
        ->assertUnprocessable();

    $this->withToken($newCodeToken)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload([
            'group_action' => 'join',
            'invitation_code' => $rotate->json('invitation_code'),
        ]))
        ->assertCreated();
});

test('approving a ready group member locks the roster automatically', function () {
    $leaderToken = 'approval-group-leader';
    $memberToken = 'approval-group-member';
    $lateToken = 'approval-group-late';
    $leader = groupRunner($leaderToken);
    groupRunner($memberToken);
    groupRunner($lateToken);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    [$event, $category] = groupEventAndCategory();

    $create = $this->withToken($leaderToken)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload([
            'group_action' => 'create',
            'group_name' => 'Approval Team',
        ]));

    $this->withToken($memberToken)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload([
            'group_action' => 'join',
            'invitation_code' => $create->json('invitation_code'),
        ]))->assertCreated();

    $registration = $leader->registrations()->firstOrFail();
    $this->actingAs($admin)
        ->patch(route('admin.participants.update', $registration), ['status' => 'approved'])
        ->assertSessionHasNoErrors();

    expect($registration->fresh()->status)->toBe('approved')
        ->and($registration->registrationGroup->fresh()->status)->toBe(RegistrationGroup::STATUS_LOCKED)
        ->and($registration->registrationGroup->fresh()->locked_at)->not->toBeNull();

    $this->withToken($lateToken)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", groupRegistrationPayload([
            'group_action' => 'join',
            'invitation_code' => $create->json('invitation_code'),
        ]))
        ->assertUnprocessable();
});
