<?php

use App\Models\Event;
use App\Models\EventRevision;
use App\Models\User;
use App\Services\EventReviewWorkflow;
use App\Services\EventRevisionComparison;

function comparisonFixture(): array
{
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $live = Event::forceCreate([
        'title' => 'Original race', 'slug' => 'comparison-'.uniqid(), 'description' => 'Race details',
        'venue' => 'Original assembly point', 'event_date' => today()->addMonth(),
        'event_end_date' => today()->addMonth(), 'registration_deadline' => today()->addWeek(),
        'start_time' => '06:00', 'end_time' => '12:00', 'interest_type' => 'Marathon',
        'type_details' => ['cutoff_time' => '6 hours'], 'banner_image' => 'events/banner.jpg',
        'manager_id' => $manager->id, 'review_status' => 'approved',
    ]);
    $live->categories()->create([
        'name' => '5K Open', 'distance_km' => 5, 'status' => 'open', 'price_cents' => 0,
        'price_currency' => 'PHP', 'requires_medical_certificate' => false,
    ]);
    $live->paymentMethods()->create([
        'provider' => 'GCash', 'account_name' => 'Race account', 'account_number' => '00123', 'is_enabled' => true,
    ]);
    $live->checkpoints()->create(['name' => 'Water station', 'location' => 'Park gate', 'latitude' => 14, 'longitude' => 121, 'type' => 'hydration', 'order' => 1]);
    $live->refreshAutomaticStatus();
    $draft = app(EventReviewWorkflow::class)->startRevision($live, $manager, 0);

    return [$live, $draft, $manager];
}

test('unchanged copied setup does not show internal ID or JSON ordering differences', function () {
    [$live, $draft] = comparisonFixture();
    $live->update(['type_details' => ['cutoff_time' => '6 hours', 'optional_note' => 'Water provided']]);
    $draft->update(['type_details' => ['optional_note' => 'Water provided', 'cutoff_time' => '6 hours']]);
    // Rebaseline only the isolated test fixture after inserting equivalent structured data.
    EventRevision::where('draft_event_id', $draft->id)->update(['base_setup_hash' => app(EventReviewWorkflow::class)->setupHash($live)]);
    $before = app(EventReviewWorkflow::class)->setupHash($live);
    $comparison = app(EventRevisionComparison::class)->build($draft);

    expect($comparison['sections'])->toBe([])->and($comparison['stale'])->toBeFalse()
        ->and(app(EventReviewWorkflow::class)->setupHash($live))->toBe($before);
    $this->actingAs($draft->manager)->get(route('admin.events.show', $draft))
        ->assertOk()->assertSee('No setup changes found.')->assertDontSee('"manager_id"');
});

test('comparison shows only changed fields with human readable dates fees and booleans', function () {
    [$live, $draft, $manager] = comparisonFixture();
    $original = app(EventReviewWorkflow::class)->setupHash($live);
    $draft->update(['venue' => 'New assembly point', 'registration_deadline' => today()->addDays(10),
        'type_details' => ['cutoff_time' => '9 hours 30 minutes']]);
    $draft->categories()->sole()->update(['price_cents' => 95000, 'requires_medical_certificate' => true]);
    $comparison = app(EventRevisionComparison::class)->build($draft);
    $rows = collect($comparison['sections'])->pluck('rows')->flatten(1)->keyBy('field');

    expect($rows->keys()->all())->toContain('Venue / landmark', 'Registration Deadline', 'Cut-off Duration', 'Registration fee', 'Requires Medical Certificate')
        ->not->toContain('Event name', 'Event manager', 'Distance (stored kilometers)')
        ->and($rows['Registration fee']['proposed']['text'])->toBe('PHP 950.00')
        ->and($rows['Requires Medical Certificate']['current']['text'])->toBe('No')
        ->and($rows['Requires Medical Certificate']['proposed']['text'])->toBe('Yes')
        ->and($rows['Registration Deadline']['proposed']['text'])->toBe(today()->addDays(10)->format('M j, Y'));
    $this->actingAs($manager)->get(route('admin.events.show', $draft))->assertOk()
        ->assertSee('Venue / landmark')->assertSee('Original assembly point')->assertSee('New assembly point')->assertSee('PHP 950.00');
    expect(app(EventReviewWorkflow::class)->setupHash($live))->toBe($original)
        ->and($live->fresh()->review_version)->toBe(0)->and($draft->fresh()->review_version)->toBe(0)
        ->and($live->reviews()->count())->toBe(0)->and($draft->reviews()->count())->toBe(1);
});

