<?php

use App\Models\BannedIP;
use App\Models\Category;
use App\Models\CheckpointScan;
use App\Models\CheckpointScanPlan;
use App\Models\Event;
use App\Models\FinishScan;
use App\Models\RaceResult;
use App\Models\Registration;
use App\Models\User;
use App\Services\FinishScanToken;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['checkpoint_scanning.enabled' => true]);
    Http::preventStrayRequests();
});

function recordingCourse(): array
{
    return ['version' => 1,
        'path' => [['lat' => 14.46, 'lng' => 120.96], ['lat' => 14.48, 'lng' => 120.98]],
        'checkpoints' => [['lat' => 14.465, 'lng' => 120.965, 'label' => 'Water station'],
            ['lat' => 14.475, 'lng' => 120.975, 'label' => 'Turnaround']]];
}

function recordingFixture(): array
{
    test()->travelTo(Carbon::parse('2026-11-10 05:00:00', config('app.timezone')));
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $date = today()->addDay()->toDateString();
    $event = Event::create(['title' => 'Synthetic checkpoint race', 'slug' => 'checkpoint-recording-'.Str::uuid(),
        'description' => 'Synthetic checkpoint recording fixture.', 'venue' => 'Synthetic assembly point',
        'event_date' => $date, 'event_end_date' => $date, 'start_time' => '06:00', 'end_time' => '12:00',
        'registration_deadline' => $date, 'interest_type' => 'Marathon', 'type_details' => ['cutoff_time' => '6 hours'],
        'banner_image' => 'events/synthetic.png', 'manager_id' => $manager->id]);
    $category = $event->categories()->create(['name' => 'Synthetic 10K', 'distance_km' => 10,
        'status' => 'open', 'slot_limit' => 100, 'price_cents' => 0, 'course_map' => recordingCourse()]);
    $event = approveEventSetupForTest($event);
    test()->travelTo(Carbon::parse($date.' 08:30:00', config('app.timezone')));
    $category->update(['started_at' => now()->subMinutes(30)]);
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $registration = Registration::create(['user_id' => $runner->id, 'event_id' => $event->id,
        'category_id' => $category->id, 'bib_number' => '501', 'status' => 'checked_in', 'registered_at' => now()->subDay()]);

    return [$manager, $event->fresh(), $category->fresh(), $registration];
}

function recordingHeaders(User $staff): array
{
    $token = 'checkpoint-recording-test-'.bin2hex(random_bytes(24));
    $staff->forceFill(['api_token' => hash('sha256', $token), 'api_token_expires_at' => now()->addHour()])->save();

    return ['Authorization' => 'Bearer '.$token];
}

function recordingPlanUrl(Event $event, Category $category): string
{
    return '/api/staff/checkpoint-scanner/plan?'.http_build_query(['event_id' => $event->id, 'category_id' => $category->id]);
}

function recordingRequest(User $staff, Event $event, Category $category, Registration $registration): array
{
    $headers = recordingHeaders($staff);
    $plan = test()->getJson(recordingPlanUrl($event, $category), $headers)->assertOk()->json('data');

    return [$headers, ['scan_mode' => 'checkpoint', 'event_id' => $event->id, 'category_id' => $category->id,
        'checkpoint_id' => $plan['checkpoints'][0]['id'], 'plan_fingerprint' => $plan['plan_fingerprint'],
        'token' => app(FinishScanToken::class)->issue($registration), 'client_scan_id' => (string) Str::uuid()]];
}

test('the current approved plan is read-only and does not expose route coordinates or create history', function () {
    [$staff, $event, $category] = recordingFixture();
    $before = $category->courseCheckpoints()->get()->toArray();
    $response = $this->getJson(recordingPlanUrl($event, $category), recordingHeaders($staff))->assertOk()
        ->assertJsonPath('data.category_started', true)->assertJsonPath('data.recording_mode', 'online')
        ->assertJsonPath('data.checkpoints', [['id' => $before[0]['id'], 'position' => 1, 'label' => 'Water station'],
            ['id' => $before[1]['id'], 'position' => 2, 'label' => 'Turnaround']]);
    expect($response->json('data.plan_fingerprint'))->toMatch('/^[a-f0-9]{64}$/')
        ->and($response->headers->get('Cache-Control'))->toContain('private', 'no-store')
        ->and($response->headers->get('Vary'))->toBe('Authorization')
        ->and($category->courseCheckpoints()->get()->toArray())->toBe($before)
        ->and(CheckpointScanPlan::count())->toBe(0)->and(CheckpointScan::count())->toBe(0);
});

