<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;

function participantConfirmationRegistration(array $registrationOverrides = []): array
{
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $runner = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'name' => 'Confirmation Runner',
        'email' => 'confirmation.runner@example.test',
    ]);
    $event = Event::create([
        'title' => 'Confirmation Marathon',
        'slug' => 'confirmation-marathon-'.uniqid(),
        'description' => 'Participant decision confirmation test event.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'event_end_date' => now()->addMonth()->toDateString(),
        'registration_deadline' => now()->addWeek()->toDateString(),
        'status' => 'draft',
        'organized_by' => 'Racetech',
        'interest_type' => 'Marathon',
    ]);
    $category = Category::create([
        'event_id' => $event->id,
        'name' => '10K Open',
        'distance_km' => 10,
        'status' => 'open',
    ]);
    $registration = Registration::create(array_merge([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'status' => 'pending',
        'payment_required' => false,
        'payment_status' => 'waived',
        'payment_amount_cents' => 0,
        'payment_currency' => 'PHP',
        'registered_at' => now(),
    ], $registrationOverrides));

    return [$admin, $runner, $event, $category, $registration];
}

test('participant approval and rejection actions render a contextual confirmation dialog', function () {
    [$admin] = participantConfirmationRegistration();

    $this
        ->actingAs($admin)
        ->get(route('admin.participants.index'))
        ->assertOk()
        ->assertSee('registration-confirmation-dialog', false)
        ->assertSee('data-registration-review-form', false)
        ->assertSee('data-participant-name="Confirmation Runner"', false)
        ->assertSee('data-event-name="Confirmation Marathon"', false)
        ->assertSee('data-category-name="10K Open"', false)
        ->assertSee('Approve Registration')
        ->assertSee('Reject Registration')
        ->assertSee('Enter a reason before rejecting this registration.');
});

test('rejection requires a reason and stores the confirmed reason', function () {
    [$admin, , , , $registration] = participantConfirmationRegistration([
        'bib_number' => '014',
    ]);

    $this
        ->actingAs($admin)
        ->patch(route('admin.participants.update', $registration), [
            'status' => 'rejected',
            'rejection_reason' => '',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('rejection_reason');

    expect($registration->fresh()->status)->toBe('pending');

    $this
        ->actingAs($admin)
        ->patch(route('admin.participants.update', $registration), [
            'status' => 'rejected',
            'rejection_reason' => '  Medical certificate needs to be replaced.  ',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Participant record updated successfully.');

    $registration->refresh();

    expect($registration->status)->toBe('rejected')
        ->and($registration->rejection_reason)->toBe('Medical certificate needs to be replaced.')
        ->and($registration->bib_number)->toBeNull();
});

test('approval still enforces payment and assigns a bib only after payment is ready', function () {
    [$admin, , , , $registration] = participantConfirmationRegistration([
        'payment_required' => true,
        'payment_status' => 'unpaid',
        'payment_amount_cents' => 50000,
    ]);

    $this
        ->actingAs($admin)
        ->patch(route('admin.participants.update', $registration), [
            'status' => 'approved',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'This registration requires payment before it can be approved.');

    expect($registration->fresh()->status)->toBe('pending')
        ->and($registration->fresh()->bib_number)->toBeNull();

    $registration->update(['payment_status' => 'paid']);

    $this
        ->actingAs($admin)
        ->patch(route('admin.participants.update', $registration), [
            'status' => 'approved',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Participant record updated successfully.');

    expect($registration->fresh()->status)->toBe('approved')
        ->and($registration->fresh()->bib_number)->toBe('001')
        ->and($registration->fresh()->rejection_reason)->toBeNull();
});