test('added and removed items are matched using revision mappings and payment provider fallback', function () {
    [, $draft] = comparisonFixture();
    $draft->categories()->sole()->delete();
    $draft->categories()->create(['name' => '10K New', 'distance_km' => 10, 'status' => 'open']);
    $draft->paymentMethods()->delete();
    $draft->paymentMethods()->create(['provider' => 'GCash', 'account_name' => 'New account', 'account_number' => '00456', 'is_enabled' => true]);
    $draft->checkpoints()->sole()->delete();
    $draft->checkpoints()->create(['name' => 'Finish gate', 'location' => 'School', 'latitude' => 14.1, 'longitude' => 121.1, 'type' => 'finish', 'order' => 2]);
    $sections = collect(app(EventRevisionComparison::class)->build($draft)['sections'])->keyBy('title');

    expect($sections['Category: 5K Open']['change'])->toBe('Removed')
        ->and($sections['Category: 10K New']['change'])->toBe('Added')
        ->and($sections['Checkpoint: Water station']['change'])->toBe('Removed')
        ->and($sections['Checkpoint: Finish gate']['change'])->toBe('Added')
        ->and($sections['Payment option: GCash']['change'])->toBe('Changed')
        ->and(count($sections))->toBe(5);
    $paymentRows = collect($sections['Payment option: GCash']['rows'])->keyBy('field');
    expect($paymentRows->keys()->all())->toBe(['Account Name', 'Account Number'])
        ->and($paymentRows['Account Number']['current']['text'])->toBe('00123')
        ->and($paymentRows['Account Number']['proposed']['text'])->toBe('00456');
});

test('removing a payment option clearly identifies the removal', function () {
    [, $draft, $manager] = comparisonFixture();
    $draft->paymentMethods()->delete();
    $comparison = app(EventRevisionComparison::class)->build($draft);
    expect($comparison['sections'][0]['title'])->toBe('Payment option: GCash')
        ->and($comparison['sections'][0]['change'])->toBe('Removed')
        ->and($comparison['sections'][0]['rows'][0]['proposed']['text'])->toBe('Not present');
    $this->actingAs($manager)->get(route('admin.events.show', $draft))->assertOk()
        ->assertSee('payment options are disabled to preserve history');
});

test('route coordinate changes remain visible even when route point counts match', function () {
    [$live, $draft, $manager] = comparisonFixture();
    $course = ['version' => 1, 'path' => [['lat' => 14, 'lng' => 121], ['lat' => 14.1, 'lng' => 121.1]],
        'checkpoints' => [['lat' => 14, 'lng' => 121, 'label' => 'Start']]];
    $live->categories()->sole()->update(['course_map' => $course]);
    $course['path'][1]['lat'] = 14.2;
    $draft->categories()->sole()->update(['course_map' => $course]);
    $comparison = app(EventRevisionComparison::class)->build($draft);
    $row = $comparison['sections'][0]['rows'][0];
    expect($row['field'])->toBe('Drawn course map')
        ->and($row['current']['text'])->toBe('2 route points; 1 checkpoint')
        ->and($row['proposed']['details'])->toContain('Route point / 2 / Latitude: 14.2')
        ->and($row['current']['details'])->toContain('Route point / 2 / Latitude: 14.1');
    $this->actingAs($manager)->get(route('admin.events.show', $draft))->assertOk()->assertSee('View route coordinates');
});

test('comparison warns about stale revisions and escapes proposed user content', function () {
    [$live, $draft, $manager] = comparisonFixture();
    $live->update(['venue' => 'Public setup changed']);
    $draft->update(['description' => '<script>alert("unsafe")</script>']);
    $this->actingAs($manager)->get(route('admin.events.show', $draft))->assertOk()
        ->assertSee('Approval will be blocked')->assertSee('Public setup changed')
        ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("unsafe")</script>', false);
    expect($draft->fresh()->review_status)->toBe('draft')->and($live->fresh()->venue)->toBe('Public setup changed');
});

test('comparison indicates when the revision has already been published', function () {
    [$live, $draft, $manager] = comparisonFixture();
    $draft->update(['venue' => 'Approved assembly point']);
    $workflow = app(EventReviewWorkflow::class);
    $workflow->transition($draft, $manager, 'submit', 0);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $workflow->transition($draft, $admin, 'approve', 1);
    $this->actingAs($admin)->get(route('admin.events.show', $draft))->assertOk()
        ->assertSee('This revision has already been published.')->assertSee('No setup changes found.')
        ->assertDontSee('Approval will be blocked');
    expect($live->fresh()->venue)->toBe('Approved assembly point');
});
