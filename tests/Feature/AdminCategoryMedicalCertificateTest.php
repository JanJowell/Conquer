<?php

use App\Http\Resources\Api\CategoryResource;
use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Http\Request;

function medicalRequirementEvent(User $manager): Event
{
    return Event::create([
        'title' => 'Medical Requirement Test '.uniqid(),
        'slug' => 'medical-requirement-test-'.uniqid(),
        'description' => 'Tests category medical certificate requirements.',
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

function medicalRequirementCategoryPayload(Event $event, bool $required): array
{
    return [
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
        'requires_medical_certificate' => $required ? '1' : '0',
    ];
}

test('admin explicitly controls the medical certificate requirement regardless of distance', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $requiredEvent = medicalRequirementEvent($admin);
    $optionalEvent = medicalRequirementEvent($admin);

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), medicalRequirementCategoryPayload($requiredEvent, true))
        ->assertSessionHasNoErrors();

    $optionalPayload = medicalRequirementCategoryPayload($optionalEvent, false);
    $optionalPayload['distance_option'] = 'custom';
    $optionalPayload['custom_distance_km'] = '50';

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), $optionalPayload)
        ->assertSessionHasNoErrors();

    $requiredCategory = $requiredEvent->categories()->firstOrFail();
    $optionalCategory = $optionalEvent->categories()->firstOrFail();
    $resource = (new CategoryResource($requiredCategory))->toArray(Request::create('/api/events'));

    expect($requiredCategory->distance_km)->toEqual(10)
        ->and($requiredCategory->requiresMedicalCertificate())->toBeTrue()
        ->and($resource['requires_medical_certificate'])->toBeTrue()
        ->and($optionalCategory->distance_km)->toEqual(50)
        ->and($optionalCategory->requiresMedicalCertificate())->toBeFalse();
});

test('embedded event creation stores its category medical certificate requirement', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $eventDate = now()->addMonth()->toDateString();

    $this
        ->actingAs($admin)
        ->post(route('admin.events.store'), [
            'title' => 'Embedded Medical Requirement',
            'description' => 'Event with a category medical requirement.',
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
                'requires_medical_certificate' => '1',
            ]],
        ])
        ->assertSessionHasNoErrors();

    $category = Event::where('title', 'Embedded Medical Requirement')->firstOrFail()->categories()->firstOrFail();

    expect($category->requiresMedicalCertificate())->toBeTrue();
});

test('medical certificate requirement is locked once the category has registrations', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $event = medicalRequirementEvent($admin);
    $category = Category::create([
        'event_id' => $event->id,
        'name' => '10K Open',
        'distance_km' => 10,
        'scheduled_start_date' => $event->event_date,
        'scheduled_start_time' => '07:00',
        'scheduled_end_date' => $event->event_date,
        'scheduled_end_time' => '10:00',
        'requires_medical_certificate' => true,
        'status' => 'open',
    ]);
    Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'status' => 'pending',
        'registered_at' => now(),
    ]);
    $payload = medicalRequirementCategoryPayload($event, false);

    $this
        ->actingAs($admin)
        ->put(route('admin.categories.update', $category), $payload)
        ->assertSessionHasNoErrors();

    expect($category->fresh()->requiresMedicalCertificate())->toBeTrue();
});

test('category forms show the medical certificate checkbox', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = medicalRequirementEvent($admin);
    $category = Category::create([
        'event_id' => $event->id,
        'name' => '10K Open',
        'distance_km' => 10,
        'status' => 'open',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.categories.create', ['event_id' => $event->id]))
        ->assertOk()
        ->assertSee('Medical Certificate Required')
        ->assertSee('name="requires_medical_certificate"', false);

    $this->actingAs($admin)
        ->get(route('admin.categories.edit', $category))
        ->assertOk()
        ->assertSee('name="requires_medical_certificate"', false);

    $this->actingAs($admin)
        ->get(route('admin.events.create'))
        ->assertOk()
        ->assertSee('name="categories[0][requires_medical_certificate]"', false);
});
