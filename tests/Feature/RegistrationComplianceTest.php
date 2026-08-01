<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;

function complianceEventWithCategory(float $distanceKm = 5): array
{
    $event = Event::create([
        'title' => 'Compliance Ready Run',
        'slug' => 'compliance-ready-run-'.uniqid(),
        'description' => 'A complete public event description.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'start_time' => '06:00',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'status' => 'upcoming',
        'banner_image' => 'events/banners/sample.jpg',
        'organized_by' => 'Conquer Events Team',
        'interest_type' => config('conquer.event_interest_types.0'),
    ]);

    $category = Category::create([
        'event_id' => $event->id,
        'name' => $distanceKm >= 50 ? '50K Ultra' : '5K',
        'distance_km' => $distanceKm,
        'slot_limit' => 50,
        'status' => 'open',
    ]);

    return [$event, $category];
}

test('check-in blocks race kit release until waiver is signed', function () {
    $admin = User::factory()->create();
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    [$event, $category] = complianceEventWithCategory();

    $registration = Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'shirt_size' => 'M',
        'status' => 'approved',
        'first_aid_kit_confirmed' => true,
        'waiver_accepted' => false,
        'registered_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->patch(route('admin.check-in.update', $registration), [
            'status' => 'checked_in',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'The participant must sign the waiver before the race kit can be released.');

    expect($registration->fresh())
        ->status->toBe('approved')
        ->kit_released_at->toBeNull();
});

test('check-in records onsite waiver signing and race kit release', function () {
    $admin = User::factory()->create();
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    [$event, $category] = complianceEventWithCategory();

    $registration = Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'shirt_size' => 'M',
        'status' => 'approved',
        'first_aid_kit_confirmed' => true,
        'waiver_accepted' => false,
        'registered_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->patch(route('admin.check-in.update', $registration), [
            'status' => 'checked_in',
            'kit_waiver_signed' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Check-in status updated successfully.');

    expect($registration->fresh())
        ->status->toBe('checked_in')
        ->kit_waiver_signed_at->not->toBeNull()
        ->kit_released_at->not->toBeNull();
});

test('check-in releases kit when mobile waiver was already accepted', function () {
    $admin = User::factory()->create();
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    [$event, $category] = complianceEventWithCategory();

    $registration = Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'shirt_size' => 'M',
        'status' => 'approved',
        'first_aid_kit_confirmed' => true,
        'waiver_accepted' => true,
        'waiver_accepted_at' => now(),
        'registered_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->patch(route('admin.check-in.update', $registration), [
            'status' => 'checked_in',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Check-in status updated successfully.');

    expect($registration->fresh())
        ->status->toBe('checked_in')
        ->kit_waiver_signed_at->toBeNull()
        ->kit_released_at->not->toBeNull();
});
