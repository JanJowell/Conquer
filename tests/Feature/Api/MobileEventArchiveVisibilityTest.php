<?php

use App\Models\Certificate;
use App\Models\Event;
use App\Models\Payment;
use App\Models\RaceResult;
use App\Models\Registration;
use App\Models\RegistrationFeedback;
use App\Models\User;
use App\Services\EventCleanup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    Http::preventStrayRequests();
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-11-18 10:00:00', config('app.timezone')));
});

function archiveVisibilityEvent(array $overrides = []): Event
{
    $date = today()->subDays(2);
    $event = Event::forceCreate([
        'title' => 'Archive discovery event', 'slug' => 'archive-discovery-'.Str::uuid(),
        'description' => 'Synthetic mobile discovery fixture.', 'venue' => 'Synthetic meeting point',
        'event_date' => $date, 'event_end_date' => $date, 'registration_deadline' => $date,
        'start_time' => '06:00', 'end_time' => '18:00', 'interest_type' => 'Marathon',
        'type_details' => ['cutoff_time' => '2 hours'], 'banner_image' => 'events/synthetic.png',
        'review_status' => 'approved', 'status' => 'completed', ...$overrides,
    ]);
    $event->categories()->create([
        'name' => 'Synthetic 3K Open', 'distance_km' => 3, 'status' => 'open',
        'slot_limit' => 100, 'price_cents' => 0, 'checkpoint_map_image' => 'categories/synthetic.png',
    ]);

    return $event->fresh();
}

function archiveVisibilityUser(string $role = User::ROLE_RUNNER, array $overrides = []): array
{
    $token = 'archive-visibility-'.Str::uuid();
    $user = User::factory()->create([
        'role' => $role, 'interests' => ['Marathon'], 'api_token' => hash('sha256', $token),
        'api_token_expires_at' => now()->addDay(), ...$overrides,
    ]);

    return [$user, $token];
}

function archiveVisibilityState(): array
{
    $state = [];
    foreach (Schema::getTableListing(schemaQualified: false) as $table) {
        $state[$table] = hash('sha256', DB::table($table)->get()->toJson());
    }
    ksort($state);

    return $state;
}

test('mobile discovery always excludes archived events across search filters and token states', function (array $query, string $viewer) {
    $visible = archiveVisibilityEvent();
    archiveVisibilityEvent(['archived_at' => now()]);
    if ($viewer !== 'guest') {
        [, $token] = archiveVisibilityUser(overrides: $viewer === 'expired' ? ['api_token_expires_at' => now()->subMinute()] : []);
        $this->withToken($viewer === 'invalid' ? 'unknown-archive-token' : $token);
    }
    $before = archiveVisibilityState();

    $this->getJson('/api/events'.($query === [] ? '' : '?'.http_build_query($query)))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visible->id)
        ->assertJsonPath('data.0.status', 'completed')
        ->assertJsonPath('meta.recommended', ($query['recommended'] ?? false) && $viewer === 'valid');

    expect(archiveVisibilityState())->toBe($before);
    Http::assertNothingSent();
})->with([
    'browse' => [[]],
    'title search' => [['search' => 'Archive discovery']],
    'venue search' => [['search' => 'meeting point']],
    'category search' => [['search' => '3K Open']],
    'interest filter' => [['interest' => 'Marathon']],
    'completed filter' => [['status' => 'completed']],
    'recommendations' => [['recommended' => 1]],
    'combined filters' => [['search' => 'Archive discovery', 'interest' => 'Marathon', 'status' => 'completed', 'recommended' => 1]],
    'no archive override' => [['archive' => 'all', 'include_archived' => 1]],
])->with(['guest', 'valid', 'invalid', 'expired']);

test('discovery preserves unarchived lifecycle states while unpublished setup stays hidden', function (?string $reviewStatus) {
    $completed = archiveVisibilityEvent(['review_status' => $reviewStatus]);
    $ongoing = archiveVisibilityEvent(['review_status' => $reviewStatus, 'event_date' => today(), 'event_end_date' => today()]);
    $upcoming = archiveVisibilityEvent(['review_status' => $reviewStatus, 'event_date' => today()->addDays(2), 'event_end_date' => today()->addDays(2)]);
    archiveVisibilityEvent(['review_status' => 'draft']);
    archiveVisibilityEvent(['review_status' => $reviewStatus, 'archived_at' => now()]);

    $response = $this->getJson('/api/events')->assertOk()->assertJsonCount(3, 'data');
    expect(collect($response->json('data'))->pluck('status', 'id')->all())->toEqual([
        $completed->id => 'completed', $ongoing->id => 'ongoing', $upcoming->id => 'upcoming',
    ]);
})->with(['legacy' => [null], 'approved' => ['approved']]);

