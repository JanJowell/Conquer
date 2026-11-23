<?php

use App\Models\AdminActivityLog;
use App\Models\Event;
use App\Models\EventAutoArchiveLog;
use App\Models\User;
use App\Services\AutomaticEventArchiver;
use App\Services\EventCleanup;
use App\Services\EventReviewWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->originalPolicyTimezone = date_default_timezone_get();
    date_default_timezone_set('Asia/Manila');
    Http::preventStrayRequests();
    Notification::fake();
    config(['app.timezone' => 'Asia/Manila', 'event_archiving.enabled' => true,
        'event_archiving.activated_at' => '2026-09-01T10:00:00+08:00']);
    $this->travelTo(CarbonImmutable::parse('2026-11-19 10:00:00', 'Asia/Manila'));
});

afterEach(function () {
    date_default_timezone_set($this->originalPolicyTimezone);
});

function publicationPolicyEvent(array $overrides = []): Event
{
    $date = today()->subDays(31);
    $event = Event::forceCreate([
        'title' => 'Synthetic publication policy', 'slug' => 'policy-'.Str::uuid(),
        'description' => 'Synthetic fixture only.', 'venue' => 'Synthetic meeting point',
        'event_date' => $date, 'event_end_date' => $date, 'registration_deadline' => $date,
        'start_time' => '06:00', 'end_time' => '09:00', 'interest_type' => 'Marathon',
        'type_details' => ['cutoff_time' => '2 hours'], 'banner_image' => 'events/synthetic.png',
        'review_status' => 'approved', 'status' => 'completed',
        'first_published_at' => '2026-09-01 10:00:00', ...$overrides,
    ]);
    $event->categories()->create(['name' => 'Synthetic 3K Open', 'distance_km' => 3, 'status' => 'open',
        'slot_limit' => 100, 'price_cents' => 0, 'checkpoint_map_image' => 'categories/synthetic.png',
        'scheduled_start_date' => $event->event_date, 'scheduled_end_date' => $event->event_end_date,
        'scheduled_start_time' => '06:00', 'scheduled_end_time' => '09:00']);

    return $event->fresh();
}

test('the policy protects unknown and earlier publications regardless of creation or latest approval dates', function (?string $published, bool $eligible) {
    $event = publicationPolicyEvent(['first_published_at' => $published,
        'created_at' => now()->subYears(2), 'approved_at' => now(), 'updated_at' => now()]);
    expect(app(AutomaticEventArchiver::class)->run(true))->toBe((int) $eligible)
        ->and(app(AutomaticEventArchiver::class)->run())->toBe((int) $eligible)
        ->and($event->fresh()->archived_at !== null)->toBe($eligible);
})->with([[null, false], ['2026-09-01 09:59:59', false], ['2026-09-01 10:00:00', true], ['2026-09-01 10:00:01', true]]);

test('activation requires a fixed valid offset timestamp and otherwise writes nothing', function (mixed $value) {
    config(['event_archiving.activated_at' => $value]);
    $event = publicationPolicyEvent();
    $before = $event->getRawOriginal();
    $archiver = app(AutomaticEventArchiver::class);
    expect($archiver->activationTime())->toBeNull()
        ->and(fn () => $archiver->run())->toThrow(RuntimeException::class)
        ->and(fn () => $archiver->run(true))->toThrow(RuntimeException::class);
    $this->artisan('events:archive-completed --dry-run')
        ->expectsOutput('Automatic event archiving requires EVENT_AUTO_ARCHIVE_ACTIVATED_AT with a fixed ISO-8601 timestamp and timezone offset. No events were changed.')->assertFailed();
    expect($event->fresh()->getRawOriginal())->toBe($before)->and(EventAutoArchiveLog::count())->toBe(0);
})->with([null, '', 'now', 'yesterday', '2026-09-01', '2026-09-01 10:00:00',
    '2026-02-30T10:00:00+08:00', '2026-09-01T25:00:00+08:00', '2026-09-01T10:00:00+99:99', true, 1]);