test('one online checkpoint visit uses server time without changing any existing business records', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $tables = array_values(array_diff(Schema::getTableListing(schemaQualified: false), ['checkpoint_scans', 'checkpoint_scan_plans']));
    $before = [];
    foreach ($tables as $table) {
        $before[$table] = DB::table($table)->get()->toJson();
    }
    $response = $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated()
        ->assertJsonPath('idempotent_replay', false)->assertJsonPath('data.scan_type', 'checkpoint')
        ->assertJsonPath('data.recording_mode', 'online')->assertJsonPath('data.registration_id', $registration->id)
        ->assertJsonPath('data.checkpoint_id', $input['checkpoint_id'])->assertJsonPath('data.checkpoint_label', 'Water station')
        ->assertJsonPath('data.checkpoint_position', 1)->assertJsonPath('data.elapsed_seconds', 1800)
        ->assertJsonPath('data.scanned_by.id', $staff->id)->assertJsonPath('data.after_cutoff', false)
        ->assertJsonMissingPath('data.token')->assertJsonMissingPath('data.request_fingerprint');
    $scan = CheckpointScan::sole();
    expect($scan->scanned_at->timestamp)->toBe(now()->timestamp)
        ->and($registration->fresh()->status)->toBe('checked_in')
        ->and(CheckpointScanPlan::count())->toBe(1)
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    foreach ($before as $table => $contents) {
        expect(DB::table($table)->get()->toJson(), $table)->toBe($contents);
    }
    Http::assertNothingSent();
});

test('recording is disabled by default and never writes while disabled', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    config(['checkpoint_scanning.enabled' => false]);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertStatus(503)->assertJsonPath('code', 'checkpoint_recording_disabled');
    $this->getJson(recordingPlanUrl($event, $category), $headers)->assertStatus(503);
    expect(CheckpointScan::count())->toBe(0)->and(CheckpointScanPlan::count())->toBe(0);
    $config = require base_path('config/checkpoint_scanning.php');
    expect($config['enabled'])->toBeFalse();
});

test('missing history schema fails closed without running migrations', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    Schema::drop('checkpoint_scans');
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertStatus(503)->assertJsonPath('code', 'checkpoint_recording_unavailable');
    expect(Schema::hasTable('checkpoint_scans'))->toBeFalse()->and(CheckpointScanPlan::count())->toBe(0);
});

test('missing or expired bearer credentials cannot record visits', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->postJson('/api/staff/checkpoint-scans', $input)->assertUnauthorized();
    $this->postJson('/api/staff/checkpoint-scans', $input, ['Authorization' => 'Bearer invalid-test-token'])->assertUnauthorized();
    $staff->update(['api_token_expires_at' => now()->subMinute()]);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertUnauthorized();
    expect(CheckpointScan::count())->toBe(0);
});

test('nonstaff roles cannot record or request a plan', function (string $role) {
    [$staff, $event, $category, $registration] = recordingFixture();
    [, $input] = recordingRequest($staff, $event, $category, $registration);
    $other = User::factory()->create(['role' => $role]);
    $headers = recordingHeaders($other);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertForbidden()->assertJsonPath('code', 'checkpoint_scanner_forbidden');
    $this->getJson(recordingPlanUrl($event, $category), $headers)->assertForbidden();
    expect(CheckpointScan::count())->toBe(0);
})->with(['runner', 'user', 'executive', 'content_moderator']);

test('unverified suspended and banned staff retain their access protections', function (string $field, int $status) {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $staff->forceFill([$field => $field === 'email_verified_at' ? null : now()])->save();
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertStatus($status);
    expect(CheckpointScan::count())->toBe(0);
})->with([['email_verified_at', 403], ['suspended_at', 423], ['banned_at', 403]]);