test('old completed events are not automatically archived by discovery reads', function () {
    $event = archiveVisibilityEvent(['event_date' => today()->subDays(60), 'event_end_date' => today()->subDays(60)]);
    $before = archiveVisibilityState();

    $this->getJson('/api/events')->assertOk()->assertJsonPath('data.0.id', $event->id);

    expect($event->fresh()->archived_at)->toBeNull()->and(archiveVisibilityState())->toBe($before);
});

test('mobile discovery remains available before the archive migration is applied', function () {
    $migration = require database_path('migrations/2026_11_15_000001_add_event_archive_metadata.php');
    $migration->down(); // Synthetic SQLite only; RefreshDatabase restores the schema.
    expect(Schema::hasColumn('events', 'archived_at'))->toBeFalse();
    $event = archiveVisibilityEvent();
    $before = archiveVisibilityState();

    $this->getJson('/api/events')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $event->id);
    $this->getJson('/api/events?search=Archive&recommended=1')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/events/'.$event->id)->assertOk()->assertJsonPath('data.id', $event->id);

    expect(archiveVisibilityState())->toBe($before);
});

test('archive and restore change discovery only while all participant history payloads stay intact', function () {
    [$manager] = archiveVisibilityUser(User::ROLE_EVENT_MANAGER);
    [$admin] = archiveVisibilityUser(User::ROLE_SUPER_ADMIN);
    [$runner, $token] = archiveVisibilityUser();
    $event = archiveVisibilityEvent(['manager_id' => $manager->id]);
    $category = $event->categories()->sole();
    $registration = Registration::create([
        'user_id' => $runner->id, 'event_id' => $event->id, 'category_id' => $category->id,
        'status' => 'completed', 'bib_number' => '001', 'registered_at' => now()->subDays(3),
        'payment_required' => true, 'payment_status' => 'paid',
    ]);
    RaceResult::create([
        'registration_id' => $registration->id, 'user_id' => $runner->id,
        'event_id' => $event->id, 'category_id' => $category->id, 'finish_time' => '00:25:00',
    ]);
    Payment::create([
        'registration_id' => $registration->id, 'user_id' => $runner->id,
        'event_id' => $event->id, 'category_id' => $category->id, 'status' => 'paid', 'amount_cents' => 10000,
    ]);
    RegistrationFeedback::withoutEvents(fn () => RegistrationFeedback::create([
        'registration_id' => $registration->id, 'user_id' => $runner->id,
        'event_id' => $event->id, 'category_id' => $category->id, 'overall_rating' => 5, 'submitted_at' => now(),
    ]));
    Certificate::create([
        'registration_id' => $registration->id, 'user_id' => $runner->id,
        'event_id' => $event->id, 'category_id' => $category->id, 'certificate_number' => 'SYNTHETIC-ARCHIVE-001',
        'verification_token' => (string) Str::uuid(), 'participant_name' => $runner->name,
        'event_title' => $event->title, 'category_name' => $category->name, 'issued_at' => now(),
    ]);
    $headers = ['Authorization' => 'Bearer '.$token];
    $paths = ['/api/events/'.$event->id, '/api/my-registrations', '/api/my-results', '/api/certificates',
        "/api/registrations/{$registration->id}/payments", "/api/registrations/{$registration->id}/feedback",
        "/api/registrations/{$registration->id}/certificate"];
    $snapshots = [];
    foreach ($paths as $path) {
        $snapshots[$path] = $this->getJson($path, $headers)->assertOk()->json();
    }
    $this->getJson('/api/events', $headers)->assertOk()->assertJsonPath('data.0.id', $event->id);
    $cleanup = app(EventCleanup::class);

    foreach (['archive', 'restore'] as $action) {
        $cleanup->perform($event, $admin, $action, $cleanup->fingerprint($event->fresh()));
        $before = archiveVisibilityState();
        $this->getJson('/api/events', $headers)->assertOk()->assertJsonCount($action === 'archive' ? 0 : 1, 'data');
        foreach ($paths as $path) {
            $this->getJson($path, $headers)->assertOk()->assertExactJson($snapshots[$path]);
        }
        expect(archiveVisibilityState())->toBe($before)
            ->and($event->fresh()->effective_status)->toBe('completed');
    }
    Http::assertNothingSent();
});

