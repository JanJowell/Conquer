<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Support\ParticipantCourseMap;
use Illuminate\Support\Facades\Http;

function participantMapRoute(): array
{
    return [
        'version' => 1,
        'path' => [['lat' => 14.46, 'lng' => 120.96], ['lat' => 14.47, 'lng' => 120.97]],
        'checkpoints' => [['lat' => 14.465, 'lng' => 120.965, 'label' => 'Water station']],
    ];
}

function participantMapCategory(): Category
{
    $event = Event::create([
        'title' => 'Course map run',
        'slug' => 'course-map-run',
        'description' => 'A complete public event description.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'start_time' => '06:00',
        'end_time' => '12:00',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'status' => 'upcoming',
        'banner_image' => 'events/banners/sample.jpg',
        'organized_by' => 'Conquer Events Team',
        'interest_type' => config('conquer.event_interest_types.0'),
    ]);

    return Category::create([
        'event_id' => $event->id,
        'name' => '5K',
        'distance_km' => 5,
        'slot_limit' => 25,
        'status' => 'open',
        'scheduled_start_time' => '06:00',
        'scheduled_end_time' => '08:00',
        'course_map' => participantMapRoute(),
        'checkpoint_map_image' => 'categories/maps/fallback.jpg',
    ]);
}

test('public event responses deliver the saved route without changing the image fallback', function () {
    Http::preventStrayRequests();
    $category = participantMapCategory();
    $expected = [
        'version' => 1,
        'path' => [['lat' => 14.46, 'lng' => 120.96], ['lat' => 14.47, 'lng' => 120.97]],
        'start' => ['lat' => 14.46, 'lng' => 120.96],
        'finish' => ['lat' => 14.47, 'lng' => 120.97],
        'is_loop' => false,
        'checkpoints' => [['lat' => 14.465, 'lng' => 120.965, 'label' => 'Water station', 'number' => 1]],
    ];

    foreach (['/api/events' => 'data.0.categories.0', '/api/events/'.$category->event_id => 'data.categories.0'] as $url => $path) {
        $this->getJson($url)->assertOk()
            ->assertJsonPath($path.'.course_map', $expected)
            ->assertJsonPath($path.'.checkpoint_map_image_url', asset('storage/categories/maps/fallback.jpg'));
    }

    expect($category->fresh()->course_map)->toBe(participantMapRoute());
    Http::assertNothingSent();
});

test('category routes stay isolated and closed categories stay hidden from the event', function () {
    $category = participantMapCategory();
    $second = $category->replicate();
    $second->name = '10K';
    $second->course_map = null;
    $second->checkpoint_map_image = null;
    $second->save();
    $closed = $category->replicate();
    $closed->status = 'closed';
    $closed->save();

    $response = $this->getJson('/api/events/'.$category->event_id)->assertOk()
        ->assertJsonCount(2, 'data.categories');
    $categories = collect($response->json('data.categories'))->keyBy('id');
    expect($categories[$category->id]['course_map'])->toBe(ParticipantCourseMap::make(participantMapRoute()))
        ->and($categories[$second->id]['course_map'])->toBeNull()
        ->and($categories[$second->id]['checkpoint_map_image_url'])->toBeNull();
});

test('draft event routes are not made public', function () {
    $category = participantMapCategory();
    $category->event->update(['status' => 'draft', 'banner_image' => null]);

    $this->getJson('/api/events')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/events/'.$category->event_id)->assertNotFound();
});

