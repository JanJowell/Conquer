<?php

use App\Models\Category;
use App\Models\Certificate;
use App\Models\Event;
use App\Models\FinishScan;
use App\Models\RaceResult;
use App\Models\Registration;
use App\Models\User;
use App\Services\CertificateNotificationService;
use App\Services\EBadgeNotificationService;
use App\Services\FinishScanToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

function finishScannerEvent(User $manager, Carbon $now): Event
{
    return Event::create([
        'title' => 'Finish Scanner Test '.uniqid(),
        'slug' => 'finish-scanner-test-'.uniqid(),
        'description' => 'Simulated finish-line scanning.',
        'venue' => 'Bacoor City',
        'event_date' => $now->toDateString(),
        'start_time' => $now->copy()->subHour()->format('H:i'),
        'end_time' => $now->copy()->addHours(4)->format('H:i'),
        'registration_deadline' => $now->copy()->subDay()->toDateString(),
        'status' => 'draft',
        'organized_by' => 'Racetech',
        'interest_type' => 'Marathon',
        'manager_id' => $manager->id,
    ]);
}

function finishScannerCategory(Event $event, Carbon $now, bool $started = true): Category
{
    return Category::create([
        'event_id' => $event->id,
        'name' => '10K Open '.uniqid(),
        'distance_km' => 10,
        'status' => 'open',
        'scheduled_start_date' => $now->toDateString(),
        'scheduled_start_time' => $now->copy()->subHour()->format('H:i'),
        'scheduled_end_date' => $now->toDateString(),
        'scheduled_end_time' => $now->copy()->addHours(2)->format('H:i'),
        'started_at' => $started ? $now->copy()->subMinutes(30) : null,
    ]);
}

function finishScannerRegistration(Event $event, Category $category, array $overrides = []): Registration
{
    $runner = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'email_verified_at' => now(),
    ]);

    return Registration::create(array_merge([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'bib_number' => (string) fake()->unique()->numberBetween(100, 999),
        'status' => 'checked_in',
        'registered_at' => now()->subDay(),
    ], $overrides));
}

function finishScannerApiToken(User $staff): string
{
    $plainToken = 'scanner-'.bin2hex(random_bytes(24));
    $staff->forceFill([
        'api_token' => hash('sha256', $plainToken),
        'api_token_expires_at' => now()->addHour(),
    ])->save();

    return $plainToken;
}

function finishScannerHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

test('only race operations staff can sign in to the finish scanner', function () {
    $password = 'ScannerPass123!';
    $manager = User::factory()->create([
        'role' => User::ROLE_EVENT_MANAGER,
        'password' => Hash::make($password),
    ]);
    $runner = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'password' => Hash::make($password),
    ]);

    $this->postJson('/api/staff/login', [
        'identifier' => $manager->email,
        'password' => $password,
    ])->assertOk()
        ->assertJsonPath('user.role', User::ROLE_EVENT_MANAGER)
        ->assertJsonStructure(['token', 'expires_at']);

    expect($manager->fresh()->api_token)->not->toBeNull()
        ->and($manager->fresh()->api_token_expires_at->diffInHours(now()))->toBeLessThanOrEqual(12);

    $this->postJson('/api/staff/login', [
        'identifier' => $runner->email,
        'password' => $password,
    ])->assertForbidden()
        ->assertJsonPath('message', 'This account is not authorized to use the finish scanner.');
});

test('a legacy scanner request records one provisional server-timed finish without publishing a result', function () {
    $staff = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $raceNow = Carbon::parse('2026-09-02 08:30:00', config('app.timezone'));
    $this->travelTo($raceNow);
    $event = finishScannerEvent($staff, $raceNow);
    $category = finishScannerCategory($event, $raceNow);
    $registration = finishScannerRegistration($event, $category, ['bib_number' => '301']);
    $token = app(FinishScanToken::class)->issue($registration);

    $this->withHeaders(finishScannerHeaders(finishScannerApiToken($staff)))
        ->postJson('/api/staff/finish-scans', [
            'token' => $token,
            'event_id' => $event->id,
            'category_id' => $category->id,
        ])->assertCreated()
        ->assertJsonPath('data.participant_name', $registration->user->name)
        ->assertJsonPath('data.bib_number', '301')
        ->assertJsonPath('data.elapsed_seconds', 1800)
        ->assertJsonPath('data.elapsed_time', '00:30:00')
        ->assertJsonPath('data.status', FinishScan::STATUS_PROVISIONAL);

    $scan = FinishScan::firstOrFail();

    expect($scan->scanned_at->timestamp)->toBe($raceNow->timestamp)
        ->and($scan->scanned_by_user_id)->toBe($staff->id)
        ->and($registration->fresh()->status)->toBe('checked_in')
        ->and(RaceResult::count())->toBe(0)
        ->and(Certificate::count())->toBe(0);
});

