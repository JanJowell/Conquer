<?php

use App\Http\Resources\Api\CategoryResource;
use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use App\Rules\CategoryCourseMap;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function courseMapTestData(): array
{
    return [
        'version' => 1,
        'path' => [['lat' => 14.46, 'lng' => 120.96], ['lat' => 14.47, 'lng' => 120.97]],
        'checkpoints' => [['lat' => 14.465, 'lng' => 120.965, 'label' => 'Water station']],
    ];
}

function courseMapTestEvent(User $manager): Event
{
    return Event::create([
        'title' => 'Course Editor Test', 'slug' => 'course-editor-'.uniqid(),
        'description' => 'Category course editor test.', 'venue' => 'Bacoor',
        'event_date' => now()->addMonth()->toDateString(),
        'event_end_date' => now()->addMonth()->toDateString(),
        'start_time' => '06:00', 'end_time' => '12:00',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'organized_by' => 'Racetech', 'interest_type' => 'Cycling',
        'status' => 'draft', 'manager_id' => $manager->id,
    ]);
}

function courseMapTestPayload(Event $event, array $extra = []): array
{
    return [
        'event_id' => $event->id, 'category_type' => 'open', 'distance_option' => '10',
        'scheduled_start_date' => $event->event_date->toDateString(), 'scheduled_start_time' => '07:00',
        'scheduled_end_date' => $event->event_date->toDateString(), 'scheduled_end_time' => '10:00',
        'price_amount' => '0.00', 'price_currency' => 'PHP', 'status' => 'open',
        ...$extra,
    ];
}

function courseMapTestCategory(Event $event): Category
{
    return $event->categories()->create([
        'name' => '10K Open', 'distance_km' => 10, 'status' => 'open',
        'scheduled_start_date' => $event->event_date, 'scheduled_start_time' => '07:00',
        'scheduled_end_date' => $event->event_date, 'scheduled_end_time' => '10:00',
        'course_map' => courseMapTestData(),
    ]);
}

function courseMapTestEventPayload(Event $event, array $rows): array
{
    return [
        'title' => $event->title, 'description' => $event->description, 'venue' => $event->venue,
        'event_date' => $event->event_date->toDateString(), 'event_end_date' => $event->event_end_date->toDateString(),
        'start_time' => '06:00', 'end_time' => '12:00', 'interest_type' => 'Cycling',
        'organized_by' => 'Racetech', 'manager_id' => $event->manager_id,
        'categories' => $rows,
    ];
}

test('a category course is saved alongside its existing image contract and reloads in the editor', function () {
    Storage::fake('public');
    $admin = User::factory()->create();
    $event = courseMapTestEvent($admin);
    $map = courseMapTestData();
    $map['checkpoints'][0]['label'] = '  Water station  ';

    $this->actingAs($admin)->post(route('admin.categories.store'), courseMapTestPayload($event, [
        'course_map' => json_encode($map),
        'checkpoint_map_image_upload' => UploadedFile::fake()->image('map.png'),
    ]))->assertSessionHasNoErrors();

    $category = $event->categories()->firstOrFail();
    expect($category->course_map)->toEqual(courseMapTestData());
    Storage::disk('public')->assertExists($category->checkpoint_map_image);
    $resource = (new CategoryResource($category))->toArray(Request::create('/api/events'));
    expect($resource['checkpoint_map_image_url'])->toEndWith($category->checkpoint_map_image);

    $response = $this->get(route('admin.categories.edit', $category))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $field = $xpath->query('//input[@name="course_map"]')->item(0);
    expect(json_decode($field->getAttribute('value'), true))->toEqual(courseMapTestData());
});

