<?php

use App\Http\Resources\Api\EventResource;
use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

function capacityAvailabilityEvent(array $categoryOverrides = []): array
{
    $date = today()->addMonth();
    $event = Event::forceCreate([
        'review_status' => null,
        'title' => 'Capacity Availability Run',
        'slug' => 'capacity-availability-'.Str::uuid(),
        'description' => 'Synthetic event for registration availability checks.',
        'venue' => 'Synthetic assembly point',
        'event_date' => $date,
        'event_end_date' => $date,
        'start_time' => '06:00',
        'end_time' => '18:00',
        'registration_deadline' => today()->addWeek(),
        'status' => 'upcoming',
        'banner_image' => 'events/banners/synthetic-capacity.jpg',
        'interest_type' => 'Marathon',
    ]);

    $category = $event->categories()->create([
        'name' => '5K Morning',
        'distance_km' => 5,
        'slot_limit' => 1,
        'price_cents' => 0,
        'status' => 'open',
        'scheduled_start_date' => $date,
        'scheduled_start_time' => '06:00',
        'scheduled_end_date' => $date,
        'scheduled_end_time' => '08:00',
        ...$categoryOverrides,
    ]);

    return [$event, $category];
}

function capacityAvailabilityRegistration(Category $category, string $status = 'pending', ?User $user = null): Registration
{
    return Registration::create([
        'user_id' => ($user ?? User::factory()->create(['role' => User::ROLE_RUNNER]))->id,
        'event_id' => $category->event_id,
        'category_id' => $category->id,
        'status' => $status,
        'registered_at' => now(),
    ]);
}

function capacityAvailabilityViewer(array $overrides = []): User
{
    return User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', 'capacity-availability-viewer'),
        'api_token_expires_at' => now()->addDay(),
        ...$overrides,
    ]);
}

dataset('capacity availability endpoints', [
    'guest list' => [false, false],
    'guest detail' => [true, false],
    'participant list' => [false, true],
    'participant detail' => [true, true],
]);

test('full categories stay visible but cannot be registered from mobile list or detail', function (bool $detail, bool $authenticated) {
    [$event, $category] = capacityAvailabilityEvent();
    capacityAvailabilityRegistration($category);
    if ($authenticated) {
        capacityAvailabilityViewer();
        $this->withToken('capacity-availability-viewer');
    }
    $prefix = $detail ? 'data' : 'data.0';

    $this->getJson($detail ? "/api/events/{$event->id}" : '/api/events')
        ->assertOk()
        ->assertJsonPath($prefix.'.id', $event->id)
        ->assertJsonPath($prefix.'.status', 'upcoming')
        ->assertJsonCount(1, $prefix.'.categories')
        ->assertJsonPath($prefix.'.categories.0.registered_count', 1)
        ->assertJsonPath($prefix.'.categories.0.slots_remaining', 0)
        ->assertJsonPath($prefix.'.can_register', false)
        ->assertJsonPath($prefix.'.category_registration_states.0.can_register', false)
        ->assertJsonPath($prefix.'.category_registration_states.0.conflict_reason', 'This category is already full.')
        ->assertJsonPath($prefix.'.category_registration_states.0.conflict_category_id', null);
})->with('capacity availability endpoints');

test('another open category with capacity keeps the event registerable', function (bool $detail, bool $authenticated) {
    [$event, $full] = capacityAvailabilityEvent();
    capacityAvailabilityRegistration($full);
    $available = $event->categories()->create([
        'name' => '5K Afternoon', 'distance_km' => 5, 'slot_limit' => 2,
        'status' => 'open', 'scheduled_start_time' => '12:00', 'scheduled_end_time' => '14:00',
    ]);
    if ($authenticated) {
        capacityAvailabilityViewer();
        $this->withToken('capacity-availability-viewer');
    }
    $prefix = $detail ? 'data' : 'data.0';

    $this->getJson($detail ? "/api/events/{$event->id}" : '/api/events')
        ->assertOk()
        ->assertJsonCount(2, $prefix.'.categories')
        ->assertJsonPath($prefix.'.can_register', true)
        ->assertJsonPath($prefix.'.category_registration_states.0.can_register', false)
        ->assertJsonPath($prefix.'.category_registration_states.1.category_id', $available->id)
        ->assertJsonPath($prefix.'.category_registration_states.1.can_register', true)
        ->assertJsonPath($prefix.'.category_registration_states.1.conflict_reason', null);
})->with('capacity availability endpoints');

