<?php

use App\Models\Event;
use App\Models\EventRevision;
use App\Models\User;
use App\Services\EventReviewWorkflow;
use App\Services\EventSubmissionRequirements;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('public');
});

function requiredSetupCourse(): array
{
    return ['version' => 1, 'path' => [['lat' => 14.1, 'lng' => 121.1], ['lat' => 14.2, 'lng' => 121.2]], 'checkpoints' => []];
}

function requiredSetupEvent(User $manager, array $overrides = []): Event
{
    $event = Event::forceCreate(array_replace([
        'title' => 'Synthetic required setup', 'slug' => 'required-setup-'.uniqid(),
        'description' => 'Assembly details', 'venue' => 'Synthetic assembly point',
        'interest_type' => 'Marathon', 'type_details' => ['cutoff_time' => '6 hours'],
        'event_date' => today()->addMonth(), 'event_end_date' => today()->addMonth(),
        'start_time' => '06:00', 'end_time' => '18:00', 'registration_deadline' => today()->addWeek(),
        'banner_image' => 'events/banners/required-setup.png', 'manager_id' => $manager->id,
        'review_status' => 'draft', 'status' => 'draft',
    ], $overrides));
    $event->categories()->create([
        'name' => '5K Open', 'distance_km' => 5, 'distance_unit' => 'km', 'status' => 'open',
        'price_cents' => 0, 'price_currency' => 'PHP', 'slot_limit' => 50,
        'scheduled_start_date' => $event->event_date, 'scheduled_end_date' => $event->event_date,
        'scheduled_start_time' => '06:00', 'scheduled_end_time' => '12:00', 'course_map' => requiredSetupCourse(),
    ]);

    return $event;
}

function requiredSetupEdit(Event $event): array
{
    $category = $event->categories()->orderBy('id')->firstOrFail();

    return [
        'intent' => 'submit', 'review_version' => $event->review_version,
        'title' => $event->title, 'description' => $event->description, 'interest_type' => 'Marathon',
        'type_details' => ['Marathon' => ['cutoff_time' => '6 hours']], 'venue_mode' => 'keep',
        'event_date' => $event->event_date->toDateString(), 'event_end_date' => $event->event_end_date->toDateString(),
        'start_time' => $event->start_time?->format('H:i'), 'end_time' => $event->end_time?->format('H:i'),
        'registration_deadline' => $event->registration_deadline->toDateString(),
        'payment_selection_submitted' => '1', 'payment_choices' => [],
        'categories' => [[
            'id' => $category->id, 'category_type' => 'open', 'distance_option' => '5', 'distance_unit' => 'km',
            'scheduled_start_date' => $category->scheduled_start_date->toDateString(),
            'scheduled_end_date' => $category->scheduled_end_date->toDateString(),
            'scheduled_start_time' => $category->scheduled_start_time->format('H:i'),
            'scheduled_end_time' => $category->scheduled_end_time->format('H:i'),
            'price_amount' => '0.00', 'price_currency' => 'PHP', 'status' => 'open',
            'slot_limit' => $category->slot_limit, 'course_map' => $category->course_map,
        ]],
    ];
}

test('standalone submission enforces new requirements without changing the saved draft', function (string $missing, string $message) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = requiredSetupEvent($manager);
    match ($missing) {
        'end' => $event->update(['end_time' => null]),
        'slots' => $event->categories()->sole()->update(['slot_limit' => null]),
        'map' => $event->categories()->sole()->update(['course_map' => null]),
    };
    $before = app(EventReviewWorkflow::class)->snapshot($event);
    $this->actingAs($manager)->post(route('admin.events.review', [$event, 'submit']), ['review_version' => 0])
        ->assertSessionHasErrors('review');
    expect(session('errors')->first('review'))->toContain($message);
    expect(app(EventReviewWorkflow::class)->snapshot($event->fresh()))->toBe($before)
        ->and($event->fresh()->review_status)->toBe('draft')->and($event->fresh()->review_version)->toBe(0)
        ->and($event->reviews()->count())->toBe(0);
})->with([['end', 'set an event end time'], ['slots', 'set a positive slot limit'], ['map', 'upload a map image or save a valid drawn route']]);

