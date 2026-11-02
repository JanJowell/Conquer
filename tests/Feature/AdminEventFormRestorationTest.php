<?php

use App\Models\Event;
use App\Models\User;

function restorationEvent(): array
{
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = Event::create([
        'title' => 'Form restoration', 'slug' => 'form-restoration', 'description' => 'Saved details',
        'venue' => 'Saved venue', 'interest_type' => 'Marathon', 'manager_id' => $manager->id,
        'event_date' => today()->addMonth(), 'type_details' => ['cutoff_time' => '9 hours 30 minutes'],
    ]);

    return [$event, $manager];
}

test('event edit marks stored preset distances as selected', function (string $distance) {
    [$event, $manager] = restorationEvent();
    $event->categories()->create(['name' => $distance.'K Open', 'distance_km' => $distance, 'distance_unit' => 'km']);
    $response = $this->actingAs($manager)->get(route('admin.events.edit', $event))->assertOk();
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML($response->getContent());
        $selected = (new DOMXPath($document))->query('//select[@name="categories[0][distance_option]"]/option[@selected]');
        expect($selected->length)->toBe(1)->and($selected->item(0)->getAttribute('value'))->toBe($distance);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
})->with(['1', '3', '5', '10', '21', '42']);

test('custom miles edit values match the input precision without changing stored kilometers', function () {
    [$event, $manager] = restorationEvent();
    $category = $event->categories()->create(['name' => '10.01mi Open', 'distance_km' => 16.11, 'distance_unit' => 'mi']);
    $original = $category->getRawOriginal('distance_km');
    $this->actingAs($manager)->get(route('admin.events.edit', $event))->assertOk()
        ->assertViewHas('categoryRows', fn ($rows) => $rows[0]['distance_option'] === 'custom'
            && $rows[0]['custom_distance_km'] === 10.01 && $rows[0]['distance_unit'] === 'mi')
        ->assertSee('step="0.01" min="0.01" value="10.01"', false);
    expect($category->fresh()->getRawOriginal('distance_km'))->toBe($original);
});

test('preset restoration accepts numeric old input after a validation error', function () {
    [$event, $manager] = restorationEvent();
    $category = $event->categories()->create(['name' => '5K Open', 'distance_km' => 5, 'distance_unit' => 'km']);
    $this->actingAs($manager)->withSession(['_old_input' => ['categories' => [[
        'id' => $category->id, 'category_type' => 'open', 'distance_option' => 5, 'distance_unit' => 'km',
    ]]]])->get(route('admin.events.edit', $event))->assertOk()->assertSee('value="5" selected', false);
});
