<?php

use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Services\EventReviewWorkflow;
use App\Services\EventRevisionComparison;

function submittedSetupFixture(): array
{
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER, 'name' => 'Synthetic Event Manager']);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = Event::forceCreate([
        'title' => 'Synthetic review race', 'slug' => 'submitted-summary-'.uniqid(), 'description' => "Assembly instructions\nBring water.",
        'venue' => 'Municipal Hall — Kapitangan, Paombong, Bulacan', 'event_date' => today()->addMonth(),
        'event_end_date' => today()->addMonth(), 'registration_deadline' => today()->addWeek(),
        'start_time' => '06:00', 'end_time' => '12:00', 'interest_type' => 'Marathon',
        'type_details' => ['cutoff_time' => '9 hours 30 minutes'], 'banner_image' => 'events/banners/synthetic.jpg',
        'manager_id' => $manager->id, 'review_status' => 'draft', 'status' => 'draft',
    ]);
    $event->categories()->create(['name' => '1K Open', 'distance_km' => 1, 'distance_unit' => 'km', 'description' => 'Synthetic category details',
        'qualification_notes' => 'Bring the required document.', 'participation_mode' => 'individual',
        'requires_medical_certificate' => true, 'status' => 'open', 'slot_limit' => 25,
        'price_cents' => 95000, 'price_currency' => 'PHP', 'scheduled_start_date' => $event->event_date,
        'scheduled_end_date' => $event->event_date, 'scheduled_start_time' => '06:00', 'scheduled_end_time' => '12:00',
        'course_map' => ['version' => 1, 'path' => [['lat' => 14, 'lng' => 121], ['lat' => 14.1, 'lng' => 121.1]],
            'checkpoints' => [['lat' => 14, 'lng' => 121, 'label' => 'Start']]],
    ]);
    $event->paymentMethods()->create(['provider' => 'GCash', 'account_name' => 'Synthetic holder', 'account_number' => '09170000000',
        'instructions' => "Use the event reference.\nKeep your receipt.", 'is_enabled' => true, 'sort_order' => 1]);
    $event->checkpoints()->create(['name' => 'Water station', 'location' => 'Park gate', 'latitude' => 14, 'longitude' => 121,
        'type' => 'hydration', 'order' => 1, 'description' => 'Water is provided.']);
    $workflow = app(EventReviewWorkflow::class);
    $event = $workflow->transition($event, $manager, 'submit', 0);

    return [$event, $manager, $admin, $workflow];
}

function submittedSetupDocument(string $html): DOMXPath
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    return new DOMXPath($document);
}

test('pending first submissions render labeled readable setup instead of JSON for manager and admin', function () {
    [$event, $manager, $admin, $workflow] = submittedSetupFixture();
    $snapshot = $workflow->snapshot($event);
    $saved = $event->reviews()->where('action', 'submit')->sole()->submission_snapshot;
    foreach ([$manager, $admin] as $actor) {
        $response = $this->actingAs($actor)->get(route('admin.events.show', $event))->assertOk();
        $xpath = submittedSetupDocument($response->getContent());
        $root = '//div[@data-submitted-setup]';
        expect($xpath->query($root)->length)->toBe(1)
            ->and($xpath->query($root.'//pre')->length)->toBe(0)
            ->and($xpath->query($root.'//input | '.$root.'//button | '.$root.'//form')->length)->toBe(0);
        $text = $xpath->query($root)->item(0)->textContent;
        expect($text)->toContain('Event details', 'Categories', 'Payment options', 'Checkpoints', 'Event name',
            'Synthetic review race', 'Municipal Hall — Kapitangan, Paombong, Bulacan', 'Cut-off Duration',
            '9 hours 30 minutes', 'Synthetic Event Manager', 'Category: 1K Open', 'PHP 950.00',
            'Yes', 'No', '09170000000', 'Use the event reference.', 'Checkpoint: Water station',
            '2 route points; 1 checkpoint', 'View route coordinates', '6:00:00 AM',
            today()->addMonth()->format('M j, Y'))
            ->not->toContain('"event_date":', '"type_details":', '"paymentMethods":', '\u2014', '\"cutoff_time\"');
        expect($xpath->query($root.'//details[@data-submitted-item and not(@open)]')->length)->toBe(3);
        if ($actor->isSuperAdmin()) {
            $response->assertSee('Approve &amp; Publish', false)->assertSee('Return for Changes')->assertDontSee('Withdraw to Edit');
        } else {
            $response->assertSee('Withdraw to Edit')->assertDontSee('Approve &amp; Publish', false);
        }
    }
    expect($workflow->snapshot($event))->toBe($snapshot)
        ->and($event->fresh()->review_version)->toBe(1)
        ->and($event->reviews()->count())->toBe(1)
        ->and($event->reviews()->where('action', 'submit')->sole()->submission_snapshot)->toBe($saved);
});