test('blocked IP addresses cannot reach checkpoint recording', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    BannedIP::create(['ip_address' => '127.0.0.1', 'reason' => 'Synthetic block']);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertForbidden();
    expect(CheckpointScan::count())->toBe(0);
});

test('staff authorization is rechecked after bearer middleware', function (string $change, int $status) {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $armed = true;
    DB::listen(function (QueryExecuted $query) use (&$armed, $staff, $change) {
        if ($armed && str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, 'api_token') && str_contains($query->sql, 'users')) {
            $armed = false;
            DB::table('users')->where('id', $staff->id)->update(match ($change) {
                'token' => ['api_token' => hash('sha256', 'revoked-test-token')],
                'role' => ['role' => 'runner'],
                default => ['suspended_at' => now()],
            });
        }
    });
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertStatus($status);
    expect($armed)->toBeFalse()->and(CheckpointScan::count())->toBe(0);
})->with([['token', 401], ['role', 403], ['suspended', 423]]);

test('another manager cannot record or replay another event and reassignment revokes access', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $other = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $otherHeaders = recordingHeaders($other);
    $this->postJson('/api/staff/checkpoint-scans', $input, $otherHeaders)->assertForbidden()->assertJsonPath('code', 'forbidden_event')->assertJsonMissingPath('data');
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    $event->update(['manager_id' => $other->id]);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertForbidden();
    expect(CheckpointScan::count())->toBe(1);
});

test('verified Admin may record an approved event assigned to another manager', function () {
    [, $event, $category, $registration] = recordingFixture();
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    [$headers, $input] = recordingRequest($admin, $event, $category, $registration);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated()->assertJsonPath('data.scanned_by.id', $admin->id);
});

test('legacy admin retains assigned-manager scope rather than global access', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    $staff->update(['role' => User::ROLE_LEGACY_ADMIN]);
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
});

test('required selection and request fields cannot be omitted', function (string $field) {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    unset($input[$field]);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(CheckpointScan::count())->toBe(0)->and(CheckpointScanPlan::count())->toBe(0);
})->with(['scan_mode', 'event_id', 'category_id', 'checkpoint_id', 'token', 'client_scan_id', 'plan_fingerprint']);

test('offline capture timestamps spoofed identities and lap data are rejected', function (string $field, mixed $value) {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $input[$field] = $value;
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(CheckpointScan::count())->toBe(0)->and(CheckpointScanPlan::count())->toBe(0);
})->with([['captured_offline', true], ['captured_offline', false], ['captured_offline', null], ['captured_at', '2026-11-10T01:00:00Z'],
    ['scanned_at', '2026-11-10T01:00:00Z'], ['elapsed_seconds', 1], ['scanned_by_user_id', 999],
    ['registration_id', 999], ['lap_number', 2], ['pass_number', 2]]);

test('malformed fields and finish mode never reach a checkpoint write', function (string $field, mixed $value) {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $input[$field] = $value;
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(CheckpointScan::count())->toBe(0);
})->with([['scan_mode', 'finish'], ['checkpoint_id', 0], ['checkpoint_id', [1]], ['category_id', -1],
    ['event_id', 'invalid'], ['client_scan_id', 'not-a-uuid'], ['plan_fingerprint', 'not-a-fingerprint']]);

test('tampered signed BIB data is rejected without saving a plan', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $input['token'] .= 'tampered';
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertUnprocessable()->assertJsonPath('code', 'invalid_scan_token');
    expect(CheckpointScanPlan::count())->toBe(0);
});

test('a signed BIB from a different category cannot use the selected checkpoint', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $other = $event->categories()->create(['name' => 'Other category', 'distance_km' => 5, 'status' => 'open', 'course_map' => recordingCourse()]);
    $registration->update(['category_id' => $other->id]);
    $input['token'] = app(FinishScanToken::class)->issue($registration->fresh());
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertUnprocessable()->assertJsonPath('code', 'selection_mismatch');
    expect(CheckpointScan::count())->toBe(0);
});

