<?php

use App\Models\Certificate;
use App\Models\Event;
use App\Models\EventAutoArchiveLog;
use App\Models\Payment;
use App\Models\RaceResult;
use App\Models\Registration;
use App\Models\RegistrationFeedback;
use App\Models\User;
use App\Services\AutomaticEventArchiver;
use App\Services\EventCleanup;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Http::preventStrayRequests();
    Notification::fake();
    config(['event_archiving.enabled' => true, 'event_archiving.activated_at' => '2026-08-01T00:00:00+00:00']);
    $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00:00', config('app.timezone')));
});

function autoArchiveEvent(array $overrides = []): Event
{
    $date = today()->subDays(31);
    $event = Event::forceCreate([
        'title' => 'Synthetic automatic archive', 'slug' => 'auto-archive-'.Str::uuid(),
        'description' => 'Synthetic retention fixture.', 'venue' => 'Synthetic meeting point',
        'event_date' => $date, 'event_end_date' => $date, 'registration_deadline' => $date,
        'start_time' => '06:00', 'end_time' => '09:00', 'interest_type' => 'Marathon',
        'type_details' => ['cutoff_time' => '2 hours'], 'banner_image' => 'events/synthetic.png',
        'review_status' => 'approved', 'status' => 'completed', 'first_published_at' => '2026-09-01 10:00:00', ...$overrides,
    ]);
    $event->categories()->create(['name' => 'Synthetic 3K Open', 'distance_km' => 3, 'status' => 'open',
        'slot_limit' => 100, 'price_cents' => 0, 'checkpoint_map_image' => 'categories/synthetic.png']);

    return $event->fresh();
}

function autoArchiveState(array $except = []): array
{
    $state = [];
    foreach (array_diff(Schema::getTableListing(schemaQualified: false), $except) as $table) {
        $state[$table] = hash('sha256', DB::table($table)->get()->toJson());
    }
    ksort($state);

    return $state;
}

function autoArchiveMigration(): Migration
{
    return require database_path('migrations/2026_11_18_000001_add_automatic_event_archiving.php');
}

test('the fixed thirty-day cutoff uses the full event end timestamp', function (int $offset, bool $eligible) {
    $end = now()->subDays(30)->addSeconds($offset);
    $event = autoArchiveEvent(['event_date' => $end->toDateString(), 'event_end_date' => $end->toDateString(),
        'end_time' => $end->format('H:i:s')]);
    $before = $event->getRawOriginal();
    $history = autoArchiveState(['events', 'event_auto_archive_logs']);

    expect(app(AutomaticEventArchiver::class)->run())->toBe($eligible ? 1 : 0)
        ->and($event->fresh()->archived_at !== null)->toBe($eligible)
        ->and(EventAutoArchiveLog::count())->toBe($eligible ? 1 : 0)
        ->and(autoArchiveState(['events', 'event_auto_archive_logs']))->toBe($history);
    $after = $event->fresh()->getRawOriginal();
    foreach (['archived_at', 'archived_by_user_id', 'updated_at'] as $field) {
        unset($before[$field], $after[$field]);
    }
    expect($after)->toBe($before)->and($event->fresh()->effective_status)->toBe('completed');
    if ($eligible) {
        $audit = EventAutoArchiveLog::sole();
        expect($audit->event_id)->toBe($event->id)->and($audit->event_title)->toBe($event->title)
            ->and($audit->event_ended_at->format('Y-m-d H:i:s'))->toBe($end->format('Y-m-d H:i:s'))
            ->and($audit->cutoff_at->format('Y-m-d H:i:s'))->toBe(now()->subDays(30)->format('Y-m-d H:i:s'))
            ->and($event->fresh()->archived_by_user_id)->toBeNull();
    }
})->with(['one second short' => [1, false], 'exact cutoff' => [0, true], 'older than cutoff' => [-1, true]]);

test('retention uses the end date rather than the start or record creation date', function (string $case, bool $eligible) {
    $event = autoArchiveEvent(match ($case) {
        'multi day recent end' => ['event_date' => today()->subDays(60), 'event_end_date' => today()->subDays(10)],
        'future end' => ['event_date' => today()->subDays(60), 'event_end_date' => today()->addDays(2)],
        'newly created old event' => ['created_at' => now(), 'updated_at' => now()],
        'old record upcoming' => ['created_at' => now()->subYear(), 'event_date' => today()->addWeek(), 'event_end_date' => today()->addWeek()],
        'old record ongoing' => ['created_at' => now()->subYear(), 'event_date' => today(), 'event_end_date' => today(), 'end_time' => '18:00'],
    });

    expect(app(AutomaticEventArchiver::class)->run())->toBe((int) $eligible)
        ->and($event->fresh()->archived_at !== null)->toBe($eligible);
})->with([
    ['multi day recent end', false], ['future end', false], ['newly created old event', true],
    ['old record upcoming', false], ['old record ongoing', false],
]);