test('an explicit UTC or Manila activation denotes the same instant', function (string $activation) {
    config(['event_archiving.activated_at' => $activation]);
    $before = publicationPolicyEvent(['first_published_at' => '2026-09-01 09:59:59']);
    $exact = publicationPolicyEvent(['first_published_at' => '2026-09-01 10:00:00']);
    expect(app(AutomaticEventArchiver::class)->run())->toBe(1)
        ->and($before->fresh()->archived_at)->toBeNull()->and($exact->fresh()->archived_at)->not->toBeNull();
})->with(['2026-09-01T02:00:00Z', '2026-09-01T02:00:00+00:00', '2026-09-01T10:00:00+08:00']);

test('a future activation archives nothing even for a restored completed event', function () {
    config(['event_archiving.activated_at' => '2027-01-01T00:00:00+08:00']);
    $event = publicationPolicyEvent(['auto_archive_restored_at' => now()->subDays(31)]);
    expect(app(AutomaticEventArchiver::class)->run())->toBe(0)
        ->and(app(AutomaticEventArchiver::class)->run(true))->toBe(0)
        ->and($event->fresh()->archived_at)->toBeNull();
});

test('only original approval records first publication even while automation is off', function () {
    config(['event_archiving.enabled' => false]);
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = publicationPolicyEvent(['first_published_at' => null, 'review_status' => 'draft', 'status' => 'draft',
        'created_at' => now()->subYear(), 'manager_id' => $manager->id,
        'event_date' => today()->addMonth(), 'event_end_date' => today()->addMonth(), 'registration_deadline' => today()->addWeek()]);
    $workflow = app(EventReviewWorkflow::class);
    expect($event->first_published_at)->toBeNull();
    $submitted = $workflow->transition($event, $manager, 'submit', 0);
    expect($submitted->first_published_at)->toBeNull();
    $published = $workflow->transition($submitted, $admin, 'approve', 1);
    $first = $published->first_published_at->toISOString();
    expect($first)->toBe(now()->toISOString());
    $this->travel(1)->days();
    $draft = $workflow->startRevision($published, $manager, $published->review_version);
    expect($draft->first_published_at)->toBeNull()->and($draft->auto_archive_restored_at)->toBeNull();
    $draft->update(['description' => 'Reviewed revision']);
    $workflow->transition($draft, $manager, 'submit', 0);
    $updated = $workflow->transition($draft, $admin, 'approve', 1);
    expect($updated->first_published_at->toISOString())->toBe($first)
        ->and($updated->approved_at->toISOString())->toBe(now()->toISOString())
        ->and($draft->fresh()->first_published_at)->toBeNull();
});

test('approving a revision never enrolls a previously published legacy event', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = publicationPolicyEvent(['first_published_at' => null, 'manager_id' => $manager->id,
        'event_date' => today()->addMonth(), 'event_end_date' => today()->addMonth(), 'registration_deadline' => today()->addWeek()]);
    $workflow = app(EventReviewWorkflow::class);
    $draft = $workflow->startRevision($event, $manager, 0);
    $workflow->transition($draft, $manager, 'submit', 0);
    $workflow->transition($draft, $admin, 'approve', 1);
    expect($event->fresh()->first_published_at)->toBeNull();
    $this->travel(62)->days();
    expect(app(AutomaticEventArchiver::class)->run())->toBe(0)->and($event->fresh()->archived_at)->toBeNull();
});