test('a checkpoint from another category is never substituted for the selected one', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $other = $event->categories()->create(['name' => 'Other route', 'distance_km' => 5, 'status' => 'open', 'course_map' => recordingCourse()]);
    $input['checkpoint_id'] = $other->activeCourseCheckpoints()->first()->id;
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertStatus(409)->assertJsonPath('code', 'checkpoint_not_available');
    expect(CheckpointScanPlan::count())->toBe(0);
});

test('draft pending returned discarded and legacy events cannot receive visits', function ($status) {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    DB::table('events')->where('id', $event->id)->update(['review_status' => $status]);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertStatus(409)->assertJsonPath('code', 'event_not_approved');
    expect(CheckpointScan::count())->toBe(0);
})->with([null, 'draft', 'pending_review', 'changes_requested', 'discarded']);

test('an approved revision copy is not a live checkpoint plan', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    DB::table('events')->where('id', $event->id)->update(['revision_of_id' => $event->id]);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertStatus(409)->assertJsonPath('code', 'event_not_approved');
});

test('registration eligibility does not silently complete or check in a participant', function (string $status, string $code) {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $registration->update(['status' => $status]);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertStatus(409)->assertJsonPath('code', $code);
    expect($registration->fresh()->status)->toBe($status)->and(CheckpointScanPlan::count())->toBe(0);
})->with([['pending', 'participant_not_checked_in'], ['approved', 'participant_not_checked_in'],
    ['rejected', 'participant_not_checked_in'], ['completed', 'participant_already_finished']]);

test('a BIB assignment is required', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $registration->update(['bib_number' => null]);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertStatus(409)->assertJsonPath('code', 'bib_not_assigned');
    expect(CheckpointScanPlan::count())->toBe(0);
});

test('a missing or future official category start blocks new visits', function (bool $future) {
    [$staff, $event, $category, $registration] = recordingFixture();
    $category->update(['started_at' => $future ? now()->addMinute() : null]);
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertStatus(409)->assertJsonPath('code', 'category_not_started');
    expect(CheckpointScanPlan::count())->toBe(0);
})->with([false, true]);

test('registration closed categories can record visits but draft categories cannot', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    $category->update(['status' => 'closed']);
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    $category->update(['status' => 'draft']);
    $input['checkpoint_id'] = $category->activeCourseCheckpoints()->get()->last()->id;
    $input['client_scan_id'] = (string) Str::uuid();
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertStatus(409)->assertJsonPath('code', 'category_not_available');
    expect(CheckpointScan::count())->toBe(1);
});

test('exact UUID retries return the original visit and never change its timestamp', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $input['client_scan_id'] = strtoupper($input['client_scan_id']);
    $first = $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated()->json('data');
    $this->travel(2)->minutes();
    $input['client_scan_id'] = strtolower($input['client_scan_id']);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertOk()
        ->assertJsonPath('idempotent_replay', true)->assertJsonPath('data', $first);
    expect(CheckpointScan::count())->toBe(1)->and(CheckpointScanPlan::count())->toBe(1);
});

test('a reused UUID with a different checkpoint or staff identity is a conflict without data leakage', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    $different = [...$input, 'checkpoint_id' => $category->activeCourseCheckpoints()->get()->last()->id];
    $this->postJson('/api/staff/checkpoint-scans', $different, $headers)->assertStatus(409)->assertJsonPath('code', 'client_scan_conflict')->assertJsonMissingPath('data');
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->postJson('/api/staff/checkpoint-scans', $input, recordingHeaders($admin))->assertStatus(409)->assertJsonPath('code', 'client_scan_conflict')->assertJsonMissingPath('data');
    expect(CheckpointScan::count())->toBe(1);
});

test('a new UUID cannot duplicate a participant checkpoint visit', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    $input['client_scan_id'] = (string) Str::uuid();
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertStatus(409)->assertJsonPath('code', 'checkpoint_already_recorded');
    expect(CheckpointScan::count())->toBe(1);
});

test('each checkpoint is independent evidence rather than mandatory order or automatic disqualification', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $second = [...$input, 'checkpoint_id' => $category->activeCourseCheckpoints()->get()->last()->id];
    $this->postJson('/api/staff/checkpoint-scans', $second, $headers)->assertCreated()->assertJsonPath('data.checkpoint_position', 2);
    $input['client_scan_id'] = (string) Str::uuid();
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated()->assertJsonPath('data.checkpoint_position', 1);
    expect(CheckpointScan::count())->toBe(2)->and(CheckpointScanPlan::count())->toBe(1)
        ->and($registration->fresh()->status)->toBe('checked_in');
});

