<?php

use App\Models\Certificate;
use App\Models\Event;
use App\Models\FinishScan;
use App\Models\RaceResult;
use App\Models\Registration;
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
    $this->travelTo(Carbon::parse('2026-11-20 10:00:00', config('app.timezone')));
});

function resultsArchiveFixture(User $manager, bool $archived, bool $started = true): array
{
    $event = Event::forceCreate(['title' => ($archived ? 'Archived' : 'Visible').' synthetic result event',
        'slug' => 'results-archive-'.Str::uuid(), 'description' => 'Synthetic history.', 'venue' => 'Synthetic venue',
        'manager_id' => $manager->id, 'event_date' => today()->subDay(), 'event_end_date' => today()->subDay(),
        'registration_deadline' => today()->subDay(), 'start_time' => '06:00', 'end_time' => '09:00',
        'interest_type' => 'Marathon', 'type_details' => ['cutoff_time' => '3 hours'], 'banner_image' => 'events/synthetic.png',
        'review_status' => 'approved', 'status' => 'completed', 'archived_at' => $archived ? now() : null]);
    $category = $event->categories()->create(['name' => 'Synthetic 3K Open', 'distance_km' => 3, 'status' => 'open',
        'slot_limit' => 100, 'price_cents' => 0, 'checkpoint_map_image' => 'categories/synthetic.png',
        'started_at' => $started ? today()->subDay()->setTime(6, 0) : null, 'started_by_user_id' => $started ? $manager->id : null]);
    $registration = Registration::create(['event_id' => $event->id, 'category_id' => $category->id,
        'user_id' => User::factory()->create(['role' => User::ROLE_RUNNER])->id, 'status' => 'completed',
        'bib_number' => 'ARCHIVE-'.Str::random(8), 'registered_at' => today()->subDay()]);
    $identity = ['event_id' => $event->id, 'category_id' => $category->id,
        'registration_id' => $registration->id, 'user_id' => $registration->user_id];
    RaceResult::create([...$identity, 'finish_time' => '00:20:00', 'rank_overall' => 1, 'rank_category' => 1]);
    if ($started) {
        FinishScan::create([...$identity, 'bib_number' => $registration->bib_number, 'scanned_at' => today()->subDay()->setTime(6, 20),
            'elapsed_seconds' => 1200, 'elapsed_time' => '00:20:00', 'scanned_by_user_id' => $manager->id,
            'client_scan_id' => Str::uuid(), 'status' => FinishScan::STATUS_PUBLISHED]);
    }
    Certificate::forceCreate([...$identity, 'certificate_number' => 'ARCHIVE-'.Str::uuid(),
        'verification_token' => (string) Str::uuid(), 'issued_at' => now(), 'participant_name' => 'Synthetic participant',
        'event_title' => $event->title, 'category_name' => $category->name, 'event_date' => $event->event_date,
        'finish_time' => '00:20:00']);

    return [$event->fresh(), $category, $registration];
}

function resultsArchiveState(): array
{
    $state = [];
    // The existing Admin middleware audits page views; business history must
    // remain unchanged while those legitimate access entries are recorded.
    foreach (array_diff(Schema::getTableListing(schemaQualified: false), ['admin_activity_logs']) as $table) {
        $state[$table] = hash('sha256', DB::table($table)->get()->toJson());
    }

    return $state;
}

test('Category Starts hides archived events for Admin and assigned Event Manager without changing records', function (string $role, bool $started) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $viewer = $role === User::ROLE_EVENT_MANAGER ? $manager : User::factory()->create(['role' => $role]);
    [$active, $activeCategory] = resultsArchiveFixture($manager, false, $started);
    [$archived, $archivedCategory] = resultsArchiveFixture($manager, true, $started);
    $state = resultsArchiveState();
    $this->actingAs($viewer)->get(route('admin.results.index'))->assertOk()
        ->assertViewHas('raceCategories', fn ($rows) => $rows->pluck('id')->all() === [$activeCategory->id])
        ->assertViewHas('registrations', fn ($rows) => $rows->total() === 2);
    expect(resultsArchiveState())->toBe($state)
        ->and($archivedCategory->fresh())->not->toBeNull()->and($archived->raceResults()->count())->toBe(1)
        ->and($active->categories()->count())->toBe(1);
})->with([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER])->with([true, false]);

test('Archived Events retains an event-filtered Results link and full historical result access', function (string $role) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $viewer = $role === User::ROLE_EVENT_MANAGER ? $manager : User::factory()->create(['role' => $role]);
    [$event, , $registration] = resultsArchiveFixture($manager, true);
    $this->actingAs($viewer)->get(route('admin.events.show', $event))->assertOk()
        ->assertSee(route('admin.results.index', ['event_id' => $event->id]), false);
    $state = resultsArchiveState();
    $this->get(route('admin.results.index', ['event_id' => $event->id]))->assertOk()
        ->assertViewHas('raceCategories', fn ($rows) => $rows->isEmpty())
        ->assertViewHas('registrations', fn ($rows) => $rows->pluck('id')->all() === [$registration->id])
        ->assertSee($registration->bib_number)->assertSee('00:20:00')->assertSee('Verify certificate')
        ->assertSee('Archived event results remain available below.');
    expect(resultsArchiveState())->toBe($state);
})->with([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER]);

test('restoring an archived event returns its category to Category Starts without rewriting history', function (string $role) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $viewer = $role === User::ROLE_EVENT_MANAGER ? $manager : $admin;
    [$event, $category] = resultsArchiveFixture($manager, true);
    $before = [$category->fresh()->getRawOriginal(), $event->raceResults()->sole()->getRawOriginal(),
        $event->certificates()->sole()->getRawOriginal(), $event->finishScans()->sole()->getRawOriginal()];
    $this->actingAs($viewer)->get(route('admin.results.index'))->assertOk()
        ->assertViewHas('raceCategories', fn ($rows) => $rows->isEmpty());
    app(EventCleanup::class)->perform($event, $admin, 'restore', app(EventCleanup::class)->fingerprint($event->fresh()));
    $this->get(route('admin.results.index'))->assertOk()
        ->assertViewHas('raceCategories', fn ($rows) => $rows->pluck('id')->all() === [$category->id]);
    expect([$category->fresh()->getRawOriginal(), $event->raceResults()->sole()->getRawOriginal(),
        $event->certificates()->sole()->getRawOriginal(), $event->finishScans()->sole()->getRawOriginal()])->toBe($before);
})->with([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER]);

test('archive history does not widen Event Manager ownership or guest and participant access', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    [$event] = resultsArchiveFixture($manager, true);
    $other = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($other)->get(route('admin.results.index', ['event_id' => $event->id]))->assertOk()
        ->assertViewHas('raceCategories', fn ($rows) => $rows->isEmpty())
        ->assertViewHas('registrations', fn ($rows) => $rows->isEmpty())
        ->assertDontSee($event->title);
    $this->get(route('admin.events.show', $event))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_RUNNER]))
        ->get(route('admin.results.index'))->assertForbidden();
    auth()->logout();
    $this->get(route('admin.results.index'))->assertRedirect(route('login'));
});
