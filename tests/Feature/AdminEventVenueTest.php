<?php

use App\Http\Resources\Api\EventResource;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\Request;

beforeEach(function () {
    config(['venues.locations_path' => base_path('tests/Fixtures/venue-locations.json')]);
});

function venueEventPayload(array $overrides = []): array
{
    static $sequence = 0;
    $sequence++;
    return array_replace([
        'title' => 'Specific Venue Test '.uniqid(),
        'interest_type' => 'Marathon',
        'event_date' => now()->addDays(30 + $sequence)->toDateString(),
        'start_time' => '06:00',
        'venue_mode' => 'new',
        'venue_name' => 'Assembly Hall',
        'venue_address' => 'Main Street entrance',
        'venue_area_code' => '0402100000',
        'venue_city_code' => '0402103000',
        'venue_barangay_code' => '0402103001',
    ], $overrides);
}

test('new event venues use canonical names from local reference data and remain readable in the mobile API', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($admin)->post(route('admin.events.store'), venueEventPayload([
        'venue' => 'Forged short venue', 'venue_area_name' => 'Forged province',
    ]))->assertRedirect()->assertSessionHasNoErrors();
    $event = Event::latest('id')->firstOrFail();
    $venue = Venue::firstOrFail();
    expect($event->venue_id)->toBe($venue->id)
        ->and($event->venue)->toBe('Assembly Hall — Main Street entrance, Test Barangay A, Test City A, Cavite')
        ->and($venue->area_name)->toBe('Cavite')
        ->and($venue->reference_version)->toBe('synthetic-test-fixture')
        ->and((new EventResource($event))->toArray(Request::create('/'))['venue'])->toBe($event->venue);
    $this->actingAs($admin)->get(route('admin.events.edit', $event))->assertOk()
        ->assertSee('data-venue-mode value="keep"', false)->assertSee($venue->label)
        ->assertSee('value="Assembly Hall, Main Street entrance"', false);
});

test('the venue form shows the four address fields directly without a saved venue dropdown', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($admin)->get(route('admin.events.create'))->assertOk()
        ->assertSee('data-venue-mode value="new"', false)
        ->assertSee('data-venue-new', false)
        ->assertSee('Venue / Landmark')
        ->assertSee('name="venue_area_code"', false)
        ->assertSee('name="venue_city_code"', false)
        ->assertSee('name="venue_barangay_code"', false)
        ->assertDontSee('data-venue-choice', false)
        ->assertDontSee('+ Add new venue')
        ->assertDontSee('Select a venue')
        ->assertDontSee('name="venue_address"', false)
        ->assertDontSee('data-venue-search', false)
        ->assertDontSee('No map, location permission, or paid search API is used.');
});

test('a combined meeting point and landmark saves without a separate street address and can be reused', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $payload = venueEventPayload(['venue_name' => '  Municipal Hall entrance,   Mabini Street  ']);
    unset($payload['venue_address']);
    $this->actingAs($admin)->post(route('admin.events.store'), $payload)
        ->assertRedirect()->assertSessionHasNoErrors();
    $venue = Venue::firstOrFail();
    $expected = 'Municipal Hall entrance, Mabini Street — Test Barangay A, Test City A, Cavite';
    expect($venue->name)->toBe('Municipal Hall entrance, Mabini Street')
        ->and($venue->address)->toBe('')
        ->and($venue->label)->toBe($expected)
        ->and(Event::firstOrFail()->venue)->toBe($expected);
    $payload['title'] .= ' Another event';
    $payload['event_date'] = \Illuminate\Support\Carbon::parse($payload['event_date'])->addDays(100)->toDateString();
    $this->actingAs($admin)->post(route('admin.events.store'), $payload)->assertSessionHasNoErrors();
    expect(Venue::count())->toBe(1);
    $this->actingAs($admin)->post(route('admin.events.store'), venueEventPayload(['venue_mode' => 'saved:'.$venue->id]))
        ->assertSessionHasNoErrors();
    expect(Event::where('venue_id', $venue->id)->count())->toBe(3);
});

test('validation errors restore the compact new venue fields and selected location codes', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $payload = venueEventPayload(['title' => '', 'venue_name' => 'School gym, Gate 2']);
    unset($payload['venue_address']);
    $this->actingAs($admin)->from(route('admin.events.create'))
        ->post(route('admin.events.store'), $payload)->assertRedirect(route('admin.events.create'))
        ->assertSessionHasErrors('title');
    $this->get(route('admin.events.create'))->assertOk()
        ->assertSee('data-venue-mode value="new"', false)
        ->assertSee('value="School gym, Gate 2"', false)
        ->assertSee('data-area="0402100000" data-city="0402103000" data-barangay="0402103001"', false);
    expect(Venue::count())->toBe(0)->and(Event::count())->toBe(0);
});