test('late server-timed visits are flagged but do not automatically fail a participant', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->travelTo(Carbon::parse($event->event_date->toDateString().' 13:00:00', config('app.timezone')));
    $staff->update(['api_token_expires_at' => now()->addHour()]);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated()->assertJsonPath('data.after_cutoff', true);
    expect($registration->fresh()->status)->toBe('checked_in');
});

test('a stale route selection requires a new plan before the first visit', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $map = recordingCourse();
    $map['checkpoints'][0]['label'] = 'Reviewed water station';
    $category->update(['course_map' => $map]);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertStatus(409)->assertJsonPath('code', 'stale_checkpoint_plan');
    expect(CheckpointScanPlan::count())->toBe(0);
    $input['plan_fingerprint'] = $this->getJson(recordingPlanUrl($event, $category), $headers)->assertOk()->json('data.plan_fingerprint');
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated()->assertJsonPath('data.checkpoint_label', 'Reviewed water station');
});

test('a route or start change after the first visit freezes further recording without rewriting history', function (string $change) {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $first = $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated()->json('data');
    $before = CheckpointScanPlan::sole()->toArray();
    if ($change === 'route') {
        $map = recordingCourse();
        $map['checkpoints'] = array_reverse($map['checkpoints']);
        $map['checkpoints'][1]['label'] = 'Changed after capture';
        $category->update(['course_map' => $map]);
    } else {
        $category->update(['started_at' => $category->started_at->copy()->subMinute()]);
    }
    $this->getJson(recordingPlanUrl($event, $category), $headers)->assertStatus(409)->assertJsonPath('code', 'checkpoint_plan_changed');
    $this->postJson('/api/staff/checkpoint-scans', [...$input, 'client_scan_id' => (string) Str::uuid()], $headers)
        ->assertStatus(409)->assertJsonPath('code', 'checkpoint_plan_changed');
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertOk()->assertJsonPath('data', $first);
    expect(CheckpointScanPlan::sole()->toArray())->toBe($before)->and(CheckpointScan::count())->toBe(1);
})->with(['route', 'start']);

test('invalid or unsynchronized routes are not repaired by reading or recording', function (string $change) {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $before = $category->courseCheckpoints()->get()->toArray();
    if ($change === 'invalid') {
        DB::table('categories')->where('id', $category->id)->update(['course_map' => '{invalid']);
    } else {
        DB::table('category_course_checkpoints')->where('id', $input['checkpoint_id'])->update(['label' => 'Out of sync']);
        $before = $category->courseCheckpoints()->get()->toArray();
    }
    $this->getJson(recordingPlanUrl($event, $category), $headers)->assertStatus(409)->assertJsonPath('code', 'checkpoint_list_not_ready');
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertStatus(409)->assertJsonPath('code', 'checkpoint_list_not_ready');
    expect($category->courseCheckpoints()->get()->toArray())->toBe($before)->and(CheckpointScanPlan::count())->toBe(0);
})->with(['invalid', 'unsynchronized']);

test('checkpoint capture and finish capture remain separate and accepted retries survive a later finish', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    expect(DB::table('finish_scans')->count())->toBe(0);
    $this->postJson('/api/staff/finish-scans', ['token' => $input['token']], $headers)->assertCreated();
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertOk()->assertJsonPath('idempotent_replay', true);
    $this->postJson('/api/staff/checkpoint-scans', [...$input, 'client_scan_id' => (string) Str::uuid(),
        'checkpoint_id' => $category->activeCourseCheckpoints()->get()->last()->id], $headers)
        ->assertStatus(409)->assertJsonPath('code', 'participant_already_finished');
    expect(CheckpointScan::count())->toBe(1)->and(DB::table('finish_scans')->count())->toBe(1)
        ->and(DB::table('race_results')->count())->toBe(0)->and(DB::table('certificates')->count())->toBe(0)
        ->and($registration->fresh()->status)->toBe('checked_in');
});

