<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\EventReview;
use App\Models\SavedPaymentOption;
use App\Models\User;
use App\Models\Venue;
use App\Services\EventReviewWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['venues.locations_path' => base_path('tests/Fixtures/venue-locations.json')]);
    Storage::fake('public');
});

function directReviewPayload(array $overrides = []): array
{
    $date = today()->addMonth()->toDateString();

    return array_replace([
        'intent' => 'submit', 'title' => 'Synthetic direct review', 'description' => 'Assembly details',
        'interest_type' => 'Marathon', 'type_details' => ['Marathon' => ['cutoff_time' => '6 hours']],
        'venue_mode' => 'new', 'venue_name' => 'School gym, Gate 2', 'venue_area_code' => '0402100000',
        'venue_city_code' => '0402103000', 'venue_barangay_code' => '0402103001',
        'event_date' => $date, 'event_end_date' => $date, 'start_time' => '06:00', 'end_time' => '18:00',
        'registration_deadline' => today()->addWeek()->toDateString(),
        'banner_image_upload' => UploadedFile::fake()->image('synthetic-banner.png'),
        'payment_selection_submitted' => '1', 'payment_choices' => [],
        'categories' => [[
            'category_type' => 'open', 'distance_option' => '5', 'distance_unit' => 'km',
            'scheduled_start_date' => $date, 'scheduled_end_date' => $date,
            'scheduled_start_time' => '06:00', 'scheduled_end_time' => '12:00',
            'price_amount' => '0.00', 'price_currency' => 'PHP', 'status' => 'open',
            'slot_limit' => 50,
            'course_map' => ['version' => 1, 'path' => [['lat' => 14.1, 'lng' => 121.1], ['lat' => 14.2, 'lng' => 121.2]], 'checkpoints' => []],
        ]],
    ], $overrides);
}

function directReviewManager(): User
{
    return User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
}

function directReviewDraft($test, User $manager): Event
{
    $test->actingAs($manager)->post(route('admin.events.store'), directReviewPayload(['intent' => 'draft']))
        ->assertRedirect()->assertSessionHasNoErrors();

    return Event::sole();
}

function directReviewEditPayload(Event $event): array
{
    $payload = directReviewPayload(['review_version' => $event->review_version, 'venue_mode' => 'keep']);
    $payload['categories'][0]['id'] = $event->categories()->sole()->id;
    unset($payload['banner_image_upload']);

    return $payload;
}

test('the primary action creates one private pending review with a complete immutable submission audit', function () {
    $manager = directReviewManager();
    $this->actingAs($manager)->post(route('admin.events.store'), directReviewPayload())
        ->assertRedirect()->assertSessionHasNoErrors();
    $event = Event::sole();
    expect($event->review_status)->toBe('pending_review')->and($event->review_version)->toBe(1)
        ->and($event->approved_at)->toBeNull()->and($event->manager_id)->toBe($manager->id)
        ->and($event->effective_status)->toBe('draft')->and($event->reviews()->count())->toBe(1)
        ->and($event->categories()->count())->toBe(1)->and(Venue::count())->toBe(1)
        ->and($event->reviews()->sole()->action)->toBe('submit')
        ->and($event->submitted_setup_hash)->toBe(app(EventReviewWorkflow::class)->setupHash($event));
    Storage::disk('public')->assertExists($event->banner_image);
    expect($event->reviews()->sole()->submission_snapshot['setup'])->toBe(app(EventReviewWorkflow::class)->snapshot($event));
    $this->getJson('/api/events/'.$event->id)->assertNotFound();
    $this->get(route('admin.events.edit', $event))->assertForbidden();
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->actingAs($admin)->get(route('admin.events.show', $event))->assertOk()->assertSee('Approve &amp; Publish', false);
    $this->post(route('admin.events.review', [$event, 'approve']), ['review_version' => 1])->assertSessionHasNoErrors();
    expect($event->fresh()->review_status)->toBe('approved')->and($event->fresh()->effective_status)->toBe('upcoming');
    $this->getJson('/api/events/'.$event->id)->assertOk();
});