test('summary formatting preserves every supplied field value including zeros disabled options and route coordinates', function () {
    $manager = User::factory()->create(['name' => 'Synthetic Manager']);
    $snapshot = ['event' => ['title' => 'Preserved title', 'manager_id' => $manager->id, 'description' => null,
        'event_date' => '2026-11-04', 'start_time' => '00:00:00', 'payment_setup_needs_review' => 0,
        'type_details' => '{"cutoff_time":"2 hours"}', 'interest_type' => 'Marathon'],
        'categories' => [29 => ['name' => 'Free group', 'distance_km' => '1.00', 'distance_unit' => 'km',
            'price_cents' => 0, 'price_currency' => 'PHP', 'requires_medical_certificate' => 0,
            'participation_mode' => 'group', 'group_min_members' => 2, 'group_max_members' => 4,
            'qualification_notes' => 'Recorded qualification', 'status' => 'closed', 'slot_limit' => null,
            'course_map' => '{"version":1,"path":[{"lat":14,"lng":121},{"lat":14.2,"lng":121.2}],"checkpoints":[]}']],
        'paymentMethods' => [8 => ['provider' => 'Bank', 'account_name' => 'Bank holder', 'account_number' => '000123', 'is_enabled' => 0, 'instructions' => 'Use Synthetic Bank']],
        'checkpoints' => [4 => ['name' => 'Finish', 'latitude' => '0.00000000', 'longitude' => '0.00000000', 'type' => 'finish', 'order' => 0]]];
    $before = $snapshot;
    $groups = app(EventRevisionComparison::class)->submittedSetup($snapshot);
    $event = collect($groups[0]['items'][0]['rows'])->keyBy('field');
    $category = collect($groups[1]['items'][0]['rows'])->keyBy('field');
    $payment = collect($groups[2]['items'][0]['rows'])->keyBy('field');
    $checkpoint = collect($groups[3]['items'][0]['rows'])->keyBy('field');
    expect($event['Description']['value']['text'])->toBe('Not set')
        ->and($event['Event Date']['value']['text'])->toBe('Nov 4, 2026')
        ->and($event['Start Time']['value']['text'])->toBe('12:00:00 AM')
        ->and($event['Payment setup needs review']['value']['text'])->toBe('No')
        ->and($event['Cut-off Duration']['value']['text'])->toBe('2 hours')
        ->and($category['Registration fee']['value']['text'])->toBe('PHP 0.00')
        ->and($category['Participation Mode']['value']['text'])->toBe('Group')
        ->and($category['Registration status']['value']['text'])->toBe('Closed')
        ->and($category['Drawn course map']['value']['details'])->toContain('Route point / 2 / Latitude: 14.2')
        ->and($payment['Account Number']['value']['text'])->toBe('000123')
        ->and($payment['Enabled']['value']['text'])->toBe('No')
        ->and($checkpoint['Latitude']['value']['text'])->toBe('0')
        ->and($snapshot)->toBe($before);
});

test('event and category specific details use their schemas without changing snapshot values', function (string $type, array $eventDetails, array $categoryDetails, string $eventLabel, string $eventText, string $categoryLabel, string $categoryText) {
    $snapshot = ['event' => ['interest_type' => $type, 'type_details' => json_encode($eventDetails)],
        'categories' => [1 => ['name' => 'Synthetic category', 'type_details' => json_encode($categoryDetails)]], 'paymentMethods' => [], 'checkpoints' => []];
    $groups = app(EventRevisionComparison::class)->submittedSetup($snapshot);
    $eventRows = collect($groups[0]['items'][0]['rows'])->keyBy('field');
    $categoryRows = collect($groups[1]['items'][0]['rows'])->keyBy('field');
    expect($eventRows[$eventLabel]['value']['text'])->toBe($eventText)
        ->and($categoryRows[$categoryLabel]['value']['text'])->toBe($categoryText);
})->with([
    ['Cycling', ['helmet_required' => false], ['custom_note' => 'Recorded note'], 'Helmet Required', 'No', 'Custom Note', 'Recorded note'],
    ['Hiking', ['elevation_gain_m' => 850], ['required_gear' => "Water\nHeadlamp"], 'Elevation Gain', '850 m', 'Required Gear', "Water\nHeadlamp"],
    ['Trail Run', ['cutoff_time' => '3 hours'], ['mandatory_gear' => 'Whistle'], 'Cut-off Duration', '3 hours', 'Mandatory Gear', 'Whistle'],
    ['Triathlon', ['swim_type' => 'Open Water'], ['swim_distance_m' => 750], 'Swim Type', 'Open Water', 'Swim Distance', '750 m'],
    ['Duathlon', ['transition_details' => 'Rack at gate'], ['first_run_distance_km' => 5], 'Transition Details', 'Rack at gate', 'First-run Distance', '5 km'],
]);

