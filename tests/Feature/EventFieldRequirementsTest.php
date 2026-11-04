<?php

use App\Models\Event;
use App\Models\User;
use App\Services\EventReviewWorkflow;

function eventRequirementXPath(string $html): DOMXPath
{
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    return new DOMXPath($dom);
}

function eventRequirementFor(DOMXPath $xpath, string $name): DOMElement
{
    $control = $xpath->query('//*[@name="'.$name.'"]')->item(0);
    expect($control)->not->toBeNull();
    $label = $control->hasAttribute('id')
        ? $xpath->query('//label[@for="'.$control->getAttribute('id').'"]')->item(0)
        : null;
    $label ??= $xpath->query('ancestor::div[label][1]/label[1]', $control)->item(0);
    expect($label)->not->toBeNull();
    $marker = $xpath->query('.//span[@data-field-requirement]', $label)->item(0);
    expect($marker)->not->toBeNull();

    return $marker;
}

function eventRequirementDraft(User $manager): Event
{
    $event = Event::forceCreate([
        'title' => 'Synthetic requirement labels', 'slug' => 'requirement-labels-'.uniqid(),
        'description' => 'Assembly details', 'venue' => 'Synthetic assembly point', 'interest_type' => 'Marathon',
        'event_date' => today()->addMonth(), 'event_end_date' => today()->addMonth(),
        'start_time' => '06:00', 'end_time' => '18:00', 'registration_deadline' => today()->addWeek(),
        'banner_image' => 'events/banners/synthetic-labels.jpg', 'type_details' => ['cutoff_time' => '6 hours'],
        'manager_id' => $manager->id, 'review_status' => 'draft', 'status' => 'draft',
    ]);
    $event->categories()->create([
        'name' => '5K Open', 'distance_km' => 5, 'distance_unit' => 'km', 'status' => 'open',
        'price_cents' => 0, 'price_currency' => 'PHP', 'scheduled_start_date' => $event->event_date,
        'scheduled_end_date' => $event->event_date, 'scheduled_start_time' => '06:00', 'scheduled_end_time' => '12:00',
        'slot_limit' => 50, 'checkpoint_map_image' => 'categories/checkpoint-maps/synthetic-labels.jpg',
    ]);

    return $event;
}

test('event create uses accessible required asterisks without a legend and retains review and draft actions', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $response = $this->actingAs($manager)->get(route('admin.events.create'))->assertOk()
        ->assertDontSee('Before submission')->assertDontSee('(Required)')->assertDontSee('* Required fields')
        ->assertDontSee('data-event-requirement-legend', false)
        ->assertSee('Checkbox settings do not have to be enabled.')
        ->assertSee('Submit for Review')->assertSee('Save Draft')->assertSee('Free events do not need a payment option.')
        ->assertSee('Paid open categories need at least one enabled option before submission.')
        ->assertDontSee('name="manager_id"', false);
    $xpath = eventRequirementXPath($response->getContent());
    foreach (['title', 'interest_type', 'event_date', 'event_end_date', 'venue_name', 'venue_area_code', 'venue_city_code', 'venue_barangay_code'] as $name) {
        $marker = eventRequirementFor($xpath, $name);
        expect($marker->getAttribute('data-field-requirement'))->toBe('required')
            ->and($xpath->query('.//span[@data-requirement-symbol and @aria-hidden="true"]', $marker)->item(0)->textContent)->toBe('*')
            ->and($xpath->query('.//span[@data-requirement-label and @class="sr-only"]', $marker)->item(0)->textContent)->toBe('Required');
    }
    foreach (['description', 'registration_deadline', 'start_time', 'end_time', 'banner_image_upload'] as $name) {
        expect(eventRequirementFor($xpath, $name)->getAttribute('data-field-requirement'))->toBe('required')
            ->and($xpath->query('//*[@name="'.$name.'"]')->item(0)->hasAttribute('required'))->toBeFalse();
    }
    expect($xpath->query('//*[@name="banner_image_upload"]')->item(0)->getAttribute('data-review-satisfied'))->toBe('false');
    foreach (['title', 'interest_type', 'event_date', 'event_end_date', 'end_time'] as $name) {
        expect($xpath->query('//*[@name="'.$name.'"]')->item(0)->hasAttribute('required'))->toBeFalse();
    }
    expect($xpath->query('//span[@data-field-requirement]/input | //span[@data-field-requirement]/button')->length)->toBe(0);
    expect($xpath->query('//button[@name="intent" and @value="submit" and not(@formnovalidate)]')->length)->toBe(1)
        ->and($xpath->query('//button[@name="intent" and @value="draft" and @formnovalidate]')->length)->toBe(1)
        ->and($xpath->query('//span[@data-field-requirement!="required" and @data-field-requirement!="optional"]')->length)->toBe(0);
});

