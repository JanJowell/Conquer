<?php

use App\Exceptions\CheckpointScanException;
use App\Models\CheckpointScan;
use App\Models\CheckpointScanPlan;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\CheckpointScanRecorder;
use App\Services\FinishScanToken;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 11, 12)->setTime(9, 0));
    config(['checkpoint_scanning.enabled' => true, 'checkpoint_scanning.pilot_event_id' => null,
        'checkpoint_scanning.pilot_category_id' => null]);
    Http::preventStrayRequests();
});

function checkpointPilotFixture(?User $staff = null): array
{
    $staff ??= User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    // Admin scans an Event Manager's approved setup; Admin cannot submit it.
    $manager = $staff->managesAssignedEventsOnly() ? $staff : User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    // Multiple setups for one manager must still satisfy scheduling rules.
    $date = today()->addDays(Event::query()->count() + 1)->toDateString();
    $event = Event::create(['title' => 'Synthetic pilot '.Str::uuid(), 'slug' => 'pilot-'.Str::uuid(),
        'description' => 'Synthetic pilot restriction test.', 'venue' => 'Synthetic venue',
        'event_date' => $date, 'event_end_date' => $date, 'registration_deadline' => $date,
        'start_time' => '06:00', 'end_time' => '23:59', 'interest_type' => 'Marathon',
        'type_details' => ['cutoff_time' => '6 hours'], 'banner_image' => 'events/synthetic.png', 'manager_id' => $manager->id]);
    $category = $event->categories()->create(['name' => 'Synthetic 1K', 'distance_km' => 1, 'slot_limit' => 10,
        'price_cents' => 0, 'status' => 'open', 'course_map' => ['version' => 1,
            'path' => [['lat' => 14.46, 'lng' => 120.96], ['lat' => 14.48, 'lng' => 120.98]],
            'checkpoints' => [['lat' => 14.465, 'lng' => 120.965, 'label' => 'Synthetic checkpoint']]]]);
    $event = approveEventSetupForTest($event);
    $category->update(['started_at' => now()->subHour()]);
    $registration = Registration::create(['user_id' => User::factory()->create(['role' => User::ROLE_RUNNER])->id,
        'event_id' => $event->id, 'category_id' => $category->id, 'bib_number' => 'TEST-PILOT', 'status' => 'checked_in', 'registered_at' => now()]);
    $bearer = 'pilot-test-'.bin2hex(random_bytes(24));
    $staff->forceFill(['api_token' => hash('sha256', $bearer), 'api_token_expires_at' => now()->addHour()])->save();
    $fixture = ['staff' => $staff->fresh(), 'event' => $event->fresh(), 'category' => $category->fresh(), 'registration' => $registration,
        'headers' => ['Authorization' => 'Bearer '.$bearer]];

    return $fixture;
}

function checkpointPilotSelect(array $fixture): void
{
    config(['checkpoint_scanning.pilot_event_id' => $fixture['event']->id,
        'checkpoint_scanning.pilot_category_id' => $fixture['category']->id]);
}

function checkpointPilotUrl(array $fixture): string
{
    return '/api/staff/checkpoint-scanner/plan?'.http_build_query(['event_id' => $fixture['event']->id, 'category_id' => $fixture['category']->id]);
}

function checkpointPilotInput(array $fixture): array
{
    $plan = test()->getJson(checkpointPilotUrl($fixture), $fixture['headers'])->assertOk()->json('data');

    return ['scan_mode' => 'checkpoint', 'event_id' => $fixture['event']->id, 'category_id' => $fixture['category']->id,
        'checkpoint_id' => $plan['checkpoints'][0]['id'], 'plan_fingerprint' => $plan['plan_fingerprint'],
        'token' => app(FinishScanToken::class)->issue($fixture['registration']), 'client_scan_id' => (string) Str::uuid()];
}

test('pilot configuration defaults remain off and unselected', function () {
    $defaults = require base_path('config/checkpoint_scanning.php');
    expect($defaults['enabled'])->toBeFalse()->and($defaults['pilot_event_id'])->toBeNull()
        ->and($defaults['pilot_category_id'])->toBeNull();
});

test('enabling the master flag alone cannot open a checkpoint plan or store', function (string $missing) {
    $fixture = checkpointPilotFixture();
    checkpointPilotSelect($fixture);
    $input = checkpointPilotInput($fixture);
    foreach ($missing === 'both' ? ['pilot_event_id', 'pilot_category_id'] : [$missing] as $key) {
        config(['checkpoint_scanning.'.$key => null]);
    }
    $this->getJson(checkpointPilotUrl($fixture), $fixture['headers'])->assertStatus(503)
        ->assertJsonPath('code', 'checkpoint_recording_unavailable')->assertJsonMissingPath('data');
    $this->postJson('/api/staff/checkpoint-scans', $input, $fixture['headers'])->assertStatus(503)
        ->assertJsonPath('code', 'checkpoint_recording_unavailable')->assertJsonMissingPath('data');
    expect(CheckpointScanPlan::count())->toBe(0)->and(CheckpointScan::count())->toBe(0);
})->with(['both', 'pilot_event_id', 'pilot_category_id']);

