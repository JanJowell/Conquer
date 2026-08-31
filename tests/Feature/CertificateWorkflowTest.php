<?php

use App\Models\Category;
use App\Models\Certificate;
use App\Models\Event;
use App\Models\RaceResult;
use App\Models\Registration;
use App\Models\RegistrationFeedback;
use App\Models\User;
use App\Services\CertificateIssuer;

function certificateRunner(): array
{
    $token = 'certificate-token-'.uniqid();
    $runner = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', $token),
        'api_token_expires_at' => now()->addMonth(),
    ]);

    return [$runner, $token];
}

function certificateRegistration(User $runner, ?User $manager = null, bool $withFeedback = false): Registration
{
    $event = Event::create([
        'title' => 'Certificate Race '.uniqid(),
        'slug' => 'certificate-race-'.uniqid(),
        'description' => 'Certificate workflow test.',
        'venue' => 'Bacoor City',
        'event_date' => now()->subDay()->toDateString(),
        'event_end_date' => now()->subDay()->toDateString(),
        'start_time' => '06:00',
        'end_time' => '12:00',
        'registration_deadline' => now()->subWeek()->toDateString(),
        'status' => 'completed',
        'organized_by' => 'RACETECH',
        'interest_type' => 'Marathon',
        'manager_id' => $manager?->id,
    ]);
    $category = Category::create([
        'event_id' => $event->id,
        'name' => '42K Open',
        'distance_km' => 42,
        'status' => 'open',
    ]);
    $registration = Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'status' => 'completed',
        'bib_number' => (string) fake()->unique()->numberBetween(1000, 9999),
        'payment_required' => false,
        'payment_status' => 'waived',
        'waiver_accepted' => true,
        'first_aid_kit_confirmed' => true,
        'registered_at' => now()->subMonth(),
    ]);
    RaceResult::create([
        'registration_id' => $registration->id,
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'finish_time' => '04:18:25',
        'rank_overall' => 8,
        'rank_category' => 3,
    ]);

    if ($withFeedback) {
        RegistrationFeedback::create([
            'registration_id' => $registration->id,
            'user_id' => $runner->id,
            'event_id' => $event->id,
            'category_id' => $category->id,
            'overall_rating' => 5,
            'submitted_at' => now(),
        ]);
    }

    return $registration;
}

test('certificate is issued only after official result and participant feedback', function () {
    [$runner, $token] = certificateRunner();
    $registration = certificateRegistration($runner);

    expect(app(CertificateIssuer::class)->syncForRegistration($registration))->toBeNull()
        ->and(Certificate::count())->toBe(0);

    $this->withToken($token)
        ->putJson("/api/registrations/{$registration->id}/feedback", [
            'overall_rating' => 5,
            'comment' => 'A well-organized event.',
        ])
        ->assertCreated()
        ->assertJsonPath('certificate_issued', true);

    $certificate = Certificate::firstOrFail();
    expect($certificate->registration_id)->toBe($registration->id)
        ->and($certificate->certificate_number)->toMatch('/^RCT-\d{4}-\d{8}$/')
        ->and($certificate->verification_token)->not->toBeEmpty();

    $this->withToken($token)->getJson('/api/my-registrations')
        ->assertOk()
        ->assertJsonPath('data.0.readiness.steps.certificate.status', 'available')
        ->assertJsonPath('data.0.readiness.certificate.certificate_number', $certificate->certificate_number)
        ->assertJsonPath('data.0.readiness.e_badges.available', false);
});

test('mobile certificate endpoints expose only the participants own certificates', function () {
    [$runner, $token] = certificateRunner();
    $registration = certificateRegistration($runner, withFeedback: true);
    $certificate = app(CertificateIssuer::class)->syncForRegistration($registration, notify: false);

    [$otherRunner, $otherToken] = certificateRunner();

    $this->withToken($token)->getJson('/api/certificates')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $certificate->id)
        ->assertJsonPath('data.0.status', 'valid');

    $this->withToken($otherToken)
        ->getJson("/api/registrations/{$registration->id}/certificate")
        ->assertForbidden();
});

test('public verification and PDF download use an unguessable token', function () {
    [$runner] = certificateRunner();
    $registration = certificateRegistration($runner, withFeedback: true);
    $certificate = app(CertificateIssuer::class)->syncForRegistration($registration, notify: false);

    $this->get(route('certificates.verify', $certificate->verification_token))
        ->assertOk()
        ->assertSee('Valid E-Certificate')
        ->assertSee($certificate->certificate_number)
        ->assertSee($runner->name);

    $this->get(route('certificates.download', $certificate->verification_token))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->get('/certificates/verify/not-a-real-token')->assertNotFound();
});

test('admin revocation is audited and blocks download while verification remains available', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    [$runner] = certificateRunner();
    $registration = certificateRegistration($runner, withFeedback: true);
    $certificate = app(CertificateIssuer::class)->syncForRegistration($registration, notify: false);

    $this->actingAs($admin)
        ->get(route('admin.certificates.index'))
        ->assertOk()
        ->assertSee('E-Certificates')
        ->assertSee($certificate->certificate_number);

    $this->actingAs($admin)
        ->patch(route('admin.certificates.revoke', $certificate), ['reason' => 'Result was entered incorrectly.'])
        ->assertRedirect();

    $certificate->refresh();
    expect($certificate->revoked_by)->toBe($admin->id)
        ->and($certificate->revocation_reason)->toBe('Result was entered incorrectly.')
        ->and($certificate->revoked_at)->not->toBeNull();

    $this->get(route('certificates.verify', $certificate->verification_token))
        ->assertOk()
        ->assertSee('Revoked E-Certificate');
    $this->get(route('certificates.download', $certificate->verification_token))->assertStatus(410);

    $this->actingAs($admin)
        ->patch(route('admin.certificates.reinstate', $certificate))
        ->assertRedirect();
    expect($certificate->refresh()->isValid())->toBeTrue();
});

test('event managers cannot manage certificates outside their assigned events', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    [$runner] = certificateRunner();
    $registration = certificateRegistration($runner, withFeedback: true);
    $certificate = app(CertificateIssuer::class)->syncForRegistration($registration, notify: false);

    $this->actingAs($manager)
        ->patch(route('admin.certificates.revoke', $certificate), ['reason' => 'Should be denied.'])
        ->assertForbidden();
});

test('sync command backfills eligible certificates without duplicating them', function () {
    [$runner] = certificateRunner();
    certificateRegistration($runner, withFeedback: true);

    $this->artisan('certificates:sync')->assertSuccessful();
    $this->artisan('certificates:sync')->assertSuccessful();

    expect(Certificate::count())->toBe(1);
});