test('all full categories make the event unavailable', function () {
    [$event, $first] = capacityAvailabilityEvent();
    $second = $event->categories()->create(['name' => '10K', 'distance_km' => 10,
        'slot_limit' => 1, 'status' => 'open', 'scheduled_start_time' => '12:00', 'scheduled_end_time' => '14:00']);
    capacityAvailabilityRegistration($first);
    capacityAvailabilityRegistration($second);

    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.categories')
        ->assertJsonPath('data.can_register', false)
        ->assertJsonPath('data.category_registration_states.0.can_register', false)
        ->assertJsonPath('data.category_registration_states.1.can_register', false);
});

test('only non rejected registrations consume category capacity', function (string $status, int $remaining, bool $available) {
    [$event, $category] = capacityAvailabilityEvent();
    capacityAvailabilityRegistration($category, $status);

    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.categories.0.registered_count', 1 - $remaining)
        ->assertJsonPath('data.categories.0.slots_remaining', $remaining)
        ->assertJsonPath('data.can_register', $available)
        ->assertJsonPath('data.category_registration_states.0.can_register', $available);
})->with([
    'pending consumes a slot' => ['pending', 0, false],
    'approved consumes a slot' => ['approved', 0, false],
    'checked in consumes a slot' => ['checked_in', 0, false],
    'completed consumes a slot' => ['completed', 0, false],
    'rejected frees a slot' => ['rejected', 1, true],
]);

test('capacity boundaries distinguish available unlimited zero and overbooked categories', function (?int $limit, int $occupants, ?int $remaining, bool $available) {
    [$event, $category] = capacityAvailabilityEvent(['slot_limit' => $limit]);
    for ($index = 0; $index < $occupants; $index++) {
        capacityAvailabilityRegistration($category);
    }

    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.categories.0.slot_limit', $limit)
        ->assertJsonPath('data.categories.0.slots_remaining', $remaining)
        ->assertJsonPath('data.can_register', $available)
        ->assertJsonPath('data.category_registration_states.0.can_register', $available);
})->with([
    'one remaining slot' => [2, 1, 1, true],
    'legacy unlimited capacity' => [null, 2, null, true],
    'legacy zero limit is full' => [0, 0, 0, false],
    'legacy overbooked capacity is full' => [1, 2, 0, false],
]);

test('a closed category with room cannot keep a full event registerable', function () {
    [$event, $full] = capacityAvailabilityEvent();
    capacityAvailabilityRegistration($full);
    $event->categories()->create(['name' => 'Closed 10K', 'distance_km' => 10,
        'slot_limit' => null, 'status' => 'closed']);

    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.categories')
        ->assertJsonPath('data.can_register', false);
});

test('capacity does not replace an existing participants registration status or reason', function (string $status) {
    [$event, $category] = capacityAvailabilityEvent();
    $viewer = capacityAvailabilityViewer();
    $registration = capacityAvailabilityRegistration($category, $status, $viewer);
    $before = $registration->fresh()->getAttributes();

    $this->withToken('capacity-availability-viewer')->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.can_register', false)
        ->assertJsonPath('data.registration_status', $status)
        ->assertJsonPath('data.category_registration_states.0.registration_status', $status)
        ->assertJsonPath('data.category_registration_states.0.is_registered', true)
        ->assertJsonPath('data.category_registration_states.0.can_register', false)
        ->assertJsonPath('data.category_registration_states.0.conflict_reason', 'You are already registered for this category.');

    expect($registration->fresh()->getAttributes())->toBe($before);
})->with(['pending', 'approved', 'checked_in', 'completed']);