test('saving the same address twice reuses a saved venue without changing prior events', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($admin)->post(route('admin.events.store'), venueEventPayload())->assertSessionHasNoErrors();
    $first = Event::firstOrFail();
    $this->actingAs($admin)->post(route('admin.events.store'), venueEventPayload())->assertSessionHasNoErrors();
    expect(Venue::count())->toBe(1)->and(Event::count())->toBe(2);
    $venue = Venue::firstOrFail();
    $this->actingAs($admin)->post(route('admin.events.store'), venueEventPayload([
        'venue_mode' => 'saved:'.$venue->id, 'venue' => 'Forged replacement',
    ]))->assertSessionHasNoErrors();
    expect(Event::latest('id')->first()->venue)->toBe($venue->label)
        ->and($first->fresh()->venue)->toBe($venue->label);
});

test('mismatched unknown or missing address selections are rejected', function (array $overrides, string $error) {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($admin)->post(route('admin.events.store'), venueEventPayload($overrides))
        ->assertSessionHasErrors($error);
    expect(Venue::count())->toBe(0)->and(Event::count())->toBe(0);
})->with([
    [['venue_area_code' => '9999999999'], 'venue_area_code'],
    [['venue_city_code' => '0401029000'], 'venue_city_code'],
    [['venue_barangay_code' => '0401029001'], 'venue_barangay_code'],
    [['venue_barangay_code' => ''], 'venue_barangay_code'],
    [['venue_name' => ''], 'venue_name'],
    [['venue_name' => str_repeat('n', 101)], 'venue_name'],
    [['venue_address' => ['invalid']], 'venue_address'],
    [['venue_mode' => ''], 'venue_mode'],
    [['venue_mode' => 'saved:999999'], 'venue_id'],
    [['venue_mode' => 'keep'], 'venue_mode'],
    [['venue_name' => str_repeat('n', 100), 'venue_address' => str_repeat('a', 150)], 'venue_address'],
]);

test('other event validation errors do not leave orphan saved venues', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($admin)->post(route('admin.events.store'), venueEventPayload(['title' => '']))
        ->assertSessionHasErrors('title');
    expect(Venue::count())->toBe(0)->and(Event::count())->toBe(0);
});

test('legacy venue text is preserved until deliberately replaced with a structured address', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $legacy = Event::forceCreate(['review_status' => null, 'manager_id' => $admin->id, 'title' => 'Legacy Event', 'slug' => 'legacy-venue', 'venue' => 'Old assembly text', 'status' => 'draft', 'event_date' => now()->addMonth()->toDateString()]);
    $this->actingAs($admin)->get(route('admin.events.edit', $legacy))->assertOk()
        ->assertSee('Old assembly text')->assertSee('data-venue-mode value="keep"', false);
    $this->actingAs($admin)->put(route('admin.events.update', $legacy), venueEventPayload([
        'venue_mode' => 'keep', 'venue' => 'Must not overwrite old text',
    ]))->assertSessionHasNoErrors();
    expect($legacy->fresh()->venue)->toBe('Old assembly text')->and(Venue::count())->toBe(0);
    $this->actingAs($admin)->put(route('admin.events.update', $legacy), venueEventPayload())->assertSessionHasNoErrors();
    expect($legacy->fresh()->venue_id)->not->toBeNull()->and(Venue::count())->toBe(1);
});

test('NCR addresses do not require a fictional province', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($admin)->post(route('admin.events.store'), venueEventPayload([
        'venue_area_code' => '1300000000', 'venue_city_code' => '1381300000', 'venue_barangay_code' => '1381300001',
    ]))->assertSessionHasNoErrors();
    expect(Venue::firstOrFail()->area_name)->toBe('Metro Manila (NCR)');
});

test('venue reference requests are authenticated role restricted and validate parent codes', function () {
    $this->getJson(route('admin.venue-locations', ['level' => 'areas']))->assertUnauthorized();
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $this->actingAs($runner)->getJson(route('admin.venue-locations', ['level' => 'areas']))->assertForbidden();
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($admin)->getJson(route('admin.venue-locations', ['level' => 'cities', 'parent' => '0402100000']))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Test City A');
    $this->actingAs($admin)->getJson(route('admin.venue-locations', ['level' => 'barangays', 'parent' => '0401029000']))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', '0401029001');
    $this->actingAs($admin)->getJson(route('admin.venue-locations', ['level' => 'cities']))->assertUnprocessable();
    $this->actingAs($admin)->getJson(route('admin.venue-locations', ['level' => 'unknown']))->assertUnprocessable();
});