test('an offline finish preserves its capture time and synchronizes idempotently', function () {
    $staff = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $raceNow = Carbon::parse('2026-09-02 08:30:00', config('app.timezone'));
    $capturedAt = $raceNow->copy()->subMinutes(10);
    $this->travelTo($raceNow);
    $event = finishScannerEvent($staff, $raceNow);
    $category = finishScannerCategory($event, $raceNow);
    $registration = finishScannerRegistration($event, $category, ['bib_number' => '305']);
    $headers = finishScannerHeaders(finishScannerApiToken($staff));
    $payload = [
        'token' => app(FinishScanToken::class)->issue($registration),
        'client_scan_id' => '01991f27-c344-7f11-92aa-0a58a9feac02',
        'captured_at' => $capturedAt->toIso8601String(),
        'captured_offline' => true,
    ];

    $this->withHeaders($headers)
        ->postJson('/api/staff/finish-scans', $payload)
        ->assertCreated()
        ->assertJsonPath('data.client_scan_id', $payload['client_scan_id'])
        ->assertJsonPath('data.captured_offline', true)
        ->assertJsonPath('data.elapsed_seconds', 1200)
        ->assertJsonPath('data.status', FinishScan::STATUS_PROVISIONAL);

    $this->withHeaders($headers)
        ->postJson('/api/staff/finish-scans', $payload)
        ->assertOk()
        ->assertJsonPath('idempotent_replay', true)
        ->assertJsonPath('data.client_scan_id', $payload['client_scan_id']);

    $scan = FinishScan::sole();

    expect($scan->scanned_at->timestamp)->toBe($capturedAt->timestamp)
        ->and($scan->captured_offline)->toBeTrue()
        ->and($scan->status)->toBe(FinishScan::STATUS_PROVISIONAL)
        ->and(RaceResult::count())->toBe(0)
        ->and(Certificate::count())->toBe(0);

    $this->actingAs($staff)
        ->get(route('admin.finish-scans.index'))
        ->assertOk()
        ->assertSee('Offline Sync');

    $this->actingAs($staff)
        ->get(route('admin.results.index', ['event_id' => $event->id]))
        ->assertOk()
        ->assertSee('Verify the captured time before publishing.');
});

test('offline finish timestamps cannot precede the start or be far in the future', function () {
    $staff = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $raceNow = Carbon::parse('2026-09-02 08:30:00', config('app.timezone'));
    $this->travelTo($raceNow);
    $event = finishScannerEvent($staff, $raceNow);
    $category = finishScannerCategory($event, $raceNow);
    $headers = finishScannerHeaders(finishScannerApiToken($staff));

    foreach ([
        ['bib' => '306', 'id' => '01991f27-c344-7f11-92aa-0a58a9feac03', 'time' => $category->started_at->copy()->subSecond(), 'code' => 'category_not_started', 'status' => 409],
        ['bib' => '307', 'id' => '01991f27-c344-7f11-92aa-0a58a9feac04', 'time' => $raceNow->copy()->addMinutes(6), 'code' => 'invalid_capture_time', 'status' => 422],
    ] as $case) {
        $registration = finishScannerRegistration($event, $category, ['bib_number' => $case['bib']]);

        $this->withHeaders($headers)
            ->postJson('/api/staff/finish-scans', [
                'token' => app(FinishScanToken::class)->issue($registration),
                'client_scan_id' => $case['id'],
                'captured_at' => $case['time']->toIso8601String(),
                'captured_offline' => true,
            ])->assertStatus($case['status'])
            ->assertJsonPath('code', $case['code']);
    }

    expect(FinishScan::count())->toBe(0);
});