test('legacy end date and end-of-day fallbacks do not archive early', function (bool $hasEndDate, string $clock, bool $eligible) {
    $this->travelTo(CarbonImmutable::parse('2026-11-18 '.$clock, config('app.timezone')));
    $date = today()->subDays(30);
    $event = autoArchiveEvent(['review_status' => null, 'event_date' => $date,
        'event_end_date' => $hasEndDate ? $date : null, 'end_time' => null]);

    expect(app(AutomaticEventArchiver::class)->run())->toBe((int) $eligible)
        ->and($event->fresh()->archived_at !== null)->toBe($eligible);
    $this->travel(1)->days();
    expect(app(AutomaticEventArchiver::class)->run())->toBe($eligible ? 0 : 1);
})->with([true, false])->with([['00:00:00', false], ['23:59:59', false]]);

test('the cutoff follows the application timezone including date rollover', function (string $timezone) {
    config(['app.timezone' => $timezone]);
    $this->travelTo(CarbonImmutable::parse('2026-11-18 00:15:00', $timezone));
    $end = now($timezone)->subDays(30);
    $eligible = autoArchiveEvent(['event_date' => $end->toDateString(), 'event_end_date' => $end->toDateString(), 'end_time' => '00:15:00']);
    $recent = autoArchiveEvent(['event_date' => $end->toDateString(), 'event_end_date' => $end->toDateString(), 'end_time' => '00:15:01']);

    expect(app(AutomaticEventArchiver::class)->run())->toBe(1)
        ->and($eligible->fresh()->archived_at)->not->toBeNull()->and($recent->fresh()->archived_at)->toBeNull();
})->with(['UTC', 'Asia/Manila', 'America/New_York']);

test('unpublished incomplete archived exempt and revision events are never automatically archived', function (string $restriction) {
    $event = autoArchiveEvent();
    if (str_starts_with($restriction, 'active_')) {
        autoArchiveEvent(['revision_of_id' => $event->id, 'review_status' => substr($restriction, 7)]);
    } elseif ($restriction === 'copy') {
        $parent = autoArchiveEvent(['auto_archive_exempt_at' => now()]);
        $event->forceFill(['revision_of_id' => $parent->id])->save();
    } elseif ($restriction === 'no open category') {
        $event->categories()->update(['status' => 'closed']);
    } else {
        $event->forceFill(match ($restriction) {
            'archived' => ['archived_at' => now()], 'exempt' => ['auto_archive_exempt_at' => now()],
            'no banner' => ['banner_image' => null],
            default => ['review_status' => $restriction],
        })->save();
    }
    $before = autoArchiveState();

    expect(app(AutomaticEventArchiver::class)->run())->toBe(0)->and(autoArchiveState())->toBe($before);
})->with(['draft', 'pending_review', 'changes_requested', 'discarded', 'archived', 'exempt', 'copy', 'no banner', 'no open category',
    'active_draft', 'active_changes_requested', 'active_pending_review']);

test('legacy published completed events without a first-publication record remain protected', function () {
    $event = autoArchiveEvent(['review_status' => null, 'first_published_at' => null]);
    expect(app(AutomaticEventArchiver::class)->run())->toBe(0)->and($event->fresh()->archived_at)->toBeNull();
});

test('dry runs can preview while disabled without writing records or audit entries', function () {
    config(['event_archiving.enabled' => false]);
    autoArchiveEvent();
    autoArchiveEvent(['auto_archive_exempt_at' => now()]);
    $before = autoArchiveState();
    $this->artisan('events:archive-completed --dry-run')
        ->expectsOutput('Dry run: 1 completed/cancelled event(s) are eligible for automatic archiving.')->assertSuccessful();
    expect(autoArchiveState())->toBe($before);
    Http::assertNothingSent();
});

test('dry run event reads never request row locks', function () {
    autoArchiveEvent();
    $locks = [];
    Event::addGlobalScope('observe_preview_locks', function (Builder $query) use (&$locks) {
        $locks[] = $query->getQuery()->lock;
    });
    expect(app(AutomaticEventArchiver::class)->run(true))->toBe(1)
        ->and($locks)->not->toBeEmpty()->not->toContain(true);
});

