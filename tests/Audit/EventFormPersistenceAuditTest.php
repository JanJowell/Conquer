<?php

use App\Models\Event;
use App\Models\User;
use App\Services\EventReviewWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function auditEventPayload(string $type, bool $miles = false): array
{
    $details = [];
    foreach (config('conquer.event_type_details.'.$type) as $key => $definition) {
        if ($definition['category_owned'] ?? false) {
            continue;
        }
        $details[$key] = match ($definition['type']) {
            'select' => end($definition['options']), 'boolean' => '0', 'number' => '123.45',
            'duration' => '__custom_duration__', 'textarea' => 'Audit '.$key." & details\nSecond line",
            default => 'Audit '.$key.' & details',
        };
        if ($definition['type'] === 'duration') {
            $details[$key.'_hours'] = '9';
            $details[$key.'_minutes'] = '30';
        }
    }
    $categories = [];
    foreach ([false, true] as $index => $group) {
        $categoryDetails = [];
        foreach (config('conquer.event_category_type_details.'.$type, []) as $key => $definition) {
            $categoryDetails[$key] = match ($definition['type']) {
                'select' => end($definition['options']), 'number' => $key === 'swim_distance_m' ? '750' : '12.5',
                default => 'Water & gear for category '.$index,
            };
        }
        $categories[] = [
            'category_type' => $group ? 'group' : 'custom', 'custom_category_name' => $group ? '' : 'Route A & Local',
            'distance_option' => 'custom', 'custom_distance_km' => $group ? ($miles ? '10.01' : '4.25') : '3.75',
            'distance_unit' => $group && $miles ? 'mi' : 'km', 'type_details' => $categoryDetails,
            'scheduled_start_date' => today()->addDays(60 + $index)->toDateString(), 'scheduled_start_time' => '06:15',
            'scheduled_end_date' => today()->addDays(61)->toDateString(), 'scheduled_end_time' => '08:30',
            'participation_mode' => $group ? 'group' : 'individual', 'group_min_members' => $group ? '2' : null,
            'group_max_members' => $group ? '6' : null, 'requires_medical_certificate' => $group ? '1' : '0',
            'slot_limit' => '120', 'price_amount' => $group ? '1250.75' : '0.00', 'price_currency' => 'PHP',
            'status' => $group ? 'closed' : 'open', 'description' => 'Category description '.$index,
            'qualification_notes' => 'Eligibility notes '.$index,
            'checkpoint_map_image_upload' => UploadedFile::fake()->image('category-'.$index.'.png'),
            'course_map' => json_encode(['version' => 1, 'path' => [['lat' => 14.01, 'lng' => 121.01], ['lat' => 14.02, 'lng' => 121.02]],
                'checkpoints' => [['lat' => 14.01, 'lng' => 121.01, 'label' => 'Start & Finish']]]),
        ];
    }

    return ['title' => 'Audit '.$type, 'description' => "Event details & notes\nSecond line", 'interest_type' => $type,
        'venue_mode' => 'new', 'venue_name' => 'School gym, Gate 2', 'venue_area_code' => '0402100000',
        'venue_city_code' => '0402103000', 'venue_barangay_code' => '0402103001',
        'event_date' => today()->addDays(60)->toDateString(), 'event_end_date' => today()->addDays(61)->toDateString(),
        'registration_deadline' => today()->addDays(45)->toDateString(), 'start_time' => '05:45', 'end_time' => '17:45',
        'banner_image_upload' => UploadedFile::fake()->image('banner.png'), 'type_details' => [$type => $details],
        'payment_methods_submitted' => '1', 'payment_methods' => [
            ['provider' => 'GCash', 'account_name' => 'Audit GCash', 'account_number' => '0012345', 'instructions' => 'Reference & receipt', 'is_enabled' => '1'],
            ['provider' => 'Maya', 'account_name' => 'Audit Maya', 'account_number' => '0054321', 'instructions' => 'Disabled option', 'is_enabled' => '0'],
            ['provider' => 'PayMongo', 'is_enabled' => '1'],
        ], 'categories' => $categories];
}

function auditEventSnapshot(Event $event): array
{
    $snapshot = app(EventReviewWorkflow::class)->snapshot($event);
    // Payment rows are intentionally recreated; compare values, not private row IDs.
    $snapshot['paymentMethods'] = array_values($snapshot['paymentMethods']);

    return $snapshot;
}

function auditBrowserForm(string $html): array
{
    $temporaryHtml = tempnam(sys_get_temp_dir(), 'racetech-form-audit-');
    try {
        file_put_contents($temporaryHtml, $html);
        $process = new Process(['node', base_path('tests/Audit/serialize-event-form.mjs'), $temporaryHtml], base_path());
        $process->setTimeout(55);
        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    } finally {
        if (is_file($temporaryHtml)) {
            unlink($temporaryHtml);
        }
    }
}

