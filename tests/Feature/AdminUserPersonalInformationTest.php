<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;

test('personal information section is shown only for participant account creation', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.users.create'))
        ->assertOk()
        ->assertSee('id="participant-personal-information"', false)
        ->assertSee("personalInformationSection.classList.toggle('hidden', !isRunner)", false)
        ->assertSee("field.disabled = !isRunner", false)
        ->assertSee("field.value = ''", false);

    $document = new DOMDocument();
    @$document->loadHTML($response->getContent());
    $form = new DOMXPath($document);

    expect(trim($form->query('//section//h3')->item(0)->textContent))->toBe('Basic Information')
        ->and(trim($form->query('//*[@id="participant-personal-information"]//h3')->item(0)->textContent))->toBe('Personal Information')
        ->and($form->query('//section[.//h3[normalize-space()="Basic Information"]][@id]')->length)->toBe(0)
        ->and($form->query('//*[@id="participant-personal-information"]//*[@id="address"]')->length)->toBe(1)
        ->and($form->query('//*[@id="participant-personal-information"]//*[@id="medical_conditions"]')->length)->toBe(1)
        ->and($form->query('//*[@id="participant-personal-information"]//*[@id="emergency_contact_name"]')->length)->toBe(1)
        ->and($form->query('//*[@id="participant-personal-information"]//*[@id="emergency_contact_number"]')->length)->toBe(1);
});

test('administrator creation discards participant-only personal information', function () {
    Notification::fake();

    $admin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Invited Content Moderator',
            'email' => 'content.moderator@example.test',
            'role' => User::ROLE_CONTENT_MODERATOR,
            'address' => 'Should not be stored',
            'medical_conditions' => 'Should not be stored',
            'emergency_contact' => 'Legacy value',
            'emergency_contact_name' => 'Should not be stored',
            'emergency_contact_number' => '09123456789',
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasNoErrors();

    $createdAdmin = User::where('email', 'content.moderator@example.test')->firstOrFail();

    expect($createdAdmin->address)->toBeNull()
        ->and($createdAdmin->medical_conditions)->toBeNull()
        ->and($createdAdmin->emergency_contact)->toBeNull()
        ->and($createdAdmin->emergency_contact_name)->toBeNull()
        ->and($createdAdmin->emergency_contact_number)->toBeNull();
});

test('participant creation keeps supplied personal information', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'New Participant',
            'email' => 'new.participant@example.test',
            'password' => 'participant-password',
            'password_confirmation' => 'participant-password',
            'role' => User::ROLE_RUNNER,
            'address' => 'Participant address',
            'medical_conditions' => 'No known conditions',
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_contact_number' => '09123456789',
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasNoErrors();

    $participant = User::where('email', 'new.participant@example.test')->firstOrFail();

    expect($participant->address)->toBe('Participant address')
        ->and($participant->medical_conditions)->toBe('No known conditions')
        ->and($participant->emergency_contact_name)->toBe('Emergency Contact')
        ->and($participant->emergency_contact_number)->toBe('09123456789');
});