test('each map alternative and both together satisfy submission including an existing category upload', function (string $alternative) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = requiredSetupEvent($manager);
    $category = $event->categories()->sole();
    $image = 'categories/checkpoint-maps/saved-alternative.png';
    if ($alternative === 'saved image') {
        $category->update(['checkpoint_map_image' => $image, 'course_map' => null]);
    }
    $payload = requiredSetupEdit($event);
    if (in_array($alternative, ['upload', 'both'], true)) {
        $payload['categories'][0]['checkpoint_map_image_upload'] = UploadedFile::fake()->image('alternative.png');
        if ($alternative === 'upload') {
            $payload['categories'][0]['course_map'] = null;
        }
    }
    $this->actingAs($manager)->put(route('admin.events.update', $event), $payload)->assertSessionHasNoErrors();
    expect($event->fresh()->review_status)->toBe('pending_review')->and($category->fresh()->slot_limit)->toBe(50);
    if (in_array($alternative, ['upload', 'both'], true)) {
        Storage::disk('public')->assertExists($category->fresh()->checkpoint_map_image);
    }
    if ($alternative === 'saved image') {
        expect($category->fresh()->checkpoint_map_image)->toBe($image)->and($category->fresh()->course_map)->toBeNull();
    }
    if (in_array($alternative, ['drawn', 'both'], true)) {
        expect($category->fresh()->course_map)->toBe(requiredSetupCourse());
    }
})->with(['upload', 'saved image', 'drawn', 'both']);

test('save draft still permits the three incomplete submission requirements', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = requiredSetupEvent($manager);
    $payload = requiredSetupEdit($event);
    $payload['intent'] = 'draft';
    $payload['end_time'] = null;
    $payload['categories'][0]['slot_limit'] = null;
    $payload['categories'][0]['course_map'] = null;
    $this->actingAs($manager)->put(route('admin.events.update', $event), $payload)->assertSessionHasNoErrors();
    expect($event->fresh()->end_time)->toBeNull()->and($event->fresh()->review_status)->toBe('draft')
        ->and($event->categories()->sole()->slot_limit)->toBeNull()->and($event->categories()->sole()->course_map)->toBeNull()
        ->and($event->reviews()->count())->toBe(0);
});

test('slot limits must be positive whole numbers on form submission', function (mixed $limit) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = requiredSetupEvent($manager);
    $payload = requiredSetupEdit($event);
    $payload['categories'][0]['slot_limit'] = $limit;
    $this->actingAs($manager)->put(route('admin.events.update', $event), $payload)->assertSessionHasErrors('categories.0.slot_limit');
    expect($event->fresh()->review_status)->toBe('draft')->and($event->categories()->sole()->slot_limit)->toBe(50);
})->with([null, 0, -1, 2.5, 'not a number']);

test('invalid saved drawn maps cannot satisfy standalone submission', function (mixed $map) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = requiredSetupEvent($manager);
    $event->categories()->sole()->update(['course_map' => $map]);
    $this->actingAs($manager)->post(route('admin.events.review', [$event, 'submit']), ['review_version' => 0])
        ->assertSessionHasErrors('review');
    expect(session('errors')->first('review'))->toContain('upload a map image or save a valid drawn route')
        ->and($event->fresh()->review_status)->toBe('draft');
})->with([
    'empty' => [null], 'empty array' => [[]], 'encoded null' => ['null'],
    'one point' => [['version' => 1, 'path' => [['lat' => 14.1, 'lng' => 121.1]], 'checkpoints' => []]],
    'identical points' => [['version' => 1, 'path' => [['lat' => 14.1, 'lng' => 121.1], ['lat' => 14.1, 'lng' => 121.1]], 'checkpoints' => []]],
    'invalid coordinates' => [['version' => 1, 'path' => [['lat' => 999, 'lng' => 121.1], ['lat' => 14.2, 'lng' => 121.2]], 'checkpoints' => []]],
]);