test('created details survive loading the real edit form and saving its browser-generated payload', function (string $type) {
    config(['venues.locations_path' => base_path('tests/Fixtures/venue-locations.json')]);
    Storage::fake('public');
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $payload = auditEventPayload($type);
    $this->actingAs($manager)->post(route('admin.events.store'), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();
    $event = Event::sole();
    $before = auditEventSnapshot($event);
    expect($event->manager_id)->toBe($manager->id)->and($event->venue_id)->not->toBeNull()
        ->and($event->categories()->count())->toBe(2)->and($event->paymentMethods()->count())->toBe(3);
    Storage::disk('public')->assertExists($event->banner_image);
    foreach ($event->categories as $category) {
        Storage::disk('public')->assertExists($category->checkpoint_map_image);
        expect($category->course_map['path'])->toHaveCount(2);
    }
    foreach (config('conquer.event_type_details.'.$type) as $key => $definition) {
        if ($definition['category_owned'] ?? false) {
            continue;
        }
        $expected = $definition['type'] === 'duration' ? '9 hours 30 minutes'
            : ($definition['type'] === 'boolean' ? false : $payload['type_details'][$type][$key]);
        expect($event->type_details[$key])->toBe($expected);
    }

    $edit = $this->get(route('admin.events.edit', $event))->assertOk();
    $browser = auditBrowserForm($edit->getContent());
    expect($browser['invalid'])->toBe([]);
    parse_str($browser['encoded'], $update);
    expect($update['venue_mode'])->toBe('keep');
    $this->put(route('admin.events.update', $event), $update)->assertSessionHasNoErrors()->assertRedirect();
    expect(auditEventSnapshot($event))->toBe($before);
    Storage::disk('public')->assertExists($event->fresh()->banner_image);
    foreach ($event->fresh()->categories as $category) {
        Storage::disk('public')->assertExists($category->checkpoint_map_image);
    }
})->with(['Cycling', 'Duathlon', 'Hiking', 'Marathon', 'Trail Run', 'Triathlon']);

test('a saved custom miles category reloads with a browser-valid distance', function (string $type) {
    config(['venues.locations_path' => base_path('tests/Fixtures/venue-locations.json')]);
    Storage::fake('public');
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($manager)->post(route('admin.events.store'), auditEventPayload($type, true))
        ->assertSessionHasNoErrors();
    $event = Event::sole();
    $before = auditEventSnapshot($event);
    $edit = $this->get(route('admin.events.edit', $event))->assertOk();
    $browser = auditBrowserForm($edit->getContent());
    expect($browser['invalidDetails'])->toBe([]);
    parse_str($browser['encoded'], $update);
    $this->put(route('admin.events.update', $event), $update)->assertSessionHasNoErrors()->assertRedirect();
    expect(auditEventSnapshot($event))->toBe($before);
})->with(['Cycling', 'Hiking', 'Marathon', 'Trail Run']);

test('optional trail difficulty remains optional when the saved category is edited', function () {
    config(['venues.locations_path' => base_path('tests/Fixtures/venue-locations.json')]);
    Storage::fake('public');
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $payload = auditEventPayload('Trail Run');
    $payload['categories'][0]['type_details']['trail_difficulty'] = null;
    $this->actingAs($manager)->post(route('admin.events.store'), $payload)->assertSessionHasNoErrors();
    $event = Event::sole();
    $before = auditEventSnapshot($event);
    $browser = auditBrowserForm($this->get(route('admin.events.edit', $event))->assertOk()->getContent());
    expect($browser['invalidDetails'])->toBe([]);
    parse_str($browser['encoded'], $update);
    $this->put(route('admin.events.update', $event), $update)->assertSessionHasNoErrors()->assertRedirect();
    expect(auditEventSnapshot($event))->toBe($before);
});

test('standard distance presets remain selected when editing', function (string $distance) {
    config(['venues.locations_path' => base_path('tests/Fixtures/venue-locations.json')]);
    Storage::fake('public');
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $payload = auditEventPayload('Marathon');
    $payload['categories'][0]['distance_option'] = $distance;
    $payload['categories'][0]['custom_distance_km'] = '';
    $this->actingAs($manager)->post(route('admin.events.store'), $payload)->assertSessionHasNoErrors();
    $event = Event::sole();
    $before = auditEventSnapshot($event);
    $edit = $this->get(route('admin.events.edit', $event))->assertOk();
    $browser = auditBrowserForm($edit->getContent());
    parse_str($browser['encoded'], $form);
    $category = $event->categories()->where('distance_km', $distance)->firstOrFail();
    $row = collect($form['categories'])->firstWhere('id', (string) $category->id);
    expect($row['distance_option'])->toBe($distance);
    expect($browser['invalidDetails'])->toBe([]);
    $this->put(route('admin.events.update', $event), $form)->assertSessionHasNoErrors()->assertRedirect();
    expect(auditEventSnapshot($event))->toBe($before);
})->with(['1', '3', '5', '10', '21', '42']);

test('reviewed revisions copy every saved event category payment and map setup value', function (string $type) {
    config(['venues.locations_path' => base_path('tests/Fixtures/venue-locations.json')]);
    Storage::fake('public');
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($manager)->post(route('admin.events.store'), auditEventPayload($type))->assertSessionHasNoErrors();
    $event = Event::sole();
    $workflow = app(EventReviewWorkflow::class);
    $event = $workflow->transition($event, $manager, 'submit', 0);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = $workflow->transition($event, $admin, 'approve', 1);
    $draft = $workflow->startRevision($event, $manager, 2);
    $original = $workflow->snapshot($event);
    $copy = $workflow->snapshot($draft);
    foreach (['categories', 'paymentMethods', 'checkpoints'] as $relation) {
        $original[$relation] = array_values($original[$relation]);
        $copy[$relation] = array_values($copy[$relation]);
    }
    expect($copy)->toBe($original)->and($event->fresh()->effective_status)->toBe('upcoming');
})->with(['Cycling', 'Duathlon', 'Hiking', 'Marathon', 'Trail Run', 'Triathlon']);