test('existing category rows and added category templates have matching requirement guidance', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $response = $this->actingAs($manager)->get(route('admin.events.create'))->assertOk();
    $xpath = eventRequirementXPath($response->getContent());
    foreach (['0', '__INDEX__'] as $index) {
        foreach (['category_type', 'distance_option', 'price_currency', 'status', 'slot_limit', 'scheduled_start_date', 'scheduled_start_time', 'scheduled_end_date', 'scheduled_end_time'] as $key) {
            expect(eventRequirementFor($xpath, "categories[{$index}][{$key}]")->getAttribute('data-field-requirement'))->toBe('required');
        }
        foreach (['description', 'qualification_notes', 'price_amount'] as $key) {
            expect(eventRequirementFor($xpath, "categories[{$index}][{$key}]")->getAttribute('data-field-requirement'))->toBe('optional');
        }
        foreach (['custom_category_name', 'custom_distance_km', 'group_min_members', 'group_max_members'] as $key) {
            expect(eventRequirementFor($xpath, "categories[{$index}][{$key}]")->getAttribute('data-field-requirement'))->toBe('required');
        }
        expect(eventRequirementFor($xpath, "categories[{$index}][checkpoint_map_image_upload]")->getAttribute('data-field-requirement'))->toBe('required')
            ->and($xpath->query('//*[@name="categories['.$index.'][checkpoint_map_image_upload]"]')->item(0)->hasAttribute('required'))->toBeFalse();
        expect($xpath->query('//*[@name="categories['.$index.'][requires_medical_certificate]" and @type="checkbox"]/ancestor::label[1]//span[@data-field-requirement="optional"]')->length)->toBe(1)
            ->and($xpath->query('//*[@name="categories['.$index.'][price_amount]"]')->item(0)->getAttribute('value'))->toBe('0.00')
            ->and($xpath->query('//*[@name="categories['.$index.'][price_currency]"]')->item(0)->getAttribute('value'))->toBe('PHP');
    }
});

test('event and category schema labels follow the selected type without marking optional values as required', function (string $type) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $response = $this->actingAs($manager)->withSession(['_old_input' => ['interest_type' => $type]])
        ->get(route('admin.events.create'))->assertOk();
    $xpath = eventRequirementXPath($response->getContent());
    foreach (config('conquer.event_type_details.'.$type) as $key => $definition) {
        if ($definition['category_owned'] ?? false) {
            continue;
        }
        $kind = $definition['type'] === 'boolean' ? 'optional'
            : ((in_array('required', $definition['rules'], true) || $definition['required_for_publication']) ? 'required' : 'optional');
        $marker = eventRequirementFor($xpath, "type_details[{$type}][{$key}]");
        expect($marker->getAttribute('data-field-requirement'))->toBe($kind);
        if ($definition['type'] === 'duration') {
            foreach (['_hours', '_minutes'] as $suffix) {
                expect(eventRequirementFor($xpath, "type_details[{$type}][{$key}{$suffix}]")->textContent)->toContain('Required');
            }
        }
    }
    foreach (config('conquer.event_category_type_details.'.$type, []) as $key => $definition) {
        $kind = (in_array('required', $definition['rules'], true) || ($definition['required_for_publication'] ?? false)) ? 'required' : 'optional';
        foreach (['0', '__INDEX__'] as $index) {
            $control = $xpath->query('//*[@data-category-type-details="'.$type.'"]//*[@name="categories['.$index.'][type_details]['.$key.']"]')->item(0);
            expect($control)->not->toBeNull();
            $marker = $xpath->query('ancestor::div[label][1]/label/span[@data-field-requirement]', $control)->item(0);
            expect($marker->getAttribute('data-field-requirement'))->toBe($kind);
        }
    }
})->with(['Cycling', 'Hiking', 'Marathon', 'Trail Run', 'Triathlon', 'Duathlon']);

test('editing retains the saved banner and all setup values while showing the same labels', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = eventRequirementDraft($manager);
    $workflow = app(EventReviewWorkflow::class);
    $before = $workflow->snapshot($event);
    $response = $this->actingAs($manager)->get(route('admin.events.edit', $event))->assertOk()
        ->assertSee('Your saved banner already counts. Choose a new file only if you want to replace it.')
        ->assertSee('name="banner_image" value="events/banners/synthetic-labels.jpg"', false)
        ->assertSee('data-venue-mode value="keep"', false);
    $xpath = eventRequirementXPath($response->getContent());
    expect(eventRequirementFor($xpath, 'banner_image_upload')->getAttribute('data-field-requirement'))->toBe('required')
        ->and($xpath->query('//*[@name="banner_image_upload"]')->item(0)->hasAttribute('required'))->toBeFalse()
        ->and($xpath->query('//*[@name="banner_image_upload"]')->item(0)->getAttribute('data-review-satisfied'))->toBe('true')
        ->and($workflow->snapshot($event->fresh()))->toBe($before)
        ->and($event->fresh()->review_version)->toBe(0);
});

test('submission labels do not force complete setup when saving a basic private draft', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($manager)->post(route('admin.events.store'), [
        'title' => 'Synthetic basic draft', 'interest_type' => 'Marathon', 'venue' => 'Synthetic assembly point',
        'event_date' => today()->addMonth()->toDateString(), 'event_end_date' => today()->addMonth()->toDateString(),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $event = Event::where('title', 'Synthetic basic draft')->sole();
    expect($event->review_status)->toBe('draft')->and($event->description)->toBeNull()
        ->and($event->banner_image)->toBeNull()->and($event->registration_deadline)->toBeNull()->and($event->start_time)->toBeNull();
    $this->post(route('admin.events.review', [$event, 'submit']), ['review_version' => 0])->assertSessionHasErrors('review');
    $message = session('errors')->first('review');
    expect($message)->toContain('add a description', 'set a start time', 'set a registration deadline',
        'upload a banner image', 'add cut-off duration', 'add at least one open category');
    expect($event->fresh()->review_status)->toBe('draft')->and($event->reviews()->count())->toBe(0);
});

test('free complete drafts submit with a saved image without a drawn route category notes or payment options', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = eventRequirementDraft($manager);
    $this->actingAs($manager)->post(route('admin.events.review', [$event, 'submit']), ['review_version' => 0])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($event->fresh()->review_status)->toBe('pending_review')->and($event->paymentMethods()->count())->toBe(0)
        ->and($event->end_time->format('H:i'))->toBe('18:00')->and($event->categories()->sole()->course_map)->toBeNull()
        ->and($event->categories()->sole()->slot_limit)->toBe(50);
});