test('restoring an older protected event opts it into exactly thirty days from restoration', function (int $offset, bool $eligible) {
    $event = publicationPolicyEvent(['first_published_at' => null, 'archived_at' => now()->subDay(),
        'auto_archive_exempt_at' => now()->subMonth()]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $cleanup = app(EventCleanup::class);
    $restored = now();
    $cleanup->perform($event, $admin, 'restore', $cleanup->fingerprint($event));
    expect($event->fresh()->auto_archive_exempt_at)->toBeNull()
        ->and($event->fresh()->first_published_at)->toBeNull()
        ->and($event->fresh()->auto_archive_restored_at->toISOString())->toBe($restored->toISOString())
        ->and(app(AutomaticEventArchiver::class)->run())->toBe(0);
    $this->travelTo($restored->addDays(30)->addSeconds($offset));
    expect(app(AutomaticEventArchiver::class)->run(true))->toBe((int) $eligible)
        ->and(app(AutomaticEventArchiver::class)->run())->toBe((int) $eligible)
        ->and($event->fresh()->archived_at !== null)->toBe($eligible);
})->with([[-1, false], [0, true], [1, true]]);

test('neither restoration nor publication substitutes for thirty days after the event ends', function () {
    $event = publicationPolicyEvent(['event_date' => today()->subDays(10), 'event_end_date' => today()->subDays(5),
        'auto_archive_restored_at' => now()->subDays(40)]);
    expect(app(AutomaticEventArchiver::class)->run())->toBe(0)->and($event->fresh()->archived_at)->toBeNull();
});

test('multiple archive restore cycles keep all earlier system audits unchanged', function () {
    $event = publicationPolicyEvent();
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $cleanup = app(EventCleanup::class);
    $priorAudits = [];
    for ($cycle = 1; $cycle <= 3; $cycle++) {
        expect(app(AutomaticEventArchiver::class)->run())->toBe(1)
            ->and(app(AutomaticEventArchiver::class)->run())->toBe(0)
            ->and(EventAutoArchiveLog::where('event_id', $event->id)->count())->toBe($cycle);
        foreach ($priorAudits as $id => $attributes) {
            expect(EventAutoArchiveLog::findOrFail($id)->getRawOriginal())->toBe($attributes);
        }
        $priorAudits = EventAutoArchiveLog::all()->mapWithKeys(fn ($row) => [$row->id => $row->getRawOriginal()])->all();
        $cleanup->perform($event, $admin, 'restore', $cleanup->fingerprint($event->fresh()));
        expect(app(AutomaticEventArchiver::class)->run())->toBe(0);
        $this->travel(30)->days();
    }
});

test('manual archiving remains available during a restored visibility window', function () {
    $event = publicationPolicyEvent(['archived_at' => now()]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $cleanup = app(EventCleanup::class);
    $cleanup->perform($event, $admin, 'restore', $cleanup->fingerprint($event));
    $cleanup->perform($event, $admin, 'archive', $cleanup->fingerprint($event->fresh()));
    expect($event->fresh()->archived_at)->not->toBeNull()->and(EventAutoArchiveLog::count())->toBe(0);
});

test('publication and restoration metadata cannot be mass assigned and invalidate stale cleanup confirmations', function (string $field) {
    $event = publicationPolicyEvent(['first_published_at' => null]);
    $cleanup = app(EventCleanup::class);
    $fingerprint = $cleanup->fingerprint($event);
    $event->fill([$field => now()])->save();
    expect($event->fresh()->{$field})->toBeNull();
    DB::table('events')->where('id', $event->id)->update([$field => now()]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    expect(fn () => $cleanup->perform($event, $admin, 'archive', $fingerprint))->toThrow(ValidationException::class);
})->with(['first_published_at', 'auto_archive_restored_at']);

test('candidate publication and restoration eligibility is rechecked under the event lock', function (string $change) {
    $event = publicationPolicyEvent();
    $changed = false;
    DB::listen(function (QueryExecuted $query) use ($event, $change, &$changed) {
        if (! $changed && str_starts_with(strtolower($query->sql), 'select "id" from "events"')) {
            $changed = true;
            DB::table('events')->where('id', $event->id)->update($change === 'publication'
                ? ['first_published_at' => null] : ['auto_archive_restored_at' => now()]);
        }
    });
    expect(app(AutomaticEventArchiver::class)->run())->toBe(0)->and($changed)->toBeTrue()
        ->and(EventAutoArchiveLog::count())->toBe(0);
})->with(['publication', 'restoration']);

test('a failed restoration audit rolls back its new timestamp and preserves the old exemption', function () {
    $event = publicationPolicyEvent(['archived_at' => now(), 'auto_archive_exempt_at' => now()->subDay()]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $before = $event->getRawOriginal();
    AdminActivityLog::creating(fn () => throw new RuntimeException('Synthetic restoration audit failure.'));
    $cleanup = app(EventCleanup::class);
    expect(fn () => $cleanup->perform($event, $admin, 'restore', $cleanup->fingerprint($event)))
        ->toThrow(RuntimeException::class, 'Synthetic restoration audit failure.')
        ->and($event->fresh()->getRawOriginal())->toBe($before);
});

test('the restore confirmation describes the thirty-day opt-in rather than a permanent exemption', function () {
    $event = publicationPolicyEvent(['first_published_at' => null, 'archived_at' => now()]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->actingAs($admin)->get(route('admin.events.index', ['archive' => 'archived']))->assertOk()
        ->assertSee('30 days after restoration')->assertSee('Restoring an older event also brings it into this rule.')
        ->assertDontSee('Restored events are exempt from automatic archiving.');
});

test('missing publication policy schema fails closed without affecting manual cleanup', function () {
    $event = publicationPolicyEvent(['first_published_at' => null, 'archived_at' => now()]);
    $migration = require database_path('migrations/2026_11_19_000001_add_event_archive_publication_policy.php');
    $migration->down();
    $this->artisan('events:archive-completed --dry-run')
        ->expectsOutput('Automatic event archiving requires its database migration. No events were changed.')->assertFailed();
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $cleanup = app(EventCleanup::class);
    $cleanup->perform($event, $admin, 'restore', $cleanup->fingerprint($event->fresh()));
    expect($event->fresh()->auto_archive_exempt_at)->not->toBeNull()->and($event->fresh()->archived_at)->toBeNull();
    $migration->up();
    expect($event->fresh()->first_published_at)->toBeNull()
        ->and($event->fresh()->auto_archive_restored_at)->toBeNull()
        ->and(app(AutomaticEventArchiver::class)->run())->toBe(0);
});

test('publication policy migration preserves old rows and foreign keys while allowing repeated audit history', function () {
    $prior = config('database.default');
    config(['database.connections.policy_upgrade' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        'foreign_key_constraints' => true], 'database.default' => 'policy_upgrade']);
    try {
        Schema::create('events', function ($table) {
            $table->id();
            $table->string('title');
            $table->dateTime('auto_archive_exempt_at')->nullable();
        });
        Schema::create('event_auto_archive_logs', function ($table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events')->restrictOnDelete();
            $table->dateTime('archived_at');
        });
        DB::table('events')->insert(['id' => 1, 'title' => 'Historical published event', 'auto_archive_exempt_at' => '2026-10-01 10:00:00']);
        DB::table('event_auto_archive_logs')->insert(['id' => 1, 'event_id' => 1, 'archived_at' => '2026-09-01 10:00:00']);
        $original = (array) DB::table('events')->find(1);
        $audit = (array) DB::table('event_auto_archive_logs')->find(1);
        $foreignKeys = Schema::getForeignKeys('event_auto_archive_logs');
        $migration = require database_path('migrations/2026_11_19_000001_add_event_archive_publication_policy.php');
        $migration->up();
        expect((array) DB::table('events')->find(1))->toBe([...$original, 'first_published_at' => null, 'auto_archive_restored_at' => null])
            ->and((array) DB::table('event_auto_archive_logs')->find(1))->toBe($audit)
            ->and(Schema::getForeignKeys('event_auto_archive_logs'))->toBe($foreignKeys);
        DB::table('event_auto_archive_logs')->insert(['id' => 2, 'event_id' => 1, 'archived_at' => '2026-11-01 10:00:00']);
        expect(fn () => $migration->down())->toThrow(RuntimeException::class)
            ->and(DB::table('event_auto_archive_logs')->count())->toBe(2)
            ->and(Schema::hasColumn('events', 'first_published_at'))->toBeTrue();
        expect(fn () => DB::table('events')->where('id', 1)->delete())->toThrow(QueryException::class);
        DB::table('event_auto_archive_logs')->where('id', 2)->delete();
        $migration->down();
        expect((array) DB::table('events')->find(1))->toBe($original)
            ->and((array) DB::table('event_auto_archive_logs')->find(1))->toBe($audit);
    } finally {
        config(['database.default' => $prior]);
        DB::purge('policy_upgrade');
    }
});

test('migration rollback protects newly recorded publication and restoration timestamps', function (string $field) {
    $event = publicationPolicyEvent(['first_published_at' => null, $field => now()]);
    $before = $event->getRawOriginal();
    $migration = require database_path('migrations/2026_11_19_000001_add_event_archive_publication_policy.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class)
        ->and($event->fresh()->getRawOriginal())->toBe($before);
})->with(['first_published_at', 'auto_archive_restored_at']);