test('direct submission accepts the required setup for every supported event type without optional extras', function (string $type) {
    $details = [];
    foreach (Event::typeDetailSchema($type) as $key => $definition) {
        if ($definition['category_owned'] ?? false) {
            continue;
        }
        if (! ($definition['required_for_publication'] ?? false) && ! in_array('required', $definition['rules'] ?? [], true)) {
            continue;
        }
        $details[$key] = match ($definition['type']) {
            'select' => $definition['options'][0],
            'boolean' => '0',
            'duration' => '6 hours',
            'number' => 10,
            default => 'Synthetic event details',
        };
    }
    $payload = directReviewPayload(['interest_type' => $type, 'type_details' => [$type => $details]]);
    foreach (config('conquer.event_category_type_details.'.$type, []) as $key => $definition) {
        if (! ($definition['required_for_publication'] ?? false) && ! in_array('required', $definition['rules'] ?? [], true)) {
            continue;
        }
        $payload['categories'][0]['type_details'][$key] = ($definition['type'] ?? 'number') === 'number'
            ? ($key === 'swim_distance_m' ? 750 : 5) : 'Synthetic water and required gear';
    }
    $this->actingAs(directReviewManager())->post(route('admin.events.store'), $payload)->assertSessionHasNoErrors();
    expect(Event::sole()->review_status)->toBe('pending_review')->and(Event::sole()->end_time->format('H:i'))->toBe('18:00');
})->with(['Cycling', 'Hiking', 'Marathon', 'Trail Run', 'Triathlon', 'Duathlon']);