test('recorded visits and plans are append-only through models', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    expect(fn () => CheckpointScan::sole()->update(['checkpoint_label' => 'Overwritten']))->toThrow(LogicException::class)
        ->and(fn () => CheckpointScan::sole()->delete())->toThrow(LogicException::class)
        ->and(fn () => CheckpointScanPlan::sole()->update(['fingerprint' => str_repeat('0', 64)]))->toThrow(LogicException::class)
        ->and(fn () => CheckpointScanPlan::sole()->delete())->toThrow(LogicException::class);
});

test('history guards prevent destructive model cascades with a validation message', function (string $parent) {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    $model = match ($parent) {
        'event' => $event, 'category' => $category, 'registration' => $registration,
        'participant' => $registration->user, default => $category->activeCourseCheckpoints()->first(),
    };
    expect(fn () => $model->delete())->toThrow(ValidationException::class)
        ->and(CheckpointScan::count())->toBe(1)->and(CheckpointScanPlan::count())->toBe(1);
})->with(['event', 'category', 'registration', 'participant', 'checkpoint']);

test('database constraints protect history even when model deletion observers are bypassed', function (string $table) {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    $id = match ($table) {
        'events' => $event->id, 'categories' => $category->id, 'registrations' => $registration->id,
        'checkpoint_scan_plans' => CheckpointScanPlan::sole()->id, default => $input['checkpoint_id'],
    };
    expect(fn () => DB::table($table)->where('id', $id)->delete())->toThrow(QueryException::class)
        ->and(CheckpointScan::count())->toBe(1);
})->with(['events', 'categories', 'registrations', 'category_course_checkpoints', 'checkpoint_scan_plans']);

test('deleting a former staff account retains recorded evidence and its original staff name', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    $name = $staff->name;
    $staff->delete();
    expect(CheckpointScan::sole()->scanned_by_user_id)->toBeNull()
        ->and(CheckpointScan::sole()->scanned_by_name)->toBe($name)->and(CheckpointScanPlan::count())->toBe(1);
});

test('populated checkpoint history cannot be dropped by migration rollback', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    $migration = require database_path('migrations/2026_11_10_000001_create_checkpoint_scan_history_tables.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class)
        ->and(Schema::hasTable('checkpoint_scans'))->toBeTrue()->and(CheckpointScan::count())->toBe(1);
});

test('checkpoint history has no update or delete HTTP operations', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    $this->patchJson('/api/staff/checkpoint-scans', [], $headers)->assertStatus(405);
    $this->deleteJson('/api/staff/checkpoint-scans', [], $headers)->assertStatus(405);
    expect(CheckpointScan::count())->toBe(1);
});

test('the original read-only selector cannot silently enable recording', function () {
    [$staff, $event, $category] = recordingFixture();
    $this->getJson('/api/staff/checkpoint-scanner/context?'.http_build_query([
        'event_id' => $event->id, 'category_id' => $category->id,
    ]), recordingHeaders($staff))->assertOk()
        ->assertJsonPath('meta.read_only', true)->assertJsonPath('meta.scan_recording_available', false);
    expect(CheckpointScan::count())->toBe(0)->and(CheckpointScanPlan::count())->toBe(0);
});

test('an image alone or an empty intermediate checkpoint list cannot enable recording', function (bool $imageOnly) {
    [$staff, $event, $category] = recordingFixture();
    $map = recordingCourse();
    $map['checkpoints'] = [];
    $category->update(['course_map' => $imageOnly ? null : $map,
        'checkpoint_map_image' => 'categories/checkpoint-maps/synthetic.png']);
    $this->getJson(recordingPlanUrl($event, $category), recordingHeaders($staff))
        ->assertStatus(409)->assertJsonPath('code', 'checkpoint_list_not_ready');
    expect(CheckpointScanPlan::count())->toBe(0);
})->with([false, true]);

test('an old checkpoint ID cannot be reused after its location is retired', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $map = recordingCourse();
    $map['checkpoints'][0]['lat'] += 0.001;
    $category->update(['course_map' => $map]);
    $input['plan_fingerprint'] = $this->getJson(recordingPlanUrl($event, $category), $headers)
        ->assertOk()->json('data.plan_fingerprint');
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)
        ->assertStatus(409)->assertJsonPath('code', 'checkpoint_not_available');
    expect($category->courseCheckpoints()->find($input['checkpoint_id'])->retired_at)->not->toBeNull()
        ->and(CheckpointScanPlan::count())->toBe(0);
});