test('only explicit true enables automatic archive writes', function (mixed $enabled) {
    config(['event_archiving.enabled' => $enabled]);
    autoArchiveEvent();
    $before = autoArchiveState();
    $this->artisan('events:archive-completed')->expectsOutput('Automatic event archiving is disabled.')->assertSuccessful();
    expect(app(AutomaticEventArchiver::class)->run())->toBe(0)->and(autoArchiveState())->toBe($before);
})->with([false, null, 'true', 'false', 1]);

test('the shipped environment configuration leaves automation off by default', function () {
    $prior = getenv('EVENT_AUTO_ARCHIVE_ENABLED');
    $priorEnv = $_ENV['EVENT_AUTO_ARCHIVE_ENABLED'] ?? null;
    $priorServer = $_SERVER['EVENT_AUTO_ARCHIVE_ENABLED'] ?? null;
    putenv('EVENT_AUTO_ARCHIVE_ENABLED');
    unset($_ENV['EVENT_AUTO_ARCHIVE_ENABLED'], $_SERVER['EVENT_AUTO_ARCHIVE_ENABLED']);
    try {
        $values = require config_path('event_archiving.php');
        expect($values['enabled'])->toBeFalse();
    } finally {
        $prior === false ? putenv('EVENT_AUTO_ARCHIVE_ENABLED') : putenv('EVENT_AUTO_ARCHIVE_ENABLED='.$prior);
        if ($priorEnv !== null) {
            $_ENV['EVENT_AUTO_ARCHIVE_ENABLED'] = $priorEnv;
        }
        if ($priorServer !== null) {
            $_SERVER['EVENT_AUTO_ARCHIVE_ENABLED'] = $priorServer;
        }
    }
});

test('sequential repeated runs cannot create duplicate archive outcomes or system audits', function () {
    $event = autoArchiveEvent();
    $this->artisan('events:archive-completed')->expectsOutput('Automatically archived 1 completed/cancelled event(s).')->assertSuccessful();
    $before = autoArchiveState();
    $this->artisan('events:archive-completed')->expectsOutput('Automatically archived 0 completed/cancelled event(s).')->assertSuccessful();
    expect(autoArchiveState())->toBe($before)->and(EventAutoArchiveLog::where('event_id', $event->id)->count())->toBe(1);
});

test('candidate eligibility is rechecked after retrieval rather than trusted from the initial list', function (string $change) {
    $event = autoArchiveEvent();
    $changed = false;
    DB::listen(function (QueryExecuted $query) use ($event, $change, &$changed) {
        if (! $changed && str_starts_with(strtolower($query->sql), 'select "id" from "events"')) {
            $changed = true;
            if ($change === 'active revision') {
                autoArchiveEvent(['revision_of_id' => $event->id, 'review_status' => 'draft']);

                return;
            }
            DB::table('events')->where('id', $event->id)->update(match ($change) {
                'restore exemption' => ['auto_archive_exempt_at' => now()],
                'other archive' => ['archived_at' => now()],
                'future end' => ['event_end_date' => today()->addWeek()->toDateString()],
                'unpublished' => ['review_status' => 'pending_review'],
            });
        }
    });
    expect(app(AutomaticEventArchiver::class)->run())->toBe(0)->and($changed)->toBeTrue()
        ->and(EventAutoArchiveLog::count())->toBe(0);
})->with(['restore exemption', 'other archive', 'future end', 'unpublished', 'active revision']);

test('a failed audit write rolls back the automatic event archive', function () {
    $event = autoArchiveEvent();
    $before = autoArchiveState();
    EventAutoArchiveLog::creating(function () {
        throw new RuntimeException('Synthetic audit failure.');
    });
    expect(fn () => app(AutomaticEventArchiver::class)->run())->toThrow(RuntimeException::class, 'Synthetic audit failure.')
        ->and($event->fresh()->archived_at)->toBeNull()->and(autoArchiveState())->toBe($before);
});