test('archived participant history still requires its owner and valid login', function (string $viewer) {
    [$owner] = archiveVisibilityUser();
    $event = archiveVisibilityEvent(['archived_at' => now()]);
    $registration = Registration::create([
        'user_id' => $owner->id, 'event_id' => $event->id, 'category_id' => $event->categories()->sole()->id,
        'status' => 'completed', 'registered_at' => now(),
    ]);
    if ($viewer !== 'guest') {
        [, $token] = archiveVisibilityUser(overrides: $viewer === 'expired' ? ['api_token_expires_at' => now()->subMinute()] : []);
        $this->withToken($token);
    }
    $before = archiveVisibilityState();
    foreach (['payments', 'feedback', 'certificate'] as $resource) {
        $this->getJson("/api/registrations/{$registration->id}/{$resource}")
            ->assertStatus($viewer === 'other owner' ? 403 : 401);
    }
    if ($viewer === 'other owner') {
        $this->getJson('/api/my-registrations')->assertOk()->assertJsonCount(0, 'data');
    }
    expect(archiveVisibilityState())->toBe($before);
})->with(['guest', 'expired', 'other owner']);

test('archiving does not block feedback or first certificate issuance after completion', function () {
    [$runner, $token] = archiveVisibilityUser();
    [$admin] = archiveVisibilityUser(User::ROLE_SUPER_ADMIN);
    $event = archiveVisibilityEvent();
    $registration = Registration::create([
        'user_id' => $runner->id, 'event_id' => $event->id, 'category_id' => $event->categories()->sole()->id,
        'status' => 'completed', 'registered_at' => now()->subDays(3),
    ]);
    RaceResult::create([
        'registration_id' => $registration->id, 'user_id' => $runner->id,
        'event_id' => $event->id, 'category_id' => $registration->category_id, 'finish_time' => '00:25:00',
    ]);
    $cleanup = app(EventCleanup::class);
    $cleanup->perform($event, $admin, 'archive', $cleanup->fingerprint($event->fresh()));
    $this->withToken($token)->getJson("/api/registrations/{$registration->id}/certificate")->assertUnprocessable();
    $this->putJson("/api/registrations/{$registration->id}/feedback", ['overall_rating' => 4])
        ->assertCreated()->assertJsonPath('data.overall_rating', 4);
    $this->getJson("/api/registrations/{$registration->id}/certificate")
        ->assertOk()->assertJsonPath('data.registration_id', $registration->id)->assertJsonPath('data.status', 'valid');
    $this->getJson('/api/my-registrations')->assertOk()
        ->assertJsonPath('data.0.event.id', $event->id)->assertJsonPath('data.0.feedback.overall_rating', 4);
    $this->getJson('/api/events')->assertOk()->assertJsonCount(0, 'data');
    expect($event->fresh()->archived_at)->not->toBeNull();
    Http::assertNothingSent();
});

test('archiving does not change authorized staff checkpoint lists or assignment boundaries', function (string $role) {
    [$manager, $managerToken] = archiveVisibilityUser(User::ROLE_EVENT_MANAGER);
    [$admin, $adminToken] = archiveVisibilityUser(User::ROLE_SUPER_ADMIN);
    [, $otherToken] = archiveVisibilityUser(User::ROLE_EVENT_MANAGER);
    $event = archiveVisibilityEvent(['manager_id' => $manager->id]);
    $path = '/api/staff/checkpoint-scanner/context?event_id='.$event->id;
    $headers = ['Authorization' => 'Bearer '.($role === User::ROLE_SUPER_ADMIN ? $adminToken : $managerToken)];
    $snapshot = $this->getJson($path, $headers)->assertOk()->assertJsonPath('data.0.id', $event->id)->json();
    $cleanup = app(EventCleanup::class);
    $cleanup->perform($event, $admin, 'archive', $cleanup->fingerprint($event->fresh()));
    $before = archiveVisibilityState();

    $this->getJson($path, $headers)->assertOk()->assertExactJson($snapshot);
    $this->getJson($path, ['Authorization' => 'Bearer '.$otherToken])->assertOk()->assertJsonCount(0, 'data');
    expect(archiveVisibilityState())->toBe($before);
})->with([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER]);