test('a participant receives the same course map in their own registration', function () {
    $category = participantMapCategory();
    $user = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', 'course-map-token'),
        'api_token_expires_at' => now()->addDay(),
    ]);
    Registration::create([
        'user_id' => $user->id,
        'event_id' => $category->event_id,
        'category_id' => $category->id,
        'shirt_size' => 'M',
        'status' => 'approved',
    ]);

    $this->getJson('/api/my-registrations')->assertUnauthorized();
    $this->withToken('course-map-token')->getJson('/api/my-registrations')
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.category.course_map', ParticipantCourseMap::make(participantMapRoute()));

    $this->withToken('course-map-token')->getJson('/api/events/'.$category->event_id)
        ->assertOk()
        ->assertJsonPath('data.current_registration.category.course_map', ParticipantCourseMap::make(participantMapRoute()));

    User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', 'another-map-token'),
        'api_token_expires_at' => now()->addDay(),
    ]);
    $this->withToken('another-map-token')->getJson('/api/my-registrations')
        ->assertOk()->assertJsonCount(0, 'data');
});

test('unusable stored routes leave the event and image fallback accessible', function (mixed $value) {
    $category = participantMapCategory();
    $category->update(['course_map' => $value]);

    $this->getJson('/api/events/'.$category->event_id)->assertOk()
        ->assertJsonPath('data.categories.0.course_map', null)
        ->assertJsonPath('data.categories.0.checkpoint_map_image_url', asset('storage/categories/maps/fallback.jpg'));
})->with([
    'no route' => [null],
    'empty object' => [[]],
    'invalid JSON string' => ['{broken'],
    'scalar' => [42],
    'unsupported version' => [array_replace(participantMapRoute(), ['version' => 2])],
    'unknown field' => [array_merge(participantMapRoute(), ['secret' => 'must-not-be-exposed'])],
    'out of range' => [array_replace(participantMapRoute(), ['path' => [['lat' => 91, 'lng' => 0], ['lat' => 1, 'lng' => 1]]])],
    'identical points' => [array_replace(participantMapRoute(), ['path' => [['lat' => 1, 'lng' => 1], ['lat' => 1, 'lng' => 1]]])],
]);

test('participant route presentation derives normalized endpoints and ordered text checkpoints', function () {
    $route = participantMapRoute();
    $route['path'][0] = ['lat' => '14.46000001', 'lng' => '120.96000001'];
    $route['path'][] = ['lat' => 14.46, 'lng' => 120.96];
    $route['checkpoints'][] = ['lat' => 14.47, 'lng' => 120.97, 'label' => ' <b>Finish water</b> '];
    $map = ParticipantCourseMap::make($route);

    expect($map['start'])->toBe(['lat' => 14.46, 'lng' => 120.96])
        ->and($map['finish'])->toBe($map['start'])
        ->and($map['is_loop'])->toBeTrue()
        ->and(array_column($map['checkpoints'], 'number'))->toBe([1, 2])
        ->and($map['checkpoints'][1]['label'])->toBe('<b>Finish water</b>')
        ->and(array_keys($map))->toBe(['version', 'path', 'start', 'finish', 'is_loop', 'checkpoints']);
});

test('participant maps support the maximum stored route and checkpoint sizes', function () {
    $route = participantMapRoute();
    $route['path'] = array_map(fn ($index) => ['lat' => 14 + $index / 10000, 'lng' => 120.96], range(0, 999));
    $route['checkpoints'] = array_fill(0, 50, $route['checkpoints'][0]);
    $map = ParticipantCourseMap::make($route);
    expect($map['path'])->toHaveCount(1000)
        ->and($map['checkpoints'])->toHaveCount(50)
        ->and($map['checkpoints'][49]['number'])->toBe(50);
});

test('a route without checkpoints remains usable after its uploaded image is removed', function () {
    $category = participantMapCategory();
    $route = participantMapRoute();
    $route['checkpoints'] = [];
    $category->update(['course_map' => $route, 'checkpoint_map_image' => null]);

    $this->getJson('/api/events/'.$category->event_id)->assertOk()
        ->assertJsonPath('data.categories.0.course_map.checkpoints', [])
        ->assertJsonPath('data.categories.0.course_map.path', $route['path'])
        ->assertJsonPath('data.categories.0.checkpoint_map_image_url', null);
});