test('save draft and older clients stay private without requiring submission-only fields', function (bool $legacy) {
    $payload = directReviewPayload(['intent' => 'draft']);
    foreach (['description', 'banner_image_upload', 'registration_deadline', 'start_time', 'end_time', 'type_details', 'categories'] as $key) {
        unset($payload[$key]);
    }
    if ($legacy) {
        unset($payload['intent']);
    }
    $this->actingAs(directReviewManager())->post(route('admin.events.store'), $payload)->assertSessionHasNoErrors();
    $event = Event::sole();
    expect($event->review_status)->toBe('draft')->and($event->review_version)->toBe(0)
        ->and($event->reviews()->count())->toBe(0)->and($event->submitted_at)->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with([false, true]);

test('missing submission fields are rejected without saving setup or uploads and input is retained', function (string $key) {
    $payload = directReviewPayload();
    Arr::forget($payload, $key);
    $this->actingAs(directReviewManager())->from(route('admin.events.create'))
        ->post(route('admin.events.store'), $payload)->assertRedirect(route('admin.events.create'))
        ->assertSessionHasErrors($key)->assertSessionHasInput($key === 'title' ? 'description' : 'title',
            $key === 'title' ? 'Assembly details' : 'Synthetic direct review');
    expect(Event::count())->toBe(0)->and(Category::count())->toBe(0)->and(Venue::count())->toBe(0)
        ->and(EventReview::count())->toBe(0)->and(Storage::disk('public')->allFiles())->toBe([]);
})->with(['title', 'description', 'interest_type', 'venue_name', 'venue_area_code', 'venue_city_code',
    'venue_barangay_code', 'event_date', 'event_end_date', 'start_time', 'end_time', 'registration_deadline',
    'banner_image_upload', 'categories', 'categories.0.slot_limit', 'categories.0.course_map', 'type_details.Marathon.cutoff_time']);

test('conditional category and date requirements cannot be bypassed on direct submission', function (array $changes, string $error) {
    $payload = directReviewPayload();
    foreach ($changes as $key => $value) {
        Arr::set($payload, $key, $value);
    }
    $this->actingAs(directReviewManager())->post(route('admin.events.store'), $payload)->assertSessionHasErrors($error);
    expect(Event::count())->toBe(0)->and(Venue::count())->toBe(0)->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    [['categories.0.status' => 'closed'], 'categories'],
    [['categories.0.category_type' => 'group'], 'categories.0.group_min_members'],
    [['categories.0.category_type' => 'group', 'categories.0.group_min_members' => 5, 'categories.0.group_max_members' => 2], 'categories.0.group_max_members'],
    [['categories.0.category_type' => 'custom'], 'categories.0.custom_category_name'],
    [['categories.0.distance_option' => 'custom'], 'categories.0.custom_distance_km'],
    [['categories.0.scheduled_end_time' => '05:00'], 'categories.0.scheduled_end_time'],
    [['event_date' => ['not-a-date']], 'event_date'],
    [['interest_type' => ['Marathon']], 'interest_type'],
    [['registration_deadline' => today()->subDay()->toDateString()], 'registration_deadline'],
    [['interest_type' => 'Hiking', 'type_details' => ['Hiking' => ['difficulty' => 'Easy', 'estimated_duration' => '4 hours']], 'categories.0.type_details' => []], 'categories.0.type_details.required_gear'],
    [['interest_type' => 'Triathlon', 'type_details' => ['Triathlon' => ['swim_type' => 'Pool', 'transition_details' => 'Gate', 'cutoff_time' => '6 hours']], 'categories.0.type_details' => ['bike_distance_km' => 20, 'run_distance_km' => 5]], 'categories.0.type_details.swim_distance_m'],
]);

test('invalid intent and a forged banner path do not bypass required upload or publication review', function (string $variant) {
    $payload = directReviewPayload();
    if ($variant === 'banner') {
        unset($payload['banner_image_upload']);
        $payload['banner_image'] = 'events/banners/not-an-upload.png';
    } else {
        $payload['intent'] = 'approve';
    }
    $payload['review_status'] = 'approved';
    $payload['approved_by'] = 1;
    $payload['status'] = 'upcoming';
    $this->actingAs(directReviewManager())->post(route('admin.events.store'), $payload)
        ->assertSessionHasErrors($variant === 'banner' ? 'banner_image_upload' : 'intent');
    expect(Event::count())->toBe(0)->and(Storage::disk('public')->allFiles())->toBe([]);
})->with(['intent', 'banner']);

test('paid submission without an enabled option rolls back every new record and image', function () {
    $payload = directReviewPayload();
    $payload['categories'][0]['price_amount'] = '500';
    $payload['categories'][0]['checkpoint_map_image_upload'] = UploadedFile::fake()->image('synthetic-map.png');
    $this->actingAs(directReviewManager())->post(route('admin.events.store'), $payload)->assertSessionHasErrors('payment_choices');
    expect(Event::count())->toBe(0)->and(Category::count())->toBe(0)->and(Venue::count())->toBe(0)
        ->and(EventReview::count())->toBe(0)->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('paid submission uses an available versioned library option without changing the library account', function () {
    $manager = directReviewManager();
    $option = SavedPaymentOption::create(['created_by' => $manager->id, 'is_shared' => false, 'label' => 'Synthetic GCash',
        'provider' => 'GCash', 'account_name' => 'Synthetic holder', 'account_number' => '09170000000',
        'is_enabled' => true, 'version' => 1]);
    $before = $option->fresh()->getAttributes();
    $payload = directReviewPayload(['payment_choices' => [$option->selectionToken()]]);
    $payload['categories'][0]['price_amount'] = '500';
    $this->actingAs($manager)->post(route('admin.events.store'), $payload)->assertSessionHasNoErrors();
    $event = Event::sole();
    expect($event->review_status)->toBe('pending_review')->and($event->paymentMethods()->sole()->account_number)->toBe('09170000000')
        ->and($option->fresh()->getAttributes())->toBe($before);
});

test('a late review failure cleans only new uploads and rolls back the draft venue categories and audit', function () {
    Storage::disk('public')->put('events/banners/existing.png', 'preserve existing banner');
    Storage::disk('public')->put('categories/checkpoint-maps/existing.png', 'preserve shared map');
    $before = Storage::disk('public')->allFiles();
    $this->mock(EventReviewWorkflow::class)->shouldReceive('transition')->once()
        ->andThrow(ValidationException::withMessages(['review' => 'Synthetic late readiness failure.']));
    $payload = directReviewPayload();
    $payload['categories'][0]['checkpoint_map_image_upload'] = UploadedFile::fake()->image('synthetic-map.png');
    $this->actingAs(directReviewManager())->post(route('admin.events.store'), $payload)->assertSessionHasErrors('review');
    expect(Event::count())->toBe(0)->and(Category::count())->toBe(0)->and(Venue::count())->toBe(0)
        ->and(EventReview::count())->toBe(0)->and(Storage::disk('public')->allFiles())->toBe($before);
});

test('editing can submit a saved banner without reuploading and uses the updated setup in the audit', function () {
    $manager = directReviewManager();
    $event = directReviewDraft($this, $manager);
    $banner = $event->banner_image;
    $payload = directReviewEditPayload($event);
    $payload['title'] = 'Synthetic updated submission';
    $payload['banner_image'] = 'events/banners/forged-replacement.png';
    $this->put(route('admin.events.update', $event), $payload)->assertSessionHasNoErrors();
    expect($event->fresh()->review_status)->toBe('pending_review')->and($event->fresh()->banner_image)->toBe($banner)
        ->and($event->reviews()->sole()->submission_snapshot['setup']['event']['title'])->toBe('Synthetic updated submission');
    Storage::disk('public')->assertExists($banner);
});

test('failed edit submission restores old setup and preserves old files while removing new uploads', function () {
    $manager = directReviewManager();
    $event = directReviewDraft($this, $manager);
    $before = app(EventReviewWorkflow::class)->snapshot($event);
    $files = Storage::disk('public')->allFiles();
    $payload = directReviewEditPayload($event);
    $payload['title'] = 'Do not retain this failed change';
    $payload['banner_image_upload'] = UploadedFile::fake()->image('new-banner.png');
    $extra = $payload['categories'][0];
    unset($extra['id']);
    $extra['price_amount'] = '500';
    $extra['checkpoint_map_image_upload'] = UploadedFile::fake()->image('new-map.png');
    $payload['categories'][] = $extra;
    $this->put(route('admin.events.update', $event), $payload)->assertSessionHasErrors('payment_choices');
    expect(app(EventReviewWorkflow::class)->snapshot($event->fresh()))->toBe($before)
        ->and($event->fresh()->review_status)->toBe('draft')->and($event->fresh()->review_version)->toBe(0)
        ->and($event->reviews()->count())->toBe(0)->and(Storage::disk('public')->allFiles())->toBe($files);
});

test('stale edit versions cannot save or submit a different review state', function () {
    $manager = directReviewManager();
    $event = directReviewDraft($this, $manager);
    $before = app(EventReviewWorkflow::class)->snapshot($event);
    $payload = directReviewEditPayload($event);
    $payload['review_version'] = 99;
    $payload['title'] = 'Stale change';
    $this->put(route('admin.events.update', $event), $payload)->assertSessionHasErrors('review_version');
    expect(app(EventReviewWorkflow::class)->snapshot($event->fresh()))->toBe($before)->and($event->reviews()->count())->toBe(0);
});

test('editing requires the current review version when submitting', function () {
    $manager = directReviewManager();
    $event = directReviewDraft($this, $manager);
    $payload = directReviewEditPayload($event);
    unset($payload['review_version']);
    $this->put(route('admin.events.update', $event), $payload)->assertSessionHasErrors('review_version');
    expect($event->fresh()->review_status)->toBe('draft')->and($event->reviews()->count())->toBe(0);
});

test('an event that already started today cannot be submitted', function () {
    $this->travelTo(today()->setTime(10, 0));
    $date = today()->toDateString();
    $payload = directReviewPayload(['event_date' => $date, 'event_end_date' => $date, 'registration_deadline' => $date]);
    $payload['categories'][0]['scheduled_start_date'] = $date;
    $payload['categories'][0]['scheduled_end_date'] = $date;
    $this->actingAs(directReviewManager())->post(route('admin.events.store'), $payload)->assertSessionHasErrors('start_time');
    expect(Event::count())->toBe(0)->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('failed submission displays all current field errors and restores entered text and category selections', function () {
    $payload = directReviewPayload();
    unset($payload['description'], $payload['registration_deadline'], $payload['banner_image_upload']);
    $this->actingAs(directReviewManager())->from(route('admin.events.create'))->post(route('admin.events.store'), $payload)
        ->assertRedirect(route('admin.events.create'))->assertSessionHasErrors(['description', 'registration_deadline', 'banner_image_upload']);
    // Carry the session cookie as a browser does, including JSON-serialized errors.
    $this->withCookie(config('session.cookie'), session()->getId());
    $response = $this->get(route('admin.events.create'))->assertOk();
    expect(app('view')->getShared()['errors']->all())->toHaveCount(3);
    $response->assertSee('Please correct the following fields.')
        ->assertSee('data-event-error-field="description"', false)
        ->assertSee('data-event-error-field="registration_deadline"', false)
        ->assertSee('data-event-error-field="banner_image_upload"', false)
        ->assertSee('value="Synthetic direct review"', false)->assertSee('name="categories[0][distance_option]"', false)
        ->assertSee('choose any new image files again');
    expect(Event::count())->toBe(0);
});

test('neither another manager nor an admin can use the form submission action to change an owned draft', function (string $role) {
    $manager = directReviewManager();
    $event = directReviewDraft($this, $manager);
    $before = app(EventReviewWorkflow::class)->snapshot($event);
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->put(route('admin.events.update', $event), directReviewEditPayload($event))->assertForbidden();
    expect(app(EventReviewWorkflow::class)->snapshot($event->fresh()))->toBe($before)
        ->and($event->reviews()->count())->toBe(0);
})->with([User::ROLE_EVENT_MANAGER, User::ROLE_SUPER_ADMIN]);

test('returned or withdrawn drafts can resubmit directly without replacing their prior review history', function (string $action) {
    $manager = directReviewManager();
    $event = directReviewDraft($this, $manager);
    $workflow = app(EventReviewWorkflow::class);
    $event = $workflow->transition($event, $manager, 'submit', 0);
    $actor = $action === 'return' ? User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]) : $manager;
    $event = $workflow->transition($event, $actor, $action, 1, 'Clarify assembly details.');
    $original = $event->reviews()->where('action', 'submit')->sole()->submission_snapshot;
    $this->actingAs($manager)->put(route('admin.events.update', $event), directReviewEditPayload($event))->assertSessionHasNoErrors();
    expect($event->fresh()->review_status)->toBe('pending_review')->and($event->fresh()->review_version)->toBe(3)
        ->and($event->reviews()->count())->toBe(3)->and($event->reviews()->where('action', 'submit')->orderBy('id')->first()->submission_snapshot)->toBe($original);
})->with(['return', 'withdraw']);

test('direct revision submission preserves the current published event and does not publish its edits', function () {
    $manager = directReviewManager();
    $live = directReviewDraft($this, $manager);
    $workflow = app(EventReviewWorkflow::class);
    $workflow->transition($live, $manager, 'submit', 0);
    $workflow->transition($live, User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]), 'approve', 1);
    $before = $workflow->snapshot($live);
    $revision = $workflow->startRevision($live->fresh(), $manager, 2);
    $payload = directReviewEditPayload($revision);
    $payload['title'] = 'Private proposed revision';
    $this->actingAs($manager)->put(route('admin.events.update', $revision), $payload)->assertSessionHasNoErrors();
    expect($revision->fresh()->review_status)->toBe('pending_review')->and($live->fresh()->review_status)->toBe('approved')
        ->and($workflow->snapshot($live))->toBe($before);
    $this->getJson('/api/events/'.$revision->id)->assertNotFound();
});
