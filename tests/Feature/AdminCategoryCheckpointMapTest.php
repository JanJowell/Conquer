<?php

use App\Http\Resources\Api\CategoryResource;
use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function checkpointMapEvent(User $manager): Event
{
    return Event::create([
        'title' => 'Checkpoint Map Test '.uniqid(),
        'slug' => 'checkpoint-map-test-'.uniqid(),
        'description' => 'Tests category course map uploads.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'event_end_date' => now()->addMonth()->toDateString(),
        'start_time' => '06:00',
        'end_time' => '12:00',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'status' => 'draft',
        'organized_by' => 'Racetech',
        'interest_type' => 'Cycling',
        'manager_id' => $manager->id,
    ]);
}

function checkpointMapCategoryPayload(Event $event, array $overrides = []): array
{
    return array_merge([
        'event_id' => $event->id,
        'category_type' => 'open',
        'distance_option' => '10',
        'scheduled_start_date' => $event->event_date->toDateString(),
        'scheduled_start_time' => '07:00',
        'scheduled_end_date' => $event->event_date->toDateString(),
        'scheduled_end_time' => '10:00',
        'slot_limit' => 100,
        'price_amount' => '0.00',
        'price_currency' => 'PHP',
        'status' => 'open',
        'requires_medical_certificate' => '0',
    ], $overrides);
}

test('admin uploads a category checkpoint map and the mobile API exposes its URL', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = checkpointMapEvent($admin);

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), checkpointMapCategoryPayload($event, [
            'checkpoint_map_image_upload' => UploadedFile::fake()->image('course-map.png', 1200, 800),
        ]))
        ->assertSessionHasNoErrors();

    $category = $event->categories()->firstOrFail();
    $resource = (new CategoryResource($category))->toArray(Request::create('/api/events'));

    expect($category->checkpoint_map_image)->toStartWith('categories/checkpoint-maps/')
        ->and($resource['checkpoint_map_image_url'])->toEndWith($category->checkpoint_map_image);
    Storage::disk('public')->assertExists($category->checkpoint_map_image);
});

test('embedded event creation stores each category checkpoint map', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $eventDate = now()->addMonth()->toDateString();

    $this
        ->actingAs($admin)
        ->post(route('admin.events.store'), [
            'title' => 'Embedded Checkpoint Map',
            'description' => 'Event with a category map.',
            'venue' => 'Bacoor City',
            'event_date' => $eventDate,
            'event_end_date' => $eventDate,
            'start_time' => '06:00',
            'end_time' => '12:00',
            'registration_deadline' => now()->addWeek()->toDateString(),
            'banner_image' => 'events/banners/sample.jpg',
            'organized_by' => 'Racetech',
            'interest_type' => 'Cycling',
            'categories' => [[
                'category_type' => 'open',
                'distance_option' => '10',
                'scheduled_start_date' => $eventDate,
                'scheduled_start_time' => '07:00',
                'scheduled_end_date' => $eventDate,
                'scheduled_end_time' => '10:00',
                'slot_limit' => 100,
                'price_amount' => '0.00',
                'price_currency' => 'PHP',
                'status' => 'open',
                'requires_medical_certificate' => '0',
                'checkpoint_map_image_upload' => UploadedFile::fake()->image('embedded-map.webp', 1200, 800),
            ]],
        ])
        ->assertSessionHasNoErrors();

    $category = Event::where('title', 'Embedded Checkpoint Map')->firstOrFail()->categories()->firstOrFail();

    expect($category->checkpoint_map_image)->not->toBeNull();
    Storage::disk('public')->assertExists($category->checkpoint_map_image);
});

test('admin can replace and remove a category checkpoint map without leaving old files', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = checkpointMapEvent($admin);
    Storage::disk('public')->put('categories/checkpoint-maps/old-map.png', 'old');
    $category = Category::create([
        'event_id' => $event->id,
        'name' => '10K Open',
        'distance_km' => 10,
        'scheduled_start_date' => $event->event_date,
        'scheduled_start_time' => '07:00',
        'scheduled_end_date' => $event->event_date,
        'scheduled_end_time' => '10:00',
        'checkpoint_map_image' => 'categories/checkpoint-maps/old-map.png',
        'status' => 'open',
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.categories.update', $category), checkpointMapCategoryPayload($event, [
            'checkpoint_map_image_upload' => UploadedFile::fake()->image('replacement.jpg', 1200, 800),
        ]))
        ->assertSessionHasNoErrors();

    $replacementPath = $category->fresh()->checkpoint_map_image;
    expect($replacementPath)->not->toBe('categories/checkpoint-maps/old-map.png');
    Storage::disk('public')->assertMissing('categories/checkpoint-maps/old-map.png');
    Storage::disk('public')->assertExists($replacementPath);

    $this
        ->actingAs($admin)
        ->put(route('admin.categories.update', $category), checkpointMapCategoryPayload($event, [
            'remove_checkpoint_map_image' => '1',
        ]))
        ->assertSessionHasNoErrors();

    expect($category->fresh()->checkpoint_map_image)->toBeNull();
    Storage::disk('public')->assertMissing($replacementPath);
});

test('deleting a category also deletes its checkpoint map image', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = checkpointMapEvent($admin);
    Storage::disk('public')->put('categories/checkpoint-maps/delete-me.png', 'map');
    $category = Category::create([
        'event_id' => $event->id,
        'name' => '10K Open',
        'distance_km' => 10,
        'checkpoint_map_image' => 'categories/checkpoint-maps/delete-me.png',
        'status' => 'open',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.categories.destroy', $category))
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing('categories/checkpoint-maps/delete-me.png');
});

test('checkpoint map uploads reject non images and the old page is hidden from navigation', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = checkpointMapEvent($admin);

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), checkpointMapCategoryPayload($event, [
            'checkpoint_map_image_upload' => UploadedFile::fake()->create('map.pdf', 100, 'application/pdf'),
        ]))
        ->assertSessionHasErrors('checkpoint_map_image_upload');

    $this->actingAs($admin)
        ->get(route('admin.categories.create', ['event_id' => $event->id]))
        ->assertOk()
        ->assertSee('Course / Checkpoint Map')
        ->assertSee('name="checkpoint_map_image_upload"', false);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee(route('admin.content.checkpoints'), false);

    expect(route('admin.content.checkpoints'))->toContain('/admin/content/checkpoints');
});
