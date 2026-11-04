<?php

use App\Models\Event;
use App\Models\User;
use App\Services\EventReviewWorkflow;

beforeEach(function () {
    config(['venues.locations_path' => base_path('tests/Fixtures/venue-locations.json')]);
});

function confirmationSetupEvent(User $manager, string $state = 'draft'): Event
{
    $event = Event::forceCreate([
        'title' => 'Synthetic confirmation event', 'slug' => 'confirmation-'.uniqid(),
        'description' => 'Synthetic assembly details', 'venue' => 'Synthetic venue',
        'interest_type' => 'Marathon', 'type_details' => ['cutoff_time' => '6 hours'],
        'event_date' => today()->addMonth(), 'event_end_date' => today()->addMonth(),
        'start_time' => '06:00', 'end_time' => '18:00', 'registration_deadline' => today()->addWeek(),
        'banner_image' => 'events/banners/synthetic-confirmation.png',
        'manager_id' => $manager->id, 'review_status' => $state,
    ]);
    $event->categories()->create([
        'name' => '5K Open', 'distance_km' => 5, 'distance_unit' => 'km', 'status' => 'open',
        'price_cents' => 0, 'price_currency' => 'PHP', 'slot_limit' => 50,
        'scheduled_start_date' => $event->event_date, 'scheduled_end_date' => $event->event_date,
        'scheduled_start_time' => '06:00', 'scheduled_end_time' => '12:00',
        'checkpoint_map_image' => 'categories/checkpoint-maps/synthetic-confirmation.png',
    ]);

    return $event;
}

test('create edit and revision forms provide an accessible non-submitting confirmation without changing setup', function (string $mode) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = null;
    $published = null;
    $workflow = app(EventReviewWorkflow::class);
    if ($mode !== 'create') {
        $event = confirmationSetupEvent($manager, $mode === 'revision' ? 'approved' : 'draft');
        if ($mode === 'revision') {
            $published = $event;
            $event = $workflow->startRevision($published, $manager, 0);
        }
    }
    $before = $event ? $workflow->snapshot($event) : null;
    $publishedBefore = $published ? $workflow->snapshot($published) : null;
    $auditCount = $event?->reviews()->count() ?? 0;
    $response = $this->actingAs($manager)->get($event ? route('admin.events.edit', $event) : route('admin.events.create'))
        ->assertOk()->assertSee('Keep Editing')->assertSee('css/event-review-submit.css', false)
        ->assertSee('Editing will be locked while it is pending unless you withdraw the submission.');
    if ($mode === 'revision') {
        $response->assertSee('Submit changes for review?')
            ->assertSee('The currently published event will stay unchanged until Admin approves these changes.');
    } else {
        $response->assertSee('Submit event for review?')->assertSee('The event will only be published after Admin approval.')
            ->assertDontSee('The currently published event will stay unchanged until Admin approves these changes.');
    }
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $xpath = new DOMXPath($dom);
    $dialog = $xpath->query('//form[@data-event-setup-form]//dialog[@data-event-review-confirmation]')->item(0);
    expect($dialog)->not->toBeNull()->and($dialog->hasAttribute('open'))->toBeFalse()
        ->and($dialog->getAttribute('aria-modal'))->toBe('true');
    foreach (['aria-labelledby', 'aria-describedby'] as $attribute) {
        expect($xpath->query('//*[@id="'.$dialog->getAttribute($attribute).'"]')->length)->toBe(1);
    }
    expect($xpath->query('.//button[@type="button" and @data-event-review-cancel and @autofocus]', $dialog)->length)->toBe(1)
        ->and($xpath->query('.//button[@type="button" and @data-event-review-confirm and not(@name)]', $dialog)->length)->toBe(1)
        ->and($xpath->query('.//form | .//input', $dialog)->length)->toBe(0)
        ->and($xpath->query('//form[@data-event-setup-form]//button[@name="intent" and @value="submit" and @type="submit" and not(@disabled)]')->length)->toBe(1)
        ->and($xpath->query('//form[@data-event-setup-form]//button[@name="intent" and @value="draft" and @formnovalidate]')->length)->toBe(1);
    if ($event) {
        expect($workflow->snapshot($event->fresh()))->toBe($before)->and($event->fresh()->review_status)->toBe('draft')
            ->and($event->reviews()->count())->toBe($auditCount);
    } else {
        expect(Event::count())->toBe(0);
    }
    if ($published) {
        expect($workflow->snapshot($published->fresh()))->toBe($publishedBefore)
            ->and($published->fresh()->review_status)->toBe('approved');
    }
})->with(['create', 'edit', 'revision']);
