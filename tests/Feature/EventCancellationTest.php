<?php

use App\Models\AdminActivityLog;
use App\Models\Category;
use App\Models\Certificate;
use App\Models\CheckpointScan;
use App\Models\Event;
use App\Models\FinishScan;
use App\Models\Payment;
use App\Models\RaceResult;
use App\Models\Registration;
use App\Models\User;
use App\Services\EventCancellation;
use App\Services\EventCleanup;
use App\Services\EventManagerAvailability;
use App\Services\EventReviewWorkflow;
use App\Services\FinishScanToken;
use App\Services\RegistrationReadiness;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    Http::fake();
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-11-20 09:00:00', config('app.timezone')));
    config(['checkpoint_scanning.enabled' => true, 'checkpoint_scanning.scope' => 'eligible']);
});

function cancellationFixture(bool $ongoing = false): array
{
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = Event::create(['title' => 'Synthetic cancellation test', 'slug' => 'cancel-'.Str::uuid(),
        'description' => 'A synthetic race for cancellation tests.', 'venue' => 'Synthetic meeting point',
        'manager_id' => $manager->id, 'event_date' => '2026-11-21', 'event_end_date' => '2026-11-21',
        'registration_deadline' => '2026-11-21', 'start_time' => '06:00', 'end_time' => '12:00',
        'banner_image' => 'events/synthetic.png', 'interest_type' => 'Marathon', 'type_details' => ['cutoff_time' => '6 hours']]);
    $category = $event->categories()->create(['name' => 'Synthetic 3K', 'distance_km' => 3, 'slot_limit' => 100,
        'price_cents' => 0, 'status' => 'open', 'course_map' => ['version' => 1,
            'path' => [['lat' => 14.46, 'lng' => 120.96], ['lat' => 14.48, 'lng' => 120.98]],
            'checkpoints' => [['lat' => 14.465, 'lng' => 120.965, 'label' => 'Checkpoint 1'],
                ['lat' => 14.475, 'lng' => 120.975, 'label' => 'Checkpoint 2']]]]);
    $event = approveEventSetupForTest($event);
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $registration = Registration::create(['event_id' => $event->id, 'category_id' => $category->id,
        'user_id' => $runner->id, 'status' => 'checked_in', 'bib_number' => '001',
        'payment_required' => false, 'payment_status' => 'waived', 'registered_at' => now(),
        'waiver_accepted_at' => now(), 'first_aid_kit_confirmed' => true]);
    if ($ongoing) {
        test()->travelTo(Carbon::parse('2026-11-21 08:00:00', config('app.timezone')));
        $category->update(['started_at' => now()->subHour(), 'started_by_user_id' => $manager->id]);
    }

    return [$admin, $manager, $event->fresh(), $category->fresh(), $registration];
}

function cancellationInput(Event $event): array
{
    return ['cancellation_fingerprint' => app(EventCleanup::class)->fingerprint($event->fresh()),
        'confirmation' => '1', 'cancellation_reason' => 'Unsafe weather at the venue.'];
}

function cancellationHeaders(User $user): array
{
    $token = 'cancel-test-'.Str::uuid();
    $user->forceFill(['api_token' => hash('sha256', $token), 'api_token_expires_at' => now()->addDay()])->save();

    return ['Authorization' => 'Bearer '.$token];
}

function cancelSyntheticEvent(User $admin, Event $event): void
{
    $input = cancellationInput($event);
    app(EventCancellation::class)->cancel($event, $admin, $input['cancellation_fingerprint'],
        $input['cancellation_reason'], '127.0.0.1');
}

test('only an Admin can cancel a published upcoming or ongoing event', function (bool $ongoing) {
    [$admin, , $event] = cancellationFixture($ongoing);
    $this->actingAs($admin)->post(route('admin.events.cancel', $event), cancellationInput($event))
        ->assertRedirect(route('admin.events.index'))->assertSessionHasNoErrors();
    $event->refresh();
    expect($event->status)->toBe('cancelled')->and($event->effective_status)->toBe('cancelled')
        ->and($event->cancelled_at->timestamp)->toBe(now()->timestamp)
        ->and($event->cancelled_by_user_id)->toBe($admin->id)
        ->and($event->cancellation_reason)->toBe('Unsafe weather at the venue.')
        ->and(AdminActivityLog::where('action', 'event.cancel event='.$event->id)->count())->toBe(1);
})->with([false, true]);