test('readable summary escapes user supplied labels account details and route content', function () {
    $unsafe = '<script>alert("unsafe")</script>';
    $snapshot = ['event' => ['title' => $unsafe, 'interest_type' => 'Marathon', 'type_details' => json_encode(['cutoff_time' => $unsafe])],
        'categories' => [1 => ['name' => $unsafe, 'qualification_notes' => $unsafe,
            'course_map' => json_encode(['path' => [], 'checkpoints' => [['label' => $unsafe]]])]],
        'paymentMethods' => [1 => ['provider' => 'GCash', 'account_name' => $unsafe, 'instructions' => $unsafe]],
        'checkpoints' => [1 => ['name' => $unsafe, 'description' => $unsafe]]];
    $html = view('admin.events.partials.submitted-setup', ['submittedSetup' => app(EventRevisionComparison::class)->submittedSetup($snapshot)])->render();
    expect($html)->toContain('&lt;script&gt;')->not->toContain($unsafe, '<pre');
    expect(submittedSetupDocument($html)->query('//script')->length)->toBe(0);
});

test('empty setup sections and missing related names have readable fallbacks', function () {
    $snapshot = ['event' => ['title' => 'Legacy submission', 'manager_id' => 999999, 'venue_id' => 999999],
        'categories' => [], 'paymentMethods' => [], 'checkpoints' => []];
    $groups = app(EventRevisionComparison::class)->submittedSetup($snapshot);
    $html = view('admin.events.partials.submitted-setup', ['submittedSetup' => $groups])->render();
    expect($html)->toContain('Former manager', 'Unavailable saved venue', 'No categories included.',
        'No payment options included.', 'No checkpoints included.')->not->toContain('999999');
});

test('summary resolves related names without exposing numeric IDs', function () {
    $manager = User::factory()->create(['name' => 'Named manager']);
    $venue = Venue::create(['label' => 'Saved assembly point', 'name' => 'Assembly point', 'address' => 'Synthetic street',
        'fingerprint' => hash('sha256', 'synthetic-summary-venue'), 'area_code' => '0300000000', 'area_name' => 'Synthetic area',
        'city_code' => '0300100000', 'city_name' => 'Synthetic city', 'barangay_code' => '0300100001',
        'barangay_name' => 'Synthetic barangay', 'reference_version' => 'synthetic']);
    $groups = app(EventRevisionComparison::class)->submittedSetup(['event' => ['manager_id' => $manager->id, 'venue_id' => $venue->id]]);
    $rows = collect($groups[0]['items'][0]['rows'])->keyBy('field');
    expect($rows['Event manager']['value']['text'])->toBe('Named manager')
        ->and($rows['Saved venue']['value']['text'])->toBe('Saved assembly point');
});

test('readable pending summaries do not grant access to another manager participant or guest', function () {
    [$event] = submittedSetupFixture();
    $other = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    foreach ([$other, $runner] as $actor) {
        $this->actingAs($actor)->get(route('admin.events.show', $event))->assertForbidden()->assertDontSee('Synthetic review race');
    }
    auth()->logout();
    $this->get(route('admin.events.show', $event))->assertRedirect(route('login'));
});

test('new summary stays limited to pending first submissions and does not replace revision comparison', function () {
    [$event, $manager, $admin, $workflow] = submittedSetupFixture();
    $workflow->transition($event, $admin, 'approve', 1);
    $this->actingAs($admin)->get(route('admin.events.show', $event))->assertOk()->assertDontSee('data-submitted-setup', false);
    $revision = $workflow->startRevision($event->fresh(), $manager, $event->fresh()->review_version);
    $revision->update(['venue' => 'Proposed assembly point']);
    $workflow->transition($revision, $manager, 'submit', 0);
    $this->get(route('admin.events.show', $revision))->assertOk()->assertSee('Compare current and proposed setup')
        ->assertSee('Proposed assembly point')->assertDontSee('data-submitted-setup', false);
});
