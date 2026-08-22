<?php

use App\Http\Resources\Api\CategoryResource;
use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Http\Request;

function qualificationNotesEvent(User $manager): Event
{
    return Event::create([
        'title' => 'Qualification Notes Test '.uniqid(),
        'slug' => 'qualification-notes-test-'.uniqid(),
        'description' => 'Tests category qualification notes.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'start_time' => '06:00',
        'end_time' => '12:00',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'status' => 'draft',
        'organized_by' => 'Racetech',
        'interest_type' => 'Cycling',
        'manager_id' => $manager->id,
    ]);
}

function qualificationNotesCategoryPayload(Event $event, ?string $notes): array
{
    return [
        'event_id' => $event->id,
        'category_type' => 'open',
        'distance_option' => '10',
        'scheduled_start_time' => '07:00',
        'scheduled_end_time' => '10:00',
        'slot_limit' => 100,
        'price_amount' => '0.00',
        'price_currency' => 'PHP',
        'status' => 'open',
        'qualification_notes' => $notes,
    ];
}

test('admin stores optional qualification notes and exposes them to mobile', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = qualificationNotesEvent($admin);
    $notes = 'Participants must be at least 18 and comfortable riding in a group.';

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), qualificationNotesCategoryPayload($event, $notes))
        ->assertRedirect(route('admin.categories.index', ['event_id' => $event->id]))
        ->assertSessionHasNoErrors();

    $category = $event->categories()->firstOrFail();
    $resource = (new CategoryResource($category))->toArray(Request::create('/api/events'));

    expect($category->qualification_notes)->toBe($notes)
        ->and($resource['qualification_notes'])->toBe($notes);
});

test('qualification notes are optional and reject excessive text', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = qualificationNotesEvent($admin);

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), qualificationNotesCategoryPayload($event, null))
        ->assertSessionHasNoErrors();

    $secondEvent = qualificationNotesEvent($admin);

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), qualificationNotesCategoryPayload($secondEvent, str_repeat('x', 5001)))
        ->assertSessionHasErrors('qualification_notes');
});

test('embedded event creation stores category qualification notes', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $eventDate = now()->addMonth()->toDateString();
    $notes = 'Open only to riders who can maintain a 20 km/h average pace.';

    $this
        ->actingAs($admin)
        ->post(route('admin.events.store'), [
            'title' => 'Embedded Qualification Notes',
            'description' => 'Event with category eligibility information.',
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
                'qualification_notes' => $notes,
            ]],
        ])
        ->assertSessionHasNoErrors();

    $category = Event::where('title', 'Embedded Qualification Notes')->firstOrFail()->categories()->firstOrFail();

    expect($category->qualification_notes)->toBe($notes);
});

test('admin can update qualification notes after a participant has registered', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $event = qualificationNotesEvent($admin);
    $category = Category::create([
        'event_id' => $event->id,
        'name' => '10K Open',
        'distance_km' => 10,
        'scheduled_start_time' => '07:00',
        'scheduled_end_time' => '10:00',
        'qualification_notes' => 'Previous race experience recommended.',
        'status' => 'open',
    ]);
    Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'status' => 'pending',
        'registered_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.categories.update', $category), [
            'scheduled_start_time' => '07:00',
            'scheduled_end_time' => '10:00',
            'slot_limit' => 100,
            'price_amount' => '0.00',
            'price_currency' => 'PHP',
            'status' => 'open',
            'qualification_notes' => 'Previous race experience is required.',
        ])
        ->assertSessionHasNoErrors();

    expect($category->fresh()->qualification_notes)->toBe('Previous race experience is required.');
});

test('category forms display qualification notes fields', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = qualificationNotesEvent($admin);
    $category = Category::create([
        'event_id' => $event->id,
        'name' => '10K Open',
        'distance_km' => 10,
        'status' => 'open',
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.categories.create', ['event_id' => $event->id]))
        ->assertOk()
        ->assertSee('Qualification / Eligibility Notes')
        ->assertSee('name="qualification_notes"', false);

    $this
        ->actingAs($admin)
        ->get(route('admin.categories.edit', $category))
        ->assertOk()
        ->assertSee('name="qualification_notes"', false);

    $this
        ->actingAs($admin)
        ->get(route('admin.events.create'))
        ->assertOk()
        ->assertSee('name="categories[0][qualification_notes]"', false);
});