test('event managers cannot view or select another managers private venue or event', function () {
    $first = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $other = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($first)->post(route('admin.events.store'), venueEventPayload())->assertSessionHasNoErrors();
    $venue = Venue::firstOrFail();
    $event = Event::firstOrFail();
    $this->actingAs($other)->get(route('admin.events.create'))->assertOk()->assertDontSee($venue->label);
    $this->actingAs($other)->post(route('admin.events.store'), venueEventPayload(['venue_mode' => 'saved:'.$venue->id]))
        ->assertSessionHasErrors('venue_id');
    $this->actingAs($other)->put(route('admin.events.update', $event), venueEventPayload())->assertForbidden();
    expect(Venue::count())->toBe(1);
});

test('an assigned manager can reuse the venues of their assigned events', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $manager = $admin;
    $this->actingAs($admin)->post(route('admin.events.store'), venueEventPayload(['manager_id' => $manager->id]))->assertSessionHasNoErrors();
    $venue = Venue::firstOrFail();
    $this->actingAs($manager)->get(route('admin.events.create'))->assertOk()->assertDontSee($venue->label);
    $this->actingAs($manager)->get(route('admin.events.edit', Event::firstOrFail()))->assertOk()
        ->assertSee($venue->label)->assertSee('data-venue-mode value="keep"', false);
    $this->actingAs($manager)->post(route('admin.events.store'), venueEventPayload(['venue_mode' => 'saved:'.$venue->id, 'event_date' => now()->addMonths(2)->toDateString()]))->assertSessionHasNoErrors();
    expect(Event::where('venue_id', $venue->id)->count())->toBe(2);
});

test('keeping a structured venue preserves its exact label and snapshot even when the reference is unavailable', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($admin)->post(route('admin.events.store'), venueEventPayload())->assertSessionHasNoErrors();
    $event = Event::firstOrFail();
    $venue = Venue::firstOrFail();
    $original = $venue->getAttributes();
    config(['venues.locations_path' => storage_path('missing-test-venue-reference.json')]);
    $this->get(route('admin.events.edit', $event))->assertOk()
        ->assertSee('data-venue-mode value="keep"', false)->assertSee($venue->label);
    $this->put(route('admin.events.update', $event), venueEventPayload([
        'venue_mode' => 'keep', 'venue_name' => 'Ignored replacement',
        'venue_area_code' => '', 'venue_city_code' => '', 'venue_barangay_code' => '',
    ]))->assertSessionHasNoErrors();
    expect($event->fresh()->venue)->toBe($venue->label)
        ->and($event->fresh()->venue_id)->toBe($venue->id)
        ->and($venue->fresh()->getAttributes())->toBe($original)
        ->and(Venue::count())->toBe(1);
});

test('deliberately changing an event venue creates a snapshot without changing other events', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($admin)->post(route('admin.events.store'), venueEventPayload())->assertSessionHasNoErrors();
    $first = Event::firstOrFail();
    $this->post(route('admin.events.store'), venueEventPayload())->assertSessionHasNoErrors();
    $second = Event::latest('id')->firstOrFail();
    $previous = Venue::firstOrFail();
    $payload = venueEventPayload(['venue_name' => 'New park entrance']);
    unset($payload['venue_address']);
    $this->put(route('admin.events.update', $first), $payload)->assertSessionHasNoErrors();
    expect($first->fresh()->venue_id)->not->toBe($previous->id)
        ->and($first->fresh()->venue)->toBe('New park entrance — Test Barangay A, Test City A, Cavite')
        ->and($second->fresh()->venue_id)->toBe($previous->id)
        ->and($second->fresh()->venue)->toBe($previous->label)
        ->and($previous->fresh()->label)->toBe($previous->label);
});

test('a missing reference file fails safely while existing venue text can still be preserved', function () {
    config(['venues.locations_path' => storage_path('missing-test-venue-reference.json')]);
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($admin)->get(route('admin.events.create'))->assertOk()->assertSee('needs to be installed');
    $this->actingAs($admin)->getJson(route('admin.venue-locations', ['level' => 'areas']))->assertStatus(503);
});