test('QR-only scanning derives the event and category and supports multiple registrations for one participant', function () {
    $staff = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $raceNow = Carbon::parse('2026-09-02 08:45:00', config('app.timezone'));
    $this->travelTo($raceNow);
    $event = finishScannerEvent($staff, $raceNow);
    $firstCategory = finishScannerCategory($event, $raceNow);
    $secondCategory = finishScannerCategory($event, $raceNow);
    $firstRegistration = finishScannerRegistration($event, $firstCategory, ['bib_number' => '311']);
    $secondRegistration = Registration::create([
        'user_id' => $firstRegistration->user_id,
        'event_id' => $event->id,
        'category_id' => $secondCategory->id,
        'bib_number' => '312',
        'status' => 'checked_in',
        'registered_at' => now()->subDay(),
    ]);
    $headers = finishScannerHeaders(finishScannerApiToken($staff));

    foreach ([$firstRegistration, $secondRegistration] as $registration) {
        $this->withHeaders($headers)
            ->postJson('/api/staff/finish-scans', [
                'token' => app(FinishScanToken::class)->issue($registration),
            ])->assertCreated()
            ->assertJsonPath('data.registration_id', $registration->id)
            ->assertJsonPath('data.event_id', $event->id)
            ->assertJsonPath('data.category_id', $registration->category_id)
            ->assertJsonPath('data.bib_number', $registration->bib_number)
            ->assertJsonPath('data.elapsed_seconds', 1800);
    }

    expect(FinishScan::where('user_id', $firstRegistration->user_id)->count())->toBe(2)
        ->and(FinishScan::pluck('category_id')->all())->toEqualCanonicalizing([
            $firstCategory->id,
            $secondCategory->id,
        ]);
});