test('a forged image path and a category owned by another event cannot satisfy the map requirement', function (bool $foreign) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = requiredSetupEvent($manager);
    $event->categories()->sole()->update(['course_map' => null]);
    $payload = requiredSetupEdit($event);
    $payload['categories'][0]['checkpoint_map_image'] = 'categories/checkpoint-maps/forged.png';
    $error = 'categories.0.course_map';
    if ($foreign) {
        $other = requiredSetupEvent(User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]));
        $other->categories()->sole()->update(['checkpoint_map_image' => 'categories/checkpoint-maps/other.png']);
        $payload['categories'][0]['id'] = $other->categories()->sole()->id;
        $error = 'categories.0.id';
    }
    $this->actingAs($manager)->put(route('admin.events.update', $event), $payload)->assertSessionHasErrors($error);
    expect($event->fresh()->review_status)->toBe('draft')->and($event->categories()->sole()->checkpoint_map_image)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([false, true]);

test('omitting a saved route preserves it but explicitly clearing it requires an image alternative', function (bool $clear) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = requiredSetupEvent($manager);
    $payload = requiredSetupEdit($event);
    unset($payload['categories'][0]['course_map']);
    if ($clear) {
        $payload['categories'][0]['course_map'] = '';
    }
    $response = $this->actingAs($manager)->put(route('admin.events.update', $event), $payload);
    if ($clear) {
        $response->assertSessionHasErrors('categories.0.course_map');
        expect($event->fresh()->review_status)->toBe('draft');
    } else {
        $response->assertSessionHasNoErrors();
        expect($event->fresh()->review_status)->toBe('pending_review');
    }
    expect($event->categories()->sole()->course_map)->toBe(requiredSetupCourse());
})->with([false, true]);

test('omitted persisted categories still need maps and slots even when not open and late failure rolls back uploads', function (string $status) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = requiredSetupEvent($manager);
    $event->categories()->create(['name' => 'Omitted category', 'distance_km' => 10, 'status' => $status]);
    $before = app(EventReviewWorkflow::class)->snapshot($event);
    $payload = requiredSetupEdit($event);
    $payload['title'] = 'Should roll back';
    $payload['banner_image_upload'] = UploadedFile::fake()->image('rollback-banner.png');
    $payload['categories'][0]['checkpoint_map_image_upload'] = UploadedFile::fake()->image('rollback-existing-map.png');
    $this->actingAs($manager)->put(route('admin.events.update', $event), $payload)->assertSessionHasErrors('review');
    expect(session('errors')->first('review'))->toContain('set a positive slot limit for Omitted category', 'save a valid drawn route for Omitted category');
    expect(app(EventReviewWorkflow::class)->snapshot($event->fresh()))->toBe($before)
        ->and($event->fresh()->review_status)->toBe('draft')->and(Storage::disk('public')->allFiles())->toBe([]);
})->with(['closed', 'draft']);

test('existing image replacement retains old files and rolls back the new file after a late review failure', function (bool $fail) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = requiredSetupEvent($manager);
    $category = $event->categories()->sole();
    $old = 'categories/checkpoint-maps/keep-old.png';
    Storage::disk('public')->put($old, 'old shared image');
    $category->update(['checkpoint_map_image' => $old, 'course_map' => null]);
    $payload = requiredSetupEdit($event);
    $payload['categories'][0]['checkpoint_map_image_upload'] = UploadedFile::fake()->image('new-existing-image.png');
    if ($fail) {
        $this->mock(EventReviewWorkflow::class)->shouldReceive('transition')->once()
            ->andThrow(ValidationException::withMessages(['review' => 'Synthetic late validation.']));
    }
    $response = $this->actingAs($manager)->put(route('admin.events.update', $event), $payload);
    if ($fail) {
        $response->assertSessionHasErrors('review');
        expect($category->fresh()->checkpoint_map_image)->toBe($old)
            ->and(Storage::disk('public')->allFiles())->toBe([$old])->and($event->fresh()->review_status)->toBe('draft');
    } else {
        $response->assertSessionHasNoErrors();
        expect($category->fresh()->checkpoint_map_image)->not->toBe($old);
        Storage::disk('public')->assertExists($category->fresh()->checkpoint_map_image);
    }
    Storage::disk('public')->assertExists($old);
})->with([false, true]);