test('category and event selection must refer to the same approved live setup', function (string $change, int $status, string $code) {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    if ($change === 'stored_draft') {
        DB::table('events')->where('id', $event->id)->update(['status' => 'draft']);
    } elseif ($change === 'unknown_event') {
        $input['event_id'] = 999999;
    } elseif ($change === 'unknown_category') {
        $input['category_id'] = 999999;
    } else {
        $other = Event::create(['title' => 'Unrelated event', 'slug' => 'unrelated-'.Str::uuid(),
            'description' => 'Synthetic', 'venue' => 'Synthetic', 'event_date' => today(), 'manager_id' => $staff->id]);
        $category->update(['event_id' => $other->id]);
    }
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertStatus($status)->assertJsonPath('code', $code);
    expect(CheckpointScan::count())->toBe(0)->and(CheckpointScanPlan::count())->toBe(0);
})->with([
    ['stored_draft', 409, 'event_not_approved'], ['unknown_event', 403, 'forbidden_event'],
    ['unknown_category', 409, 'category_not_available'], ['wrong_parent', 409, 'category_not_available'],
]);

test('starting a category invalidates a pre-start plan and requires an explicit refresh', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    $category->update(['started_at' => null]);
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->getJson(recordingPlanUrl($event, $category), $headers)->assertOk()->assertJsonPath('data.category_started', false);
    $category->update(['started_at' => now()->subMinute()]);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)
        ->assertStatus(409)->assertJsonPath('code', 'stale_checkpoint_plan');
    $input['plan_fingerprint'] = $this->getJson(recordingPlanUrl($event, $category), $headers)->assertOk()->json('data.plan_fingerprint');
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated()->assertJsonPath('data.elapsed_seconds', 60);
});

test('an official result blocks a new visit even when a registration still says checked in', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    RaceResult::create(['registration_id' => $registration->id, 'user_id' => $registration->user_id,
        'event_id' => $event->id, 'category_id' => $category->id, 'finish_time' => '00:25:00']);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)
        ->assertStatus(409)->assertJsonPath('code', 'participant_already_finished');
    expect(CheckpointScan::count())->toBe(0)->and(CheckpointScanPlan::count())->toBe(0)
        ->and($registration->fresh()->status)->toBe('checked_in');
});

test('the signed registration tuple is rechecked after resolving the BIB QR', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $other = $event->categories()->create(['name' => 'Moved registration', 'distance_km' => 5, 'status' => 'open']);
    $armed = true;
    DB::listen(function (QueryExecuted $query) use (&$armed, $registration, $other) {
        if ($armed && str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, 'registrations')
            && str_contains($query->sql, 'category_id')) {
            $armed = false;
            DB::table('registrations')->where('id', $registration->id)->update(['category_id' => $other->id]);
        }
    });
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertUnprocessable()->assertJsonPath('code', 'invalid_scan_token');
    expect($armed)->toBeFalse()->and(CheckpointScan::count())->toBe(0)->and(CheckpointScanPlan::count())->toBe(0);
});

test('a UUID used for another participant never returns that participants record', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    $other = Registration::create(['user_id' => User::factory()->create()->id, 'event_id' => $event->id,
        'category_id' => $category->id, 'status' => 'checked_in', 'bib_number' => '502', 'registered_at' => now()]);
    $input['token'] = app(FinishScanToken::class)->issue($other);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertStatus(409)
        ->assertJsonPath('code', 'client_scan_conflict')->assertJsonMissingPath('data');
    expect(CheckpointScan::count())->toBe(1);
});

test('a unique insert race rolls back its new plan and retries without orphan history', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $attempts = 0;
    CheckpointScan::creating(function () use (&$attempts) {
        $attempts++;
        if ($attempts === 1) {
            throw new UniqueConstraintViolationException('sqlite', 'synthetic concurrent insert', [], new RuntimeException('Synthetic collision'));
        }
    });
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    expect($attempts)->toBe(2)->and(CheckpointScan::count())->toBe(1)->and(CheckpointScanPlan::count())->toBe(1)
        ->and(CheckpointScan::sole()->checkpoint_scan_plan_id)->toBe(CheckpointScanPlan::sole()->id);
});