test('another category or event is blocked for both manager and Admin', function (string $role, string $selection) {
    $fixture = checkpointPilotFixture(User::factory()->create(['role' => $role]));
    checkpointPilotSelect($fixture);
    $input = checkpointPilotInput($fixture);
    if ($selection === 'category') {
        $other = $fixture['event']->categories()->create(['name' => 'Other category', 'distance_km' => 2, 'status' => 'open']);
        $input['category_id'] = $other->id;
    } else {
        $other = checkpointPilotFixture($fixture['staff']);
        $fixture['headers'] = $other['headers']; // The existing token was replaced only in this synthetic fixture.
        $input['event_id'] = $other['event']->id;
        $input['category_id'] = $other['category']->id;
    }
    $url = '/api/staff/checkpoint-scanner/plan?'.http_build_query(['event_id' => $input['event_id'], 'category_id' => $input['category_id']]);
    foreach ([$this->getJson($url, $fixture['headers']), $this->postJson('/api/staff/checkpoint-scans', $input, $fixture['headers'])] as $response) {
        $response->assertForbidden()->assertJsonPath('code', 'checkpoint_pilot_forbidden')->assertJsonMissingPath('data');
        expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store')
            ->and($response->headers->get('Vary'))->toBe('Authorization');
    }
    expect(CheckpointScanPlan::count())->toBe(0)->and(CheckpointScan::count())->toBe(0);
})->with([User::ROLE_EVENT_MANAGER, User::ROLE_SUPER_ADMIN])->with(['category', 'event']);

test('pilot does not replace ordinary assignment approval start and BIB checks', function (string $restriction, int $status, string $code) {
    $fixture = checkpointPilotFixture();
    checkpointPilotSelect($fixture);
    $input = checkpointPilotInput($fixture);
    match ($restriction) {
        'assignment' => $fixture['event']->update(['manager_id' => User::factory()->create(['role' => User::ROLE_EVENT_MANAGER])->id]),
        'approval' => DB::table('events')->where('id', $fixture['event']->id)->update(['review_status' => 'pending_review']),
        'start' => $fixture['category']->update(['started_at' => null]),
        default => $fixture['registration']->update(['status' => 'approved']),
    };
    if ($restriction === 'start') {
        $input = checkpointPilotInput($fixture);
    }
    $this->postJson('/api/staff/checkpoint-scans', $input, $fixture['headers'])->assertStatus($status)->assertJsonPath('code', $code);
    expect(CheckpointScan::count())->toBe(0)->and(CheckpointScanPlan::count())->toBe(0);
})->with([['assignment', 403, 'forbidden_event'], ['approval', 409, 'event_not_approved'],
    ['start', 409, 'category_not_started'], ['registration', 409, 'participant_not_checked_in']]);

test('an exact pilot pair can create one visit and acknowledge an exact retry without finishing a participant', function () {
    $fixture = checkpointPilotFixture();
    checkpointPilotSelect($fixture);
    $input = checkpointPilotInput($fixture);
    $first = $this->postJson('/api/staff/checkpoint-scans', $input, $fixture['headers'])->assertCreated()->json('data');
    $this->postJson('/api/staff/checkpoint-scans', $input, $fixture['headers'])->assertOk()
        ->assertJsonPath('idempotent_replay', true)->assertJsonPath('data', $first);
    expect(CheckpointScan::count())->toBe(1)->and(CheckpointScanPlan::count())->toBe(1)
        ->and($fixture['registration']->fresh()->status)->toBe('checked_in')
        ->and(DB::table('finish_scans')->count())->toBe(0)->and(DB::table('race_results')->count())->toBe(0)
        ->and(DB::table('certificates')->count())->toBe(0);
});

test('clearing or changing pilot scope blocks old retries but preserves their stored evidence', function (string $change) {
    $fixture = checkpointPilotFixture();
    checkpointPilotSelect($fixture);
    $input = checkpointPilotInput($fixture);
    $this->postJson('/api/staff/checkpoint-scans', $input, $fixture['headers'])->assertCreated();
    $history = CheckpointScan::sole()->toArray();
    $plan = CheckpointScanPlan::sole()->toArray();
    config(['checkpoint_scanning.pilot_category_id' => $change === 'clear' ? null : $fixture['category']->id + 999]);
    $this->postJson('/api/staff/checkpoint-scans', $input, $fixture['headers'])->assertStatus($change === 'clear' ? 503 : 403)
        ->assertJsonMissingPath('data');
    expect(CheckpointScan::sole()->toArray())->toBe($history)->and(CheckpointScanPlan::sole()->toArray())->toBe($plan);
    checkpointPilotSelect($fixture);
    $this->postJson('/api/staff/checkpoint-scans', $input, $fixture['headers'])->assertOk()->assertJsonPath('idempotent_replay', true);
})->with(['clear', 'change']);