test('overnight event end time is compared with its date rather than its clock time', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = requiredSetupEvent($manager);
    $payload = requiredSetupEdit($event);
    $nextDate = $event->event_date->copy()->addDay()->toDateString();
    $payload['start_time'] = '22:00';
    $payload['event_end_date'] = $nextDate;
    $payload['end_time'] = '04:00';
    $payload['categories'][0]['scheduled_start_time'] = '22:30';
    $payload['categories'][0]['scheduled_end_date'] = $nextDate;
    $payload['categories'][0]['scheduled_end_time'] = '03:00';
    $this->actingAs($manager)->put(route('admin.events.update', $event), $payload)->assertSessionHasNoErrors();
    expect($event->fresh()->review_status)->toBe('pending_review')->and($event->fresh()->end_time->format('H:i'))->toBe('04:00')
        ->and($event->fresh()->event_end_date->toDateString())->toBe($nextDate);
});

test('standalone submission also validates end ordering and category schedule bounds', function (string $invalid) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = requiredSetupEvent($manager);
    match ($invalid) {
        'same' => $event->update(['end_time' => '06:00']),
        'earlier' => $event->update(['end_time' => '05:00']),
        'outside' => $event->categories()->sole()->update(['scheduled_end_time' => '19:00']),
    };
    $this->actingAs($manager)->post(route('admin.events.review', [$event, 'submit']), ['review_version' => 0])->assertSessionHasErrors('review');
    expect(session('errors')->first('review'))->toContain($invalid === 'outside' ? 'within the event start and end' : 'after its start')
        ->and($event->fresh()->review_status)->toBe('draft');
})->with(['same', 'earlier', 'outside']);

test('already published approved and legacy events remain publicly visible without newly required setup', function (?string $reviewStatus) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = requiredSetupEvent($manager, ['review_status' => $reviewStatus, 'status' => 'upcoming', 'end_time' => null]);
    $event->categories()->sole()->update(['slot_limit' => null, 'course_map' => null]);
    $event->refreshAutomaticStatus();
    $before = app(EventReviewWorkflow::class)->snapshot($event);
    expect($event->fresh()->isReviewPublished())->toBeTrue()->and($event->fresh()->effective_status)->toBe('upcoming')
        ->and($event->publicReadinessErrors())->toBe([])->and(app(EventSubmissionRequirements::class)->errors($event))->not->toBeEmpty();
    $this->getJson('/api/events')->assertOk()->assertJsonFragment(['title' => $event->title]);
    $this->getJson('/api/events/'.$event->id)->assertOk()->assertJsonPath('data.title', $event->title);
    expect(app(EventReviewWorkflow::class)->snapshot($event->fresh()))->toBe($before);
})->with(['approved', null]);

test('a revision of a grandfathered public event must complete new requirements and leaves the public setup unchanged', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $live = requiredSetupEvent($manager, ['review_status' => 'approved', 'end_time' => null]);
    $live->categories()->sole()->update(['slot_limit' => null, 'course_map' => null]);
    $workflow = app(EventReviewWorkflow::class);
    $before = $workflow->snapshot($live);
    $revision = $workflow->startRevision($live, $manager, 0);
    $this->actingAs($manager)->post(route('admin.events.review', [$revision, 'submit']), ['review_version' => 0])->assertSessionHasErrors('review');
    expect($revision->fresh()->review_status)->toBe('draft')->and($workflow->snapshot($live->fresh()))->toBe($before)
        ->and(EventRevision::sole()->applied_at)->toBeNull();
    $this->getJson('/api/events/'.$live->id)->assertOk();
    $this->getJson('/api/events/'.$revision->id)->assertNotFound();
});

test('approval revalidates historical pending submissions without publishing incomplete setup', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = requiredSetupEvent($manager, ['review_status' => 'pending_review', 'review_version' => 1, 'end_time' => null]);
    $event->categories()->sole()->update(['slot_limit' => null, 'course_map' => null]);
    $event->forceFill(['submitted_setup_hash' => app(EventReviewWorkflow::class)->setupHash($event)])->save();
    $this->actingAs($admin)->post(route('admin.events.review', [$event, 'approve']), ['review_version' => 1])->assertSessionHasErrors('review');
    expect(session('errors')->first('review'))->toContain('set an event end time', 'set a positive slot limit', 'save a valid drawn route');
    expect($event->fresh()->review_status)->toBe('pending_review')->and($event->fresh()->approved_at)->toBeNull()
        ->and($event->fresh()->review_version)->toBe(1)->and($event->reviews()->count())->toBe(0);
    $this->getJson('/api/events/'.$event->id)->assertNotFound();
});