test('Event Managers and participants cannot cancel through a forged request', function (string $role) {
    [, $manager, $event, , $registration] = cancellationFixture();
    $user = $role === 'manager' ? $manager : $registration->user;
    $this->actingAs($user)->post(route('admin.events.cancel', $event), cancellationInput($event))->assertForbidden();
    expect($event->fresh()->isCancelled())->toBeFalse();
})->with(['manager', 'participant']);

test('cancellation requires a reason, acknowledgement and current fingerprint without title typing', function (string $field, mixed $value) {
    [$admin, , $event] = cancellationFixture();
    $input = array_replace(cancellationInput($event), [$field => $value]);
    $this->actingAs($admin)->post(route('admin.events.cancel', $event), $input)->assertSessionHasErrors();
    expect($event->fresh()->isCancelled())->toBeFalse();
})->with([
    ['cancellation_reason', null], ['cancellation_reason', '   '], ['cancellation_reason', str_repeat('x', 1001)],
    ['confirmation', null], ['confirmation', '0'], ['cancellation_fingerprint', null],
]);

test('an already open legacy cancellation form may send its obsolete title field', function () {
    [$admin, , $event] = cancellationFixture();
    $this->actingAs($admin)->post(route('admin.events.cancel', $event), [
        ...cancellationInput($event), 'confirmation_title' => 'Obsolete title input',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($event->fresh()->isCancelled())->toBeTrue();
});

test('stale and duplicate cancellation requests cannot overwrite the first decision', function () {
    [$admin, , $event] = cancellationFixture();
    $input = cancellationInput($event);
    $event->update(['title' => 'Changed title']);
    $this->actingAs($admin)->post(route('admin.events.cancel', $event), $input)->assertSessionHasErrors('cancellation');
    $event->refresh();
    $input = cancellationInput($event);
    $this->post(route('admin.events.cancel', $event), $input)->assertRedirect();
    $this->post(route('admin.events.cancel', $event), $input)->assertSessionHasErrors('cancellation');
    expect(AdminActivityLog::where('action', 'event.cancel event='.$event->id)->count())->toBe(1);
});

test('draft, pending, completed and archived events are not cancellation candidates', function (string $state) {
    [$admin, , $event] = cancellationFixture();
    $attributes = match ($state) {
        'draft' => ['review_status' => 'draft', 'status' => 'draft'],
        'pending' => ['review_status' => 'pending_review', 'status' => 'draft'],
        'completed' => ['status' => 'completed', 'event_date' => '2026-11-19', 'event_end_date' => '2026-11-19'],
        'archived' => ['archived_at' => now()],
    };
    DB::table('events')->where('id', $event->id)->update($attributes);
    $event->refresh();
    $this->actingAs($admin)->post(route('admin.events.cancel', $event), cancellationInput($event))->assertSessionHasErrors('cancellation');
    expect($event->fresh()->isCancelled())->toBeFalse();
})->with(['draft', 'pending', 'completed', 'archived']);

test('cancellation is terminal and releases manager availability without changing the booked dates', function () {
    [$admin, $manager, $event] = cancellationFixture();
    $schedule = $event->only(['event_date', 'event_end_date', 'start_time', 'end_time']);
    $storedSchedule = array_intersect_key($event->getRawOriginal(), $schedule);
    $availability = app(EventManagerAvailability::class);
    expect($availability->conflictingEvent($manager->id, $schedule)?->id)->toBe($event->id);
    cancelSyntheticEvent($admin, $event);
    $event->refresh();
    $event->forceFill(['status' => 'upcoming'])->save();
    $this->travelTo(now()->addMonths(2));
    $event->refreshAutomaticStatus();
    expect($event->fresh()->effective_status)->toBe('cancelled')
        ->and(array_intersect_key($event->fresh()->getRawOriginal(), $schedule))->toEqual($storedSchedule)
        ->and($availability->conflictingEvent($manager->id, $schedule))->toBeNull()
        ->and(app(EventCleanup::class)->actionFor($event->fresh()))->toBe('archive');
});

test('cancellation preserves all child tables and disables only the automatic announcement', function () {
    [$admin, , $event, $category, $registration] = cancellationFixture(true);
    $registration->update(['status' => 'completed', 'payment_required' => true, 'payment_status' => 'paid']);
    $identity = ['registration_id' => $registration->id, 'user_id' => $registration->user_id,
        'event_id' => $event->id, 'category_id' => $category->id];
    RaceResult::create($identity + ['finish_time' => '00:25:00']);
    Payment::create($identity + ['amount_cents' => 10000, 'status' => 'paid']);
    $certificate = Certificate::create($identity + ['certificate_number' => 'SYNTHETIC-CANCEL-001',
        'verification_token' => (string) Str::uuid(), 'participant_name' => $registration->user->name,
        'event_title' => $event->title, 'category_name' => $category->name, 'issued_at' => now()]);
    $tables = array_diff(Schema::getTableListing(schemaQualified: false), ['events', 'announcements', 'admin_activity_logs']);
    $before = [];
    foreach ($tables as $table) {
        $before[$table] = DB::table($table)->get()->toJson();
    }
    cancelSyntheticEvent($admin, $event);
    foreach ($tables as $table) {
        expect(DB::table($table)->get()->toJson(), $table)->toBe($before[$table]);
    }
    expect($event->announcements()->where('is_auto_generated', true)->where('is_published', true)->exists())->toBeFalse();
    $this->get(route('certificates.verify', $certificate->verification_token))->assertOk();
    $this->actingAs($admin)->patch(route('admin.results.update', RaceResult::sole()), ['finish_now' => true])
        ->assertSessionHas('error');
    expect(RaceResult::sole()->finish_time)->toBe('00:25:00');
});

test('certificate collection and detail expose cancellation without changing certificate validity or download access', function (bool $revoked) {
    [$admin, , $event, $category, $registration] = cancellationFixture(true);
    $headers = cancellationHeaders($registration->user);
    $certificate = Certificate::create([
        'registration_id' => $registration->id, 'user_id' => $registration->user_id,
        'event_id' => $event->id, 'category_id' => $category->id,
        'certificate_number' => 'SYNTHETIC-CANCEL-API-001', 'verification_token' => (string) Str::uuid(),
        'participant_name' => $registration->user->name, 'event_title' => $event->title,
        'category_name' => $category->name, 'event_date' => $event->event_date,
        'distance_km' => $category->distance_km, 'venue' => $event->venue,
        'issued_at' => now(), 'revoked_at' => $revoked ? now() : null,
    ]);
    $original = $certificate->fresh()->getRawOriginal();
    $download = route('certificates.download', $certificate->verification_token);
    $endpoints = ['/api/certificates' => 'data.0', '/api/registrations/'.$registration->id.'/certificate' => 'data'];
    foreach ($endpoints as $endpoint => $prefix) {
        $this->getJson($endpoint, $headers)->assertOk()
            ->assertJsonPath($prefix.'.event.status', 'ongoing')
            ->assertJsonPath($prefix.'.event.is_cancelled', false)
            ->assertJsonPath($prefix.'.event.cancelled_at', null)
            ->assertJsonPath($prefix.'.event.cancellation_reason', null);
    }

    cancelSyntheticEvent($admin, $event);
    $event->refresh();
    foreach ($endpoints as $endpoint => $prefix) {
        $this->getJson($endpoint, $headers)->assertOk()
            ->assertJsonPath($prefix.'.event.id', $event->id)
            ->assertJsonPath($prefix.'.event.title', $event->title)
            ->assertJsonPath($prefix.'.event.status', 'cancelled')
            ->assertJsonPath($prefix.'.event.is_cancelled', true)
            ->assertJsonPath($prefix.'.event.cancelled_at', $event->cancelled_at->toIso8601String())
            ->assertJsonPath($prefix.'.event.cancellation_reason', $event->cancellation_reason)
            ->assertJsonMissingPath($prefix.'.event.cancelled_by_user_id')
            ->assertJsonPath($prefix.'.status', $revoked ? 'revoked' : 'valid')
            ->assertJsonPath($prefix.'.download_url', $revoked ? null : $download)
            ->assertJsonPath($prefix.'.verification_url', route('certificates.verify', $certificate->verification_token));
    }
    $this->get(route('certificates.verify', $certificate->verification_token))->assertOk();
    $response = $this->get($download)->assertStatus($revoked ? 410 : 200);
    if (! $revoked) {
        $response->assertHeader('Content-Type', 'application/pdf');
    }
    expect($certificate->fresh()->getRawOriginal())->toBe($original);
})->with(['valid certificate' => false, 'revoked certificate' => true]);

test('cancelled events block new registration, checkout and proof without an external payment request', function () {
    [$admin, , $event, $category, $registration] = cancellationFixture();
    $headers = cancellationHeaders($registration->user);
    cancelSyntheticEvent($admin, $event);
    foreach (["/api/events/{$event->id}/register/{$category->id}",
        "/api/registrations/{$registration->id}/paymongo-checkout",
        "/api/registrations/{$registration->id}/payment-proof"] as $url) {
        $this->postJson($url, [], $headers)->assertConflict()->assertJsonPath('code', 'event_cancelled');
    }
    Http::assertNothingSent();
    expect($registration->fresh()->status)->toBe('checked_in')->and($registration->fresh()->payment_status)->toBe('waived');
});

test('cancelled events block category starts, check-in, participant approval and manual finishes', function () {
    [$admin, $manager, $event, $category, $registration] = cancellationFixture();
    cancelSyntheticEvent($admin, $event);
    $this->actingAs($manager);
    $this->post(route('admin.categories.start', $category))->assertSessionHas('error');
    $this->patch(route('admin.check-in.update', $registration), ['action' => 'check_in'])->assertSessionHas('error');
    $this->patch(route('admin.participants.update', $registration), ['status' => 'approved'])->assertSessionHas('error');
    $this->post(route('admin.results.store'), ['registration_id' => $registration->id])->assertSessionHas('error');
    expect($category->fresh()->started_at)->toBeNull()->and($registration->fresh()->status)->toBe('checked_in')
        ->and(FinishScan::count())->toBe(0);
});

test('accepted checkpoint UUID retries survive cancellation but new visits and plans are rejected', function () {
    [$admin, $manager, $event, $category, $registration] = cancellationFixture(true);
    $headers = cancellationHeaders($manager);
    $url = '/api/staff/checkpoint-scanner/plan?'.http_build_query(['event_id' => $event->id, 'category_id' => $category->id]);
    $plan = $this->getJson($url, $headers)->assertOk()->json('data');
    $input = ['scan_mode' => 'checkpoint', 'event_id' => $event->id, 'category_id' => $category->id,
        'checkpoint_id' => $plan['checkpoints'][0]['id'], 'plan_fingerprint' => $plan['plan_fingerprint'],
        'client_scan_id' => (string) Str::uuid(), 'token' => app(FinishScanToken::class)->issue($registration)];
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    $scan = CheckpointScan::sole()->getRawOriginal();
    cancelSyntheticEvent($admin, $event);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertOk()->assertJsonPath('idempotent_replay', true);
    $new = array_replace($input, ['client_scan_id' => (string) Str::uuid(), 'checkpoint_id' => $plan['checkpoints'][1]['id']]);
    $this->postJson('/api/staff/checkpoint-scans', $new, $headers)->assertConflict()->assertJsonPath('code', 'event_cancelled');
    $this->getJson($url, $headers)->assertConflict()->assertJsonPath('code', 'event_cancelled');
    expect(CheckpointScan::count())->toBe(1)->and(CheckpointScan::sole()->getRawOriginal())->toBe($scan);
});

test('accepted finish retries survive cancellation but new online and queued finish captures are rejected', function () {
    [$admin, $manager, $event, , $registration] = cancellationFixture(true);
    $headers = cancellationHeaders($manager);
    $input = ['token' => app(FinishScanToken::class)->issue($registration), 'client_scan_id' => (string) Str::uuid()];
    $this->postJson('/api/staff/finish-scans', $input, $headers)->assertCreated();
    $before = FinishScan::sole()->getRawOriginal();
    cancelSyntheticEvent($admin, $event);
    $this->postJson('/api/staff/finish-scans', $input, $headers)->assertOk()->assertJsonPath('idempotent_replay', true);
    $this->postJson('/api/staff/finish-scans', array_replace($input, ['client_scan_id' => (string) Str::uuid()]), $headers)
        ->assertConflict()->assertJsonPath('code', 'event_cancelled');
    $this->postJson('/api/staff/finish-scans', array_replace($input, ['client_scan_id' => (string) Str::uuid(),
        'captured_offline' => true, 'captured_at' => now()->subMinutes(5)->toIso8601String()]), $headers)
        ->assertConflict()->assertJsonPath('code', 'event_cancelled');
    expect(FinishScan::count())->toBe(1)->and(FinishScan::sole()->getRawOriginal())->toBe($before);
});

test('cancellation cannot be bypassed by editing or submitting an existing draft revision', function () {
    [$admin, $manager, $event] = cancellationFixture();
    $draft = app(EventReviewWorkflow::class)->startRevision($event, $manager, $event->review_version);
    cancelSyntheticEvent($admin, $event);
    expect($event->fresh()->canEditSetup($manager))->toBeFalse()->and($draft->fresh()->canEditSetup($manager))->toBeFalse();
    $this->actingAs($manager)->get(route('admin.events.edit', $draft))->assertForbidden();
    $this->post(route('admin.events.review', [$draft, 'submit']), ['review_version' => $draft->review_version])->assertConflict();
    $this->post(route('admin.events.revision', $event), ['review_version' => $event->review_version])->assertConflict();
    $this->get(route('admin.events.show', $draft))->assertOk()->assertSee('Unsafe weather at the venue.')
        ->assertDontSee('Submit for Review')->assertDontSee('Withdraw to Edit');
});

test('mobile discovery hides cancellation while activity retains honest registration history and a cancellation notice', function () {
    [$admin, , $event, $category, $registration] = cancellationFixture();
    $headers = cancellationHeaders($registration->user);
    cancelSyntheticEvent($admin, $event);
    $this->getJson('/api/events', $headers)->assertOk()->assertJsonMissing(['id' => $event->id, 'title' => $event->title]);
    $this->getJson('/api/events/'.$event->id, $headers)->assertOk()
        ->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.can_register', false)
        ->assertJsonPath('data.category_registration_states.0.can_register', false)
        ->assertJsonPath('data.cancellation_reason', 'Unsafe weather at the venue.')
        ->assertJsonPath('data.payment_options', []);
    $this->getJson('/api/my-registrations', $headers)->assertOk()
        ->assertJsonPath('data.0.status', 'checked_in')->assertJsonPath('data.0.event.status', 'cancelled');
    $readiness = app(RegistrationReadiness::class)->for($registration->fresh());
    expect($readiness['overall_status'])->toBe('cancelled')->and($readiness['is_ready_for_event_day'])->toBeFalse()
        ->and($readiness['next_action']['key'])->toBe('event_cancelled')
        ->and($category->fresh()->resultsDisplayStatus())->toBe(Category::RESULTS_STATUS_CANCELLED);
});

test('the Admin cancellation action and required confirmation are absent for Event Managers', function () {
    [$admin, $manager, $event] = cancellationFixture();
    $this->actingAs($admin)->get(route('admin.events.index'))->assertOk()
        ->assertSee('Cancel event')->assertSee('name="cancellation_reason"', false)
        ->assertSee('name="confirmation"', false)->assertSee('No automatic refund is issued.')
        ->assertSee($event->title)->assertDontSee('name="confirmation_title"', false)->assertDontSee('Type the exact event title');
    $this->actingAs($manager)->get(route('admin.events.index'))->assertOk()->assertDontSee('data-event-cleanup-open="event-cancel-', false);
    cancelSyntheticEvent($admin, $event);
    $this->actingAs($admin)->get(route('admin.events.index', ['status' => 'cancelled']))->assertOk()->assertSee('Cancelled');
    $this->get(route('admin.events.show', $event))->assertOk()->assertSee('Unsafe weather at the venue.');
});

test('cancellation metadata migration rolls back only when there is no cancellation history', function () {
    $migration = require database_path('migrations/2026_11_20_000001_add_event_cancellation_metadata.php');
    $migration->down();
    expect(app(EventCancellation::class)->available())->toBeFalse();
    $migration->up();
    expect(app(EventCancellation::class)->available())->toBeTrue();
    [$admin, , $event] = cancellationFixture();
    cancelSyntheticEvent($admin, $event);
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Cancellation history must be preserved');
    expect($event->fresh()->isCancelled())->toBeTrue()->and(app(EventCancellation::class)->available())->toBeTrue();
});

test('automatic archiving never reclassifies a cancelled event as completed', function () {
    [$admin, , $event] = cancellationFixture();
    config(['event_archiving.enabled' => true, 'event_archiving.activated_at' => '2026-11-19T00:00:00+08:00']);
    cancelSyntheticEvent($admin, $event);
    $this->travelTo(now()->addMonths(3));
    $this->artisan('events:archive-completed')->assertSuccessful();
    expect($event->fresh()->isCancelled())->toBeTrue()->and($event->fresh()->archived_at)->not->toBeNull();
});

test('a cancelled event rejects its first finish capture and preserves the eligible participant', function () {
    [$admin, $manager, $event, , $registration] = cancellationFixture(true);
    $headers = cancellationHeaders($manager);
    cancelSyntheticEvent($admin, $event);
    $this->postJson('/api/staff/finish-scans', ['token' => app(FinishScanToken::class)->issue($registration),
        'client_scan_id' => (string) Str::uuid()], $headers)->assertConflict()->assertJsonPath('code', 'event_cancelled');
    expect(FinishScan::count())->toBe(0)->and($registration->fresh()->status)->toBe('checked_in');
});