test('stored uniqueness constraints reject duplicate UUIDs and duplicate participant visits', function (bool $sameUuid) {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    $row = (array) DB::table('checkpoint_scans')->first();
    unset($row['id']);
    if ($sameUuid) {
        $row['checkpoint_id'] = $category->activeCourseCheckpoints()->get()->last()->id;
    } else {
        $row['client_scan_id'] = (string) Str::uuid();
    }
    expect(fn () => DB::table('checkpoint_scans')->insert($row))->toThrow(UniqueConstraintViolationException::class)
        ->and(CheckpointScan::count())->toBe(1);
})->with([false, true]);

test('checkpoint history deletion errors are returned safely through the admin web flow', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->actingAs($admin)->from(route('admin.users.index'))->delete(route('admin.users.destroy', $registration->user))
        ->assertRedirect(route('admin.users.index'))->assertSessionHasErrors('checkpoint_history');
    expect($registration->user()->exists())->toBeTrue()->and(CheckpointScan::count())->toBe(1);
});

test('finish and result eligibility checks use current locking reads', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $locks = ['result' => [], 'finish' => []];
    RaceResult::addGlobalScope('checkpoint-current-read', function (Builder $query) use (&$locks) {
        $locks['result'][] = $query->getQuery()->lock;
    });
    FinishScan::addGlobalScope('checkpoint-current-read', function (Builder $query) use (&$locks) {
        $locks['finish'][] = $query->getQuery()->lock;
    });
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    expect($locks)->toBe(['result' => [true], 'finish' => [true]]);
});

test('checkpoint parent locks protect setup without blocking the existing finish foreign keys', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
    $locks = ['staff' => [], 'event' => [], 'category' => []];
    foreach (['staff' => User::class, 'event' => Event::class, 'category' => Category::class] as $label => $model) {
        $model::addGlobalScope('checkpoint-parent-lock', function (Builder $query) use (&$locks, $label) {
            if ($query->getQuery()->lock !== null) {
                $locks[$label][] = $query->getQuery()->lock;
            }
        });
    }
    $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated();
    expect($locks)->toBe(['staff' => [false], 'event' => [false], 'category' => [false]]);
});

test('the empty additive migration can be reversed without dropping existing business tables', function () {
    [$staff, $event, $category, $registration] = recordingFixture();
    $migration = require database_path('migrations/2026_11_10_000001_create_checkpoint_scan_history_tables.php');
    $tables = array_values(array_diff(Schema::getTableListing(schemaQualified: false), ['checkpoint_scans', 'checkpoint_scan_plans']));
    $before = [];
    foreach ($tables as $table) {
        $before[$table] = DB::table($table)->get()->toJson();
    }
    $migration->down();
    expect(Schema::hasTable('checkpoint_scans'))->toBeFalse()->and(Schema::hasTable('checkpoint_scan_plans'))->toBeFalse();
    foreach ($before as $table => $contents) {
        expect(DB::table($table)->get()->toJson(), $table)->toBe($contents);
    }
    $migration->up();
    expect(CheckpointScan::count())->toBe(0)->and(CheckpointScanPlan::count())->toBe(0);
});

test('server elapsed time and response offsets remain correct in the Philippine timezone', function () {
    $originalTimezone = date_default_timezone_get();
    try {
        config(['app.timezone' => 'Asia/Manila']);
        date_default_timezone_set('Asia/Manila');
        [$staff, $event, $category, $registration] = recordingFixture();
        [$headers, $input] = recordingRequest($staff, $event, $category, $registration);
        $this->postJson('/api/staff/checkpoint-scans', $input, $headers)->assertCreated()
            ->assertJsonPath('data.scanned_at', '2026-11-11T08:30:00+08:00')
            ->assertJsonPath('data.elapsed_seconds', 1800);
    } finally {
        date_default_timezone_set($originalTimezone);
    }
});