test('a full category preserves the existing missing birthdate restriction', function () {
    [$event, $category] = capacityAvailabilityEvent();
    capacityAvailabilityRegistration($category);
    capacityAvailabilityViewer(['birthdate' => null]);
    $reason = 'Add a valid birthdate to your profile before registering for an event.';

    $this->withToken('capacity-availability-viewer')->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.can_register', false)
        ->assertJsonPath('data.registration_block_reason', $reason)
        ->assertJsonPath('data.category_registration_states.0.conflict_reason', $reason);
});

test('event availability still requires personal eligibility even when another category has room', function () {
    [$event, $joined] = capacityAvailabilityEvent();
    $viewer = capacityAvailabilityViewer();
    capacityAvailabilityRegistration($joined, 'approved', $viewer);
    $compatibleFull = $event->categories()->create(['name' => 'Full Afternoon', 'distance_km' => 10,
        'slot_limit' => 1, 'status' => 'open', 'scheduled_start_time' => '12:00', 'scheduled_end_time' => '14:00']);
    capacityAvailabilityRegistration($compatibleFull);
    $conflicting = $event->categories()->create(['name' => 'Available Overlap', 'distance_km' => 5,
        'slot_limit' => 5, 'status' => 'open', 'scheduled_start_time' => '07:00', 'scheduled_end_time' => '09:00']);

    $this->withToken('capacity-availability-viewer')->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.can_register', false)
        ->assertJsonPath('data.category_registration_states.1.can_register', false)
        ->assertJsonPath('data.category_registration_states.1.conflict_reason', 'This category is already full.')
        ->assertJsonPath('data.category_registration_states.2.category_id', $conflicting->id)
        ->assertJsonPath('data.category_registration_states.2.can_register', false)
        ->assertJsonPath('data.category_registration_states.2.conflict_reason', 'This category conflicts with 5K Morning. A 30-minute gap is required between categories.')
        ->assertJsonPath('data.category_registration_states.2.conflict_category_id', $joined->id);
});

test('event capacity is checked when its categories were not eager loaded', function (?int $limit, bool $available) {
    [$event, $category] = capacityAvailabilityEvent(['slot_limit' => $limit]);
    capacityAvailabilityRegistration($category);
    $model = $event->fresh();
    $payload = (new EventResource($model))->resolve(Request::create('/api/events', 'GET'));

    expect($payload['can_register'])->toBe($available)
        ->and($payload)->not->toHaveKey('categories')
        ->and($model->relationLoaded('categories'))->toBeFalse();
})->with(['full' => [1, false], 'room' => [2, true], 'unlimited' => [null, true]]);

test('loaded categories without a count do not default to having capacity', function () {
    [$event, $category] = capacityAvailabilityEvent();
    capacityAvailabilityRegistration($category);
    $model = $event->fresh()->load('categories');
    $payload = (new EventResource($model))->resolve(Request::create('/api/events', 'GET'));

    expect($payload['can_register'])->toBeFalse()
        ->and($payload['category_registration_states'][0]['can_register'])->toBeFalse();
});

test('the registration endpoint still rejects a category filled after an availability check', function () {
    [$event, $category] = capacityAvailabilityEvent();
    capacityAvailabilityViewer();
    $this->withToken('capacity-availability-viewer')->getJson("/api/events/{$event->id}")
        ->assertOk()->assertJsonPath('data.can_register', true);
    capacityAvailabilityRegistration($category);

    $this->postJson("/api/events/{$event->id}/register/{$category->id}", [
        'shirt_size' => 'M', 'waiver_accepted' => true, 'first_aid_kit_confirmed' => true,
    ])->assertUnprocessable()->assertJsonPath('message', 'This category is already full.');

    expect($category->registrations()->count())->toBe(1);
});