test('omitting a course preserves it while an explicit clear leaves the uploaded image untouched', function () {
    Storage::fake('public');
    $admin = User::factory()->create();
    $event = courseMapTestEvent($admin);
    $category = courseMapTestCategory($event);
    Storage::disk('public')->put('categories/checkpoint-maps/keep.png', 'map');
    $category->update(['checkpoint_map_image' => 'categories/checkpoint-maps/keep.png']);

    $this->actingAs($admin)->put(route('admin.categories.update', $category), courseMapTestPayload($event))
        ->assertSessionHasNoErrors();
    expect($category->fresh()->course_map)->toEqual(courseMapTestData());

    $this->put(route('admin.categories.update', $category), courseMapTestPayload($event, ['course_map' => '']))
        ->assertSessionHasNoErrors();
    expect($category->fresh()->course_map)->toBeNull()
        ->and($category->fresh()->checkpoint_map_image)->toBe('categories/checkpoint-maps/keep.png');
    Storage::disk('public')->assertExists('categories/checkpoint-maps/keep.png');
});

dataset('invalid category courses', [
    'malformed JSON' => ['{bad'],
    'unsupported version' => [fn () => [...courseMapTestData(), 'version' => 2]],
    'unknown top-level fields' => [fn () => [...courseMapTestData(), 'image_url' => 'https://example.test']],
    'one point' => [fn () => [...courseMapTestData(), 'path' => [['lat' => 14, 'lng' => 120]]]],
    'identical points' => [fn () => [...courseMapTestData(), 'path' => array_fill(0, 2, ['lat' => 14, 'lng' => 120])]],
    'excess points' => [fn () => [...courseMapTestData(), 'path' => array_fill(0, 1001, ['lat' => 14, 'lng' => 120])]],
    'latitude outside range' => [fn () => [...courseMapTestData(), 'path' => [['lat' => 91, 'lng' => 120], ['lat' => 14, 'lng' => 121]]]],
    'missing coordinate' => [fn () => [...courseMapTestData(), 'path' => [['lat' => 14], ['lat' => 15, 'lng' => 121]]]],
    'non numeric coordinate' => [fn () => [...courseMapTestData(), 'path' => [['lat' => 'bad', 'lng' => 120], ['lat' => 14, 'lng' => 121]]]],
    'missing checkpoints collection' => [fn () => collect(courseMapTestData())->except('checkpoints')->all()],
    'blank checkpoint label' => [fn () => [...courseMapTestData(), 'checkpoints' => [['lat' => 14, 'lng' => 120, 'label' => '   ']]]],
    'long checkpoint label' => [fn () => [...courseMapTestData(), 'checkpoints' => [['lat' => 14, 'lng' => 120, 'label' => str_repeat('x', 121)]]]],
    'checkpoint outside longitude range' => [fn () => [...courseMapTestData(), 'checkpoints' => [['lat' => 14, 'lng' => 181, 'label' => 'Water']]]],
    'too many checkpoints' => [fn () => [...courseMapTestData(), 'checkpoints' => array_fill(0, 51, ['lat' => 14, 'lng' => 120, 'label' => 'Water'])]],
    'non list route' => [fn () => [...courseMapTestData(), 'path' => ['a' => ['lat' => 14, 'lng' => 120], 'b' => ['lat' => 15, 'lng' => 121]]]],
    'oversized JSON' => [str_repeat('x', 150001)],
]);

test('invalid route submissions cannot overwrite a saved course', function (mixed $map) {
    $admin = User::factory()->create();
    $event = courseMapTestEvent($admin);
    $category = courseMapTestCategory($event);

    $this->actingAs($admin)->put(route('admin.categories.update', $category), courseMapTestPayload($event, [
        'course_map' => is_array($map) ? json_encode($map) : $map,
    ]))->assertSessionHasErrors('course_map');

    expect($category->fresh()->course_map)->toEqual(courseMapTestData());
})->with('invalid category courses');