test('duplicate and altered scans are rejected without creating extra records', function () {
    $staff = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $raceNow = Carbon::parse('2026-09-02 09:00:00', config('app.timezone'));
    $this->travelTo($raceNow);
    $event = finishScannerEvent($staff, $raceNow);
    $category = finishScannerCategory($event, $raceNow);
    $registration = finishScannerRegistration($event, $category, ['bib_number' => '302']);
    $token = app(FinishScanToken::class)->issue($registration);
    $headers = finishScannerHeaders(finishScannerApiToken($staff));
    $payload = ['token' => $token];

    $this->withHeaders($headers)->postJson('/api/staff/finish-scans', $payload)->assertCreated();
    $this->withHeaders($headers)->postJson('/api/staff/finish-scans', $payload)
        ->assertStatus(409)
        ->assertJsonPath('code', 'already_scanned')
        ->assertJsonPath('data.elapsed_time', '00:30:00');

    $altered = substr($token, 0, -1).($token[-1] === 'a' ? 'b' : 'a');
    $this->withHeaders($headers)->postJson('/api/staff/finish-scans', [
        ...$payload,
        'token' => $altered,
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_scan_token');

    expect(FinishScan::count())->toBe(1);
});

test('scanner enforces selected category check-in start state and assigned manager access', function () {
    $assignedManager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $otherManager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $raceNow = Carbon::parse('2026-09-02 10:00:00', config('app.timezone'));
    $this->travelTo($raceNow);
    $event = finishScannerEvent($assignedManager, $raceNow);
    $category = finishScannerCategory($event, $raceNow, false);
    $otherCategory = finishScannerCategory($event, $raceNow);
    $registration = finishScannerRegistration($event, $category, ['bib_number' => '303']);
    $token = app(FinishScanToken::class)->issue($registration);

    $this->withHeaders(finishScannerHeaders(finishScannerApiToken($otherManager)))
        ->postJson('/api/staff/finish-scans', [
            'token' => $token,
        ])->assertForbidden()
        ->assertJsonPath('code', 'forbidden_event');

    $headers = finishScannerHeaders(finishScannerApiToken($assignedManager));
    $this->withHeaders($headers)->postJson('/api/staff/finish-scans', [
        'token' => $token,
        'event_id' => $event->id,
        'category_id' => $otherCategory->id,
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'selection_mismatch');

    $this->withHeaders($headers)->postJson('/api/staff/finish-scans', [
        'token' => $token,
    ])->assertStatus(409)
        ->assertJsonPath('code', 'category_not_started');

    $category->update(['started_at' => $raceNow->copy()->subMinutes(10)]);
    $registration->update(['status' => 'approved']);

    $this->withHeaders($headers)->postJson('/api/staff/finish-scans', [
        'token' => $token,
    ])->assertStatus(409)
        ->assertJsonPath('code', 'participant_not_checked_in');

    $registration->update(['status' => 'checked_in', 'bib_number' => null]);

    $this->withHeaders($headers)->postJson('/api/staff/finish-scans', [
        'token' => $token,
    ])->assertStatus(409)
        ->assertJsonPath('code', 'bib_not_assigned');

    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $this->withHeaders(finishScannerHeaders(finishScannerApiToken($runner)))
        ->getJson('/api/staff/finish-scanner/context')
        ->assertForbidden()
        ->assertJsonPath('code', 'scanner_forbidden');

    expect(FinishScan::count())->toBe(0);
});

test('admin can print an assigned BIB QR and review scan history while other managers cannot', function () {
    $assignedManager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $otherManager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $raceNow = Carbon::parse('2026-09-02 11:00:00', config('app.timezone'));
    $event = finishScannerEvent($assignedManager, $raceNow);
    $category = finishScannerCategory($event, $raceNow);
    $registration = finishScannerRegistration($event, $category, ['bib_number' => '304']);

    $this->actingAs($assignedManager)
        ->get(route('admin.check-in.finish-qr', $registration))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->assertSee('BIB 304')
        ->assertSee('Finish Scanner Simulation');

    $this->actingAs($assignedManager)
        ->get(route('admin.check-in.index', ['event_id' => $event->id, 'search' => '304']))
        ->assertOk()
        ->assertSee('View / Print Finish QR')
        ->assertSee(route('admin.check-in.finish-qr', $registration));

    $this->actingAs($assignedManager)
        ->get(route('admin.participants.index', ['event_id' => $event->id, 'search' => '304']))
        ->assertOk()
        ->assertDontSee('View Finish QR');

    $this->actingAs($assignedManager)
        ->get(route('admin.results.index', ['event_id' => $event->id, 'search' => '304']))
        ->assertOk()
        ->assertSee('data-manual-result="true"', false)
        ->assertSee('data-manual-finish', false)
        ->assertSee('Finish');

    $this->actingAs($otherManager)
        ->get(route('admin.check-in.finish-qr', $registration))
        ->assertForbidden();

    $registration->update(['status' => 'approved']);

    $this->actingAs($assignedManager)
        ->get(route('admin.check-in.finish-qr', $registration))
        ->assertStatus(409);

    $this->actingAs($assignedManager)
        ->get(route('admin.finish-scans.index'))
        ->assertOk()
        ->assertSee('Finish Scan History');
});

test('publishing a provisional scan creates the official result and certificate exactly once', function () {
    $this->mock(EBadgeNotificationService::class, fn ($mock) => $mock->shouldReceive('notifyIssued')->andReturnNull());
    $this->mock(CertificateNotificationService::class, fn ($mock) => $mock->shouldReceive('notifyIssued')->once()->andReturnNull());

    $staff = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $raceNow = Carbon::parse('2026-09-02 12:45:00', config('app.timezone'));
    $this->travelTo($raceNow);
    $event = finishScannerEvent($staff, $raceNow);
    $category = finishScannerCategory($event, $raceNow);
    $category->update(['started_at' => $raceNow->copy()->subMinutes(45)]);
    $registration = finishScannerRegistration($event, $category, ['bib_number' => '305']);
    $token = app(FinishScanToken::class)->issue($registration);

    $this->withHeaders(finishScannerHeaders(finishScannerApiToken($staff)))
        ->postJson('/api/staff/finish-scans', [
            'token' => $token,
            'event_id' => $event->id,
            'category_id' => $category->id,
        ])->assertCreated();

    $this->actingAs($staff)
        ->get(route('admin.results.index', ['event_id' => $event->id, 'search' => '305']))
        ->assertOk()
        ->assertSee('Scanned · Review')
        ->assertSee('00:45:00')
        ->assertSee('data-provisional-scan="true"', false)
        ->assertSee('Update');

    $this->actingAs($staff)
        ->post(route('admin.results.store'), [
            'registration_id' => $registration->id,
            'finish_time' => '00:45:00',
            'remarks' => 'Published from finish scanner simulation.',
        ])->assertRedirect()
        ->assertSessionHas('success');

    expect($registration->fresh()->status)->toBe('completed')
        ->and($registration->raceResult?->finish_time)->toBe('00:45:00')
        ->and($registration->finishScan?->status)->toBe(FinishScan::STATUS_PUBLISHED)
        ->and($registration->finishScan?->published_at)->not->toBeNull()
        ->and(Certificate::where('registration_id', $registration->id)->count())->toBe(1);
});

test('admin can publish all valid provisional scans for one category in a single action', function () {
    $this->mock(EBadgeNotificationService::class, fn ($mock) => $mock->shouldReceive('notifyIssued')->andReturnNull());
    $this->mock(CertificateNotificationService::class, fn ($mock) => $mock->shouldReceive('notifyIssued')->twice()->andReturnNull());

    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $otherManager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $raceNow = Carbon::parse('2026-09-02 14:00:00', config('app.timezone'));
    $this->travelTo($raceNow);
    $event = finishScannerEvent($manager, $raceNow);
    $category = finishScannerCategory($event, $raceNow);
    $otherCategory = finishScannerCategory($event, $raceNow);
    $first = finishScannerRegistration($event, $category, ['bib_number' => '401']);
    $second = finishScannerRegistration($event, $category, ['bib_number' => '402']);
    $invalid = finishScannerRegistration($event, $category, ['bib_number' => '403']);
    $otherCategoryRegistration = finishScannerRegistration($event, $otherCategory, ['bib_number' => '404']);
    $headers = finishScannerHeaders(finishScannerApiToken($manager));

    foreach ([$first, $second, $invalid] as $registration) {
        $this->withHeaders($headers)
            ->postJson('/api/staff/finish-scans', [
                'token' => app(FinishScanToken::class)->issue($registration),
                'event_id' => $event->id,
                'category_id' => $category->id,
            ])
            ->assertCreated();
    }

    $this->withHeaders($headers)
        ->postJson('/api/staff/finish-scans', [
            'token' => app(FinishScanToken::class)->issue($otherCategoryRegistration),
            'event_id' => $event->id,
            'category_id' => $otherCategory->id,
        ])
        ->assertCreated();

    $invalid->update(['status' => 'approved']);

    $this->actingAs($manager)
        ->get(route('admin.results.index', ['event_id' => $event->id]))
        ->assertOk()
        ->assertSee('Publish Results')
        ->assertSee(route('admin.results.publish-scans', $category));

    $this->actingAs($otherManager)
        ->post(route('admin.results.publish-scans', $category))
        ->assertForbidden();

    $this->actingAs($manager)
        ->post(route('admin.results.publish-scans', $category))
        ->assertRedirect()
        ->assertSessionHas('success', "Published 2 result(s) for {$category->name}. Skipped 1 scan(s) that require individual review.");

    expect(RaceResult::where('category_id', $category->id)->count())->toBe(2)
        ->and(RaceResult::where('category_id', $otherCategory->id)->count())->toBe(0)
        ->and($first->fresh()->status)->toBe('completed')
        ->and($second->fresh()->status)->toBe('completed')
        ->and($invalid->fresh()->status)->toBe('approved')
        ->and($otherCategoryRegistration->fresh()->status)->toBe('checked_in')
        ->and($first->finishScan?->fresh()->status)->toBe(FinishScan::STATUS_PUBLISHED)
        ->and($second->finishScan?->fresh()->status)->toBe(FinishScan::STATUS_PUBLISHED)
        ->and($invalid->finishScan?->fresh()->status)->toBe(FinishScan::STATUS_PROVISIONAL)
        ->and($otherCategoryRegistration->finishScan?->fresh()->status)->toBe(FinishScan::STATUS_PROVISIONAL)
        ->and(Certificate::whereIn('registration_id', [$first->id, $second->id])->count())->toBe(2);
});