test('pilot restrictions cannot be bypassed by calling the recorder service directly', function (string $operation) {
    $fixture = checkpointPilotFixture();
    checkpointPilotSelect($fixture);
    $input = checkpointPilotInput($fixture);
    $input['category_id'] += 999;
    DB::enableQueryLog();
    DB::flushQueryLog();
    $failure = null;
    try {
        $recorder = app(CheckpointScanRecorder::class);
        if ($operation === 'plan') {
            $recorder->plan($fixture['staff'], $input['event_id'], $input['category_id']);
        } else {
            $recorder->record($fixture['staff'], $input);
        }
    } catch (CheckpointScanException $exception) {
        $failure = $exception;
    }
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect($failure)->toBeInstanceOf(CheckpointScanException::class)
        ->and($failure->errorCode)->toBe('checkpoint_pilot_forbidden')->and($queries)->toBe([])
        ->and(CheckpointScan::count())->toBe(0)->and(CheckpointScanPlan::count())->toBe(0);
})->with(['plan', 'record']);

test('turning the master flag off also blocks direct service calls', function () {
    $fixture = checkpointPilotFixture();
    checkpointPilotSelect($fixture);
    $input = checkpointPilotInput($fixture);
    config(['checkpoint_scanning.enabled' => false]);
    expect(fn () => app(CheckpointScanRecorder::class)->record($fixture['staff'], $input))->toThrow(CheckpointScanException::class);
    $this->postJson('/api/staff/checkpoint-scans', $input, $fixture['headers'])->assertStatus(503)
        ->assertJsonPath('code', 'checkpoint_recording_disabled');
    expect(CheckpointScan::count())->toBe(0)->and(CheckpointScanPlan::count())->toBe(0);
});

test('transactional uniqueness recovery rechecks the pilot and cannot write after it changes', function () {
    $fixture = checkpointPilotFixture();
    checkpointPilotSelect($fixture);
    $input = checkpointPilotInput($fixture);
    $attempts = 0;
    CheckpointScan::creating(function () use (&$attempts) {
        $attempts++;
        config(['checkpoint_scanning.pilot_category_id' => null]);
        throw new UniqueConstraintViolationException('sqlite', 'synthetic pilot collision', [], new RuntimeException('Synthetic retry'));
    });
    $this->postJson('/api/staff/checkpoint-scans', $input, $fixture['headers'])->assertStatus(503)
        ->assertJsonPath('code', 'checkpoint_recording_unavailable');
    expect($attempts)->toBe(1)->and(CheckpointScan::count())->toBe(0)->and(CheckpointScanPlan::count())->toBe(0);
});

test('the read-only selector and existing finish endpoint remain independent of the pilot gate', function () {
    $fixture = checkpointPilotFixture();
    // No pilot pair configured; checkpoint writes must remain unavailable.
    $this->getJson('/api/staff/checkpoint-scanner/context', $fixture['headers'])->assertOk()
        ->assertJsonPath('meta.scan_recording_available', false)->assertJsonPath('data.0.id', $fixture['event']->id);
    $this->getJson('/api/staff/finish-scanner/context', $fixture['headers'])->assertOk();
    $this->postJson('/api/staff/finish-scans', ['token' => app(FinishScanToken::class)->issue($fixture['registration'])], $fixture['headers'])
        ->assertCreated();
    expect(DB::table('finish_scans')->count())->toBe(1)->and(CheckpointScan::count())->toBe(0)
        ->and(CheckpointScanPlan::count())->toBe(0)->and(DB::table('race_results')->count())->toBe(0)
        ->and(DB::table('certificates')->count())->toBe(0);
});

test('request payload cannot configure the pilot or override the server category', function () {
    $fixture = checkpointPilotFixture();
    checkpointPilotSelect($fixture);
    $input = checkpointPilotInput($fixture);
    config(['checkpoint_scanning.pilot_event_id' => null, 'checkpoint_scanning.pilot_category_id' => null]);
    $this->postJson('/api/staff/checkpoint-scans', [...$input, 'pilot_event_id' => $input['event_id'],
        'pilot_category_id' => $input['category_id'], 'checkpoint_scanning' => ['enabled' => true]], $fixture['headers'])
        ->assertStatus(503)->assertJsonPath('code', 'checkpoint_recording_unavailable');
    expect(CheckpointScan::count())->toBe(0)->and(CheckpointScanPlan::count())->toBe(0);
});