test('event forms persist separate routes for existing and dynamically added categories', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = courseMapTestEvent($admin);
    $category = courseMapTestCategory($event);
    $replacement = courseMapTestData();
    $replacement['checkpoints'][0]['label'] = 'Replacement checkpoint';

    $this->actingAs($admin)->put(route('admin.events.update', $event), courseMapTestEventPayload($event, [
        courseMapTestPayload($event, ['id' => $category->id, 'course_map' => json_encode($replacement)]),
        courseMapTestPayload($event, ['category_type' => 'female', 'course_map' => json_encode(courseMapTestData())]),
    ]))->assertSessionHasNoErrors();

    expect($category->fresh()->course_map)->toEqual($replacement)
        ->and($event->categories()->where('id', '!=', $category->id)->firstOrFail()->course_map)->toEqual(courseMapTestData());

    $this->get(route('admin.events.edit', $event))->assertOk()
        ->assertSee('Replacement checkpoint')
        ->assertSee('categories[__INDEX__][course_map]', false);

    $this->put(route('admin.events.update', $event), courseMapTestEventPayload($event, [
        courseMapTestPayload($event, ['id' => $category->id]),
    ]))->assertSessionHasNoErrors();
    expect($category->fresh()->course_map)->toEqual($replacement);

    $this->put(route('admin.events.update', $event), courseMapTestEventPayload($event, [
        courseMapTestPayload($event, ['id' => $category->id, 'course_map' => '']),
    ]))->assertSessionHasNoErrors();
    expect($category->fresh()->course_map)->toBeNull();
});

test('embedded event creation saves its category route and rejects invalid nested data', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = courseMapTestEvent($admin);
    $payload = courseMapTestEventPayload($event, [courseMapTestPayload($event, ['course_map' => json_encode(courseMapTestData())])]);
    $payload['title'] = 'New Event With Course';
    // The seed event only supplies form values; it must not occupy the manager.
    $event->update(['manager_id' => null]);

    $this->actingAs($admin)->post(route('admin.events.store'), $payload)->assertSessionHasNoErrors();
    $createdEvent = Event::where('title', $payload['title'])->firstOrFail();
    expect($createdEvent->categories()->firstOrFail()->course_map)->toEqual(courseMapTestData());

    $payload['title'] = 'Invalid Course Event';
    $payload['categories'][0]['course_map'] = '{bad';
    $this->post(route('admin.events.store'), $payload)->assertSessionHasErrors('categories.0.course_map');
    $this->assertDatabaseMissing('events', ['title' => 'Invalid Course Event']);
});

test('only managers of the event or administrators can update its category route', function () {
    $owner = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $other = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $executive = User::factory()->create(['role' => User::ROLE_EXECUTIVE]);
    $event = courseMapTestEvent($owner);
    $category = courseMapTestCategory($event);
    $payload = courseMapTestPayload($event, ['course_map' => '']);

    foreach ([$other, $executive] as $unauthorized) {
        $this->actingAs($unauthorized)->put(route('admin.categories.update', $category), $payload)->assertForbidden();
        $this->put(route('admin.events.update', $event), courseMapTestEventPayload($event, [$payload]))->assertForbidden();
    }
    expect($category->fresh()->course_map)->toEqual(courseMapTestData());
});

test('category editors render without a key and never eagerly load Google Maps', function () {
    config(['services.google_maps.browser_key' => null]);
    $admin = User::factory()->create();
    $event = courseMapTestEvent($admin);
    $category = courseMapTestCategory($event);

    $this->actingAs($admin)->get(route('admin.categories.edit', $category))->assertOk()
        ->assertSee('The map editor is not configured yet.')
        ->assertSee('name="course_map"', false)
        ->assertDontSee('src="https://maps.googleapis.com', false);

    config(['services.google_maps.browser_key' => 'browser-test-key-not-a-real-credential']);
    $this->get(route('admin.events.create'))->assertOk()
        ->assertSee('Open Map Editor')
        ->assertSee('category-course-map.js')
        ->assertDontSee('src="https://maps.googleapis.com', false);
});

test('route normalization preserves a loop and does not silently drop checkpoint text', function () {
    $map = courseMapTestData();
    $map['path'][] = $map['path'][0];
    $map['checkpoints'][0]['label'] = '<b>Water station</b>';
    $validator = validator(['course_map' => $map], ['course_map' => new CategoryCourseMap]);

    expect($validator->passes())->toBeTrue()
        ->and(CategoryCourseMap::normalize($map))->toEqual($map);
});