test('Admin restoration starts another thirty-day window and allows repeated automatic archive cycles', function (string $source) {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = autoArchiveEvent();
    $cleanup = app(EventCleanup::class);
    if ($source === 'automatic') {
        expect(app(AutomaticEventArchiver::class)->run())->toBe(1);
    } else {
        $cleanup->perform($event, $admin, 'archive', $cleanup->fingerprint($event->fresh()));
    }
    $this->actingAs($admin)->post(route('admin.events.restore', $event), [
        'confirmation' => '1', 'cleanup_fingerprint' => $cleanup->fingerprint($event->fresh()),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $marker = $event->fresh()->auto_archive_restored_at;
    expect($marker)->not->toBeNull()->and($event->fresh()->auto_archive_exempt_at)->toBeNull()
        ->and($event->fresh()->archived_at)->toBeNull();
    expect(app(AutomaticEventArchiver::class)->run())->toBe(0)
        ->and($event->fresh()->auto_archive_restored_at->toISOString())->toBe($marker->toISOString());
    $this->travel(30)->days();
    expect(app(AutomaticEventArchiver::class)->run())->toBe(1);
    expect($event->fresh()->archived_at)->not->toBeNull()
        ->and($event->fresh()->auto_archive_restored_at->toISOString())->toBe($marker->toISOString())
        ->and(EventAutoArchiveLog::where('event_id', $event->id)->count())->toBe($source === 'automatic' ? 2 : 1);
})->with(['automatic', 'manual']);

test('restore exemptions cannot be changed through editable event attributes and stale confirmations fail', function () {
    $event = autoArchiveEvent();
    $cleanup = app(EventCleanup::class);
    $fingerprint = $cleanup->fingerprint($event);
    $event->fill(['auto_archive_exempt_at' => now()])->save();
    expect($event->fresh()->auto_archive_exempt_at)->toBeNull();
    DB::table('events')->where('id', $event->id)->update(['auto_archive_exempt_at' => now()]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    expect(fn () => $cleanup->perform($event, $admin, 'archive', $fingerprint))
        ->toThrow(ValidationException::class);
});

test('missing automatic archive schema fails closed without changing existing events', function (bool $dryRun) {
    autoArchiveMigration()->down();
    autoArchiveEvent();
    $before = autoArchiveState();
    $this->artisan('events:archive-completed'.($dryRun ? ' --dry-run' : ''))
        ->expectsOutput('Automatic event archiving requires its database migration. No events were changed.')->assertFailed();
    expect(autoArchiveState())->toBe($before);
})->with([true, false]);

test('migration preserves historical Admin restores but ignores unrelated or malformed audit text', function () {
    autoArchiveMigration()->down();
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $restored = autoArchiveEvent();
    $untouched = autoArchiveEvent();
    $archived = autoArchiveEvent(['archived_at' => now()]);
    $stamp = now()->subDay()->format('Y-m-d H:i:s');
    foreach (['event.cleanup.restore event='.$restored->id, 'event.cleanup.restore event='.$archived->id,
        'event.cleanup.restore event='.$untouched->id.' trailing', 'event.cleanup.archive event='.$untouched->id] as $action) {
        DB::table('admin_activity_logs')->insert(['user_id' => $admin->id, 'action' => $action,
            'ip_address' => '127.0.0.1', 'created_at' => $stamp, 'updated_at' => $stamp]);
    }
    $before = autoArchiveState(['events']);
    autoArchiveMigration()->up();
    expect($restored->fresh()->auto_archive_exempt_at->format('Y-m-d H:i:s'))->toBe($stamp)
        ->and($untouched->fresh()->auto_archive_exempt_at)->toBeNull()
        ->and($archived->fresh()->auto_archive_exempt_at)->toBeNull();
    $after = autoArchiveState(['events', 'event_auto_archive_logs']);
    expect($after)->toBe($before);
});

test('a restore before the new migration remains protected after migration', function () {
    autoArchiveMigration()->down();
    $event = autoArchiveEvent(['archived_at' => now()]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $cleanup = app(EventCleanup::class);
    $cleanup->perform($event, $admin, 'restore', $cleanup->fingerprint($event->fresh()));
    autoArchiveMigration()->up();
    expect($event->fresh()->auto_archive_exempt_at)->not->toBeNull()
        ->and(app(AutomaticEventArchiver::class)->run())->toBe(0);
});

test('migration rollback refuses to discard exemptions or system audit evidence', function (string $evidence) {
    $event = autoArchiveEvent();
    if ($evidence === 'exemption') {
        DB::table('events')->where('id', $event->id)->update(['auto_archive_exempt_at' => now()]);
    } else {
        app(AutomaticEventArchiver::class)->run();
    }
    $before = autoArchiveState();
    expect(fn () => autoArchiveMigration()->down())->toThrow(RuntimeException::class)
        ->and(autoArchiveState())->toBe($before);
})->with(['exemption', 'audit']);

test('system audit evidence blocks empty-draft cleanup even if setup metadata is changed later', function () {
    $event = autoArchiveEvent();
    expect(app(AutomaticEventArchiver::class)->run())->toBe(1);
    $event->categories()->delete();
    DB::table('events')->where('id', $event->id)->update([
        'review_status' => 'draft', 'status' => 'draft', 'archived_at' => null,
    ]);
    expect(app(EventCleanup::class)->actionFor($event->fresh()))->toBeNull()
        ->and(EventAutoArchiveLog::where('event_id', $event->id)->count())->toBe(1);
});

test('manual empty-draft cleanup remains usable before the automation migration', function () {
    autoArchiveMigration()->down();
    $event = autoArchiveEvent(['review_status' => 'draft', 'status' => 'draft']);
    $event->categories()->delete();
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $cleanup = app(EventCleanup::class);
    expect($cleanup->actionFor($event->fresh()))->toBe('delete');
    $cleanup->perform($event, $admin, 'delete', $cleanup->fingerprint($event->fresh()), $event->title);
    expect(Event::find($event->id))->toBeNull();
});

test('automatic archiving preserves participant activity while hiding discovery', function () {
    $event = autoArchiveEvent();
    $token = 'synthetic-auto-archive-participant';
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER, 'api_token' => hash('sha256', $token),
        'api_token_expires_at' => now()->addDay()]);
    $category = $event->categories()->sole();
    $registration = Registration::create(['event_id' => $event->id, 'category_id' => $category->id,
        'user_id' => $runner->id, 'status' => 'completed', 'registered_at' => now()->subDays(32),
        'payment_required' => true, 'payment_status' => 'paid']);
    RaceResult::create(['registration_id' => $registration->id, 'user_id' => $runner->id,
        'event_id' => $event->id, 'category_id' => $category->id, 'finish_time' => '00:25:00']);
    Payment::create(['registration_id' => $registration->id, 'user_id' => $runner->id,
        'event_id' => $event->id, 'category_id' => $category->id, 'amount_cents' => 10000, 'status' => 'paid']);
    RegistrationFeedback::withoutEvents(fn () => RegistrationFeedback::create([
        'registration_id' => $registration->id, 'user_id' => $runner->id,
        'event_id' => $event->id, 'category_id' => $category->id, 'overall_rating' => 5, 'submitted_at' => now()->subDays(30),
    ]));
    Certificate::create(['registration_id' => $registration->id, 'user_id' => $runner->id,
        'event_id' => $event->id, 'category_id' => $category->id, 'certificate_number' => 'SYNTHETIC-AUTO-ARCHIVE-001',
        'verification_token' => (string) Str::uuid(), 'participant_name' => $runner->name,
        'event_title' => $event->title, 'category_name' => $category->name, 'issued_at' => now()->subDays(30)]);
    $headers = ['Authorization' => 'Bearer '.$token];
    $paths = ['/api/events/'.$event->id, '/api/my-registrations', '/api/my-results', '/api/certificates',
        "/api/registrations/{$registration->id}/payments", "/api/registrations/{$registration->id}/feedback",
        "/api/registrations/{$registration->id}/certificate"];
    $snapshots = [];
    foreach ($paths as $path) {
        $snapshots[$path] = $this->getJson($path, $headers)->assertOk()->json();
    }
    $before = autoArchiveState(['events', 'event_auto_archive_logs']);

    expect(app(AutomaticEventArchiver::class)->run())->toBe(1);
    $this->getJson('/api/events', $headers)->assertOk()->assertJsonCount(0, 'data');
    foreach ($paths as $path) {
        $this->getJson($path, $headers)->assertOk()->assertExactJson($snapshots[$path]);
    }
    expect(autoArchiveState(['events', 'event_auto_archive_logs']))->toBe($before);
    Http::assertNothingSent();
});

test('chunking does not skip candidates as earlier events leave the eligible set', function () {
    for ($index = 0; $index < 103; $index++) {
        autoArchiveEvent();
    }
    expect(app(AutomaticEventArchiver::class)->run())->toBe(103)
        ->and(EventAutoArchiveLog::count())->toBe(103)
        ->and(Event::whereNotNull('archived_at')->count())->toBe(103)
        ->and(app(AutomaticEventArchiver::class)->run())->toBe(0);
});

test('the daily schedule has an opt-in gate and overlap protection', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($scheduled) => str_contains($scheduled->command ?? '', 'events:archive-completed'));
    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 4 * * *')->and($event->withoutOverlapping)->toBeTrue();
    config(['event_archiving.enabled' => false]);
    expect($event->filtersPass(app()))->toBeFalse();
    config(['event_archiving.enabled' => true]);
    expect($event->filtersPass(app()))->toBeTrue();
});
