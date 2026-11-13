<?php

use App\Models\Category;
use App\Models\CheckpointScan;
use App\Models\CheckpointScanPlan;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    Http::preventStrayRequests();
    Notification::fake();
    config(['checkpoint_scanning.enabled' => false]);
});

// Synthetic local history only; no production access or real BIB QR requests.
function checkpointHistoryFixture(User $manager, string $label = 'Alpha', int $count = 1, array $overrides = [], ?User $marshal = null): array
{
    $event = Event::forceCreate(['title' => $label.' history event', 'slug' => 'history-'.Str::uuid(),
        'venue' => 'Synthetic venue', 'manager_id' => $manager->id, 'event_date' => today(),
        'event_end_date' => today(), 'start_time' => '01:00', 'end_time' => '23:59',
        'status' => 'ongoing', 'review_status' => 'approved']);
    $category = Category::create(['event_id' => $event->id, 'name' => $label.' category',
        'distance_km' => 3, 'status' => 'open', 'started_at' => now()->subHour()]);
    $checkpoint = $category->courseCheckpoints()->create(['position' => 1, 'label' => $label.' checkpoint',
        'latitude' => 14.5, 'longitude' => 121.0]);
    $plan = CheckpointScanPlan::create(['event_id' => $event->id, 'category_id' => $category->id,
        'fingerprint' => hash('sha256', $label), 'category_started_at' => $category->started_at,
        'snapshot' => ['event_title' => $event->title, 'category_name' => $category->name]]);
    $scans = collect();
    $registrations = collect();
    for ($index = 1; $index <= $count; $index++) {
        $runner = User::factory()->create(['role' => User::ROLE_RUNNER, 'name' => 'Current profile '.$label.' '.$index]);
        $registration = Registration::create(['user_id' => $runner->id, 'event_id' => $event->id,
            'category_id' => $category->id, 'bib_number' => sprintf('%03d', $index), 'status' => 'checked_in']);
        $registrations->push($registration);
        $scans->push(CheckpointScan::create(array_replace([
            'checkpoint_scan_plan_id' => $plan->id, 'event_id' => $event->id, 'category_id' => $category->id,
            'registration_id' => $registration->id, 'checkpoint_id' => $checkpoint->id,
            'scanned_by_user_id' => ($marshal ?? $manager)->id, 'scanned_by_name' => 'Recorded marshal '.$label,
            'client_scan_id' => (string) Str::uuid(), 'request_fingerprint' => hash('sha256', $label.$index),
            'participant_name' => 'Recorded runner '.$label.' '.$index, 'bib_number' => $registration->bib_number,
            'checkpoint_label' => $checkpoint->label, 'checkpoint_position' => 1,
            'scanned_at' => now()->startOfSecond(), 'elapsed_seconds' => 3890, 'after_cutoff' => false,
        ], $overrides)));
    }

    return compact('event', 'category', 'checkpoint', 'plan', 'scans', 'registrations');
}

test('checkpoint history requires an authenticated administrative account', function () {
    $this->get(route('admin.checkpoint-history.index'))->assertRedirect(route('login'));
});

test('participants and retired roles cannot open checkpoint history', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user)->get(route('admin.checkpoint-history.index'))->assertForbidden();
})->with(['runner', 'user', 'executive', 'content_moderator']);

test('administrative verification two factor and account restrictions remain enforced', function (string $restriction) {
    $attributes = match ($restriction) {
        'unverified' => ['email_verified_at' => null],
        'two-factor' => ['two_factor_required' => true],
        'suspended' => ['suspended_at' => now()],
        'banned' => ['banned_at' => now()],
    };
    $viewer = User::factory()->create([...$attributes, 'role' => User::ROLE_EVENT_MANAGER]);
    $response = $this->actingAs($viewer)->get(route('admin.checkpoint-history.index'));
    if ($restriction === 'suspended') {
        $response->assertForbidden();
    } else {
        $response->assertRedirect($restriction === 'two-factor' ? route('profile.edit') : route('login'));
    }
})->with(['unverified', 'two-factor', 'suspended', 'banned']);

test('admins managers and legacy managers can read their history while recording is off', function (string $role) {
    $manager = User::factory()->create(['role' => $role === 'super_admin' ? 'event_manager' : $role]);
    $viewer = $role === 'super_admin' ? User::factory()->create(['role' => $role]) : $manager;
    $fixture = checkpointHistoryFixture($manager);
    $scan = $fixture['scans']->sole();
    $response = $this->actingAs($viewer)->get(route('admin.checkpoint-history.index'))->assertOk()
        ->assertSee('Checkpoint History')->assertSee('Recorded runner Alpha 1')->assertSee('BIB 001')
        ->assertSee('Alpha history event')->assertSee('Alpha category')->assertSee('1. Alpha checkpoint')
        ->assertSee('Recorded marshal Alpha')->assertSee('01:04:50')
        ->assertSee('Server time: '.config('app.timezone'))->assertSee('not finishes or official results')
        ->assertSee(route('admin.checkpoint-history.index'))
        ->assertDontSee($scan->client_scan_id)->assertDontSee($scan->request_fingerprint)
        ->assertDontSee('Current profile Alpha 1');
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store')
        ->and($response->headers->get('Pragma'))->toBe('no-cache');
    $response->assertViewHas('scans', fn ($page) => ! array_key_exists('client_scan_id', $page->first()->getAttributes())
        && ! array_key_exists('request_fingerprint', $page->first()->getAttributes()));
})->with(['super_admin', 'event_manager', 'admin']);

test('manager row filter choices counts and searches never include unassigned events', function () {
    $manager = User::factory()->create(['role' => 'event_manager']);
    $other = User::factory()->create(['role' => 'event_manager']);
    $own = checkpointHistoryFixture($manager, 'Owned');
    $foreign = checkpointHistoryFixture($other, 'Private');
    $this->actingAs($manager)->get(route('admin.checkpoint-history.index'))->assertOk()
        ->assertSee('Recorded runner Owned 1')->assertDontSee('Private history event')->assertDontSee('Private category')
        ->assertDontSee('Recorded runner Private 1')->assertDontSee('Recorded marshal Private')
        ->assertViewHas('scans', fn ($page) => $page->total() === 1 && $page->first()->id === $own['scans']->sole()->id)
        ->assertViewHas('events', fn ($events) => $events->modelKeys() === [$own['event']->id])
        ->assertViewHas('categories', fn ($categories) => $categories->modelKeys() === [$own['category']->id]);
    $this->get(route('admin.checkpoint-history.index', ['search' => 'Private']))->assertOk()
        ->assertViewHas('scans', fn ($page) => $page->total() === 0)->assertDontSee('Private history event');
    foreach (['event_id' => $foreign['event']->id, 'category_id' => $foreign['category']->id] as $key => $value) {
        $this->get(route('admin.checkpoint-history.index', [$key => $value]))->assertNotFound();
    }
});

test('admins see all assigned and unassigned event histories', function () {
    $manager = User::factory()->create(['role' => 'event_manager']);
    checkpointHistoryFixture($manager, 'Assigned');
    $unassigned = checkpointHistoryFixture($manager, 'Unassigned');
    $unassigned['event']->update(['manager_id' => null]);
    $admin = User::factory()->create(['role' => 'super_admin']);
    $this->actingAs($admin)->get(route('admin.checkpoint-history.index'))->assertOk()
        ->assertSee('Assigned history event')->assertSee('Unassigned history event')
        ->assertViewHas('scans', fn ($page) => $page->total() === 2);
});

test('assignment changes immediately remove access from the former manager', function () {
    $previous = User::factory()->create(['role' => 'event_manager']);
    $current = User::factory()->create(['role' => 'event_manager']);
    $fixture = checkpointHistoryFixture($previous);
    $fixture['event']->update(['manager_id' => $current->id]);
    $this->actingAs($previous)->get(route('admin.checkpoint-history.index'))->assertOk()
        ->assertDontSee('Recorded runner Alpha 1')->assertViewHas('scans', fn ($page) => $page->total() === 0);
    $this->get(route('admin.checkpoint-history.index', ['event_id' => $fixture['event']->id]))->assertNotFound();
    $this->actingAs($current)->get(route('admin.checkpoint-history.index'))->assertOk()->assertSee('Recorded runner Alpha 1');
});

test('event category and snapshot name or BIB searches work independently and together', function () {
    $manager = User::factory()->create(['role' => 'event_manager']);
    $alpha = checkpointHistoryFixture($manager, 'Alpha');
    checkpointHistoryFixture($manager, 'Beta');
    $this->actingAs($manager);
    foreach ([['event_id' => $alpha['event']->id], ['category_id' => $alpha['category']->id], ['search' => 'runner Alpha'],
        ['event_id' => $alpha['event']->id, 'category_id' => $alpha['category']->id, 'search' => '001']] as $filters) {
        $this->get(route('admin.checkpoint-history.index', $filters))->assertOk()
            ->assertViewHas('scans', fn ($page) => $page->total() === 1 && $page->first()->id === $alpha['scans']->sole()->id);
    }
    $this->get(route('admin.checkpoint-history.index', ['search' => '0']))->assertOk()
        ->assertViewHas('scans', fn ($page) => $page->total() === 2);
    $this->get(route('admin.checkpoint-history.index', ['search' => 'Current profile']))->assertOk()
        ->assertSee('No checkpoint visits match these filters.')->assertViewHas('scans', fn ($page) => $page->total() === 0);
});

test('a category from another selected event is rejected even when both events are assigned', function () {
    $manager = User::factory()->create(['role' => 'event_manager']);
    $alpha = checkpointHistoryFixture($manager, 'Alpha');
    $beta = checkpointHistoryFixture($manager, 'Beta');
    $this->actingAs($manager)->get(route('admin.checkpoint-history.index', [
        'event_id' => $alpha['event']->id, 'category_id' => $beta['category']->id,
    ]))->assertNotFound();
});

test('managers with no assignments get a private empty page', function () {
    checkpointHistoryFixture(User::factory()->create(['role' => 'event_manager']), 'Other');
    $manager = User::factory()->create(['role' => 'event_manager']);
    $this->actingAs($manager)->get(route('admin.checkpoint-history.index'))->assertOk()
        ->assertSee('No checkpoint visits match these filters.')->assertDontSee('Other history event')
        ->assertViewHas('events', fn ($events) => $events->isEmpty())
        ->assertViewHas('categories', fn ($categories) => $categories->isEmpty())
        ->assertViewHas('scans', fn ($page) => $page->total() === 0);
});

test('history paginates with stable newest first ordering and preserves only supported filters', function () {
    $manager = User::factory()->create(['role' => 'event_manager']);
    $fixture = checkpointHistoryFixture($manager, count: 22);
    $ids = $fixture['scans']->reverse()->pluck('id')->values();
    $filters = ['event_id' => $fixture['event']->id, 'category_id' => $fixture['category']->id,
        'search' => 'Recorded runner', 'unsupported' => 'must-not-be-linked'];
    $this->actingAs($manager)->get(route('admin.checkpoint-history.index', $filters))->assertOk()
        ->assertViewHas('scans', fn ($page) => $page->total() === 22 && $page->pluck('id')->all() === $ids->take(20)->all()
            && str_contains($page->nextPageUrl(), 'event_id='.$fixture['event']->id)
            && str_contains($page->nextPageUrl(), 'category_id='.$fixture['category']->id)
            && str_contains($page->nextPageUrl(), 'search=Recorded%20runner')
            && ! str_contains($page->nextPageUrl(), 'unsupported'));
    $this->get(route('admin.checkpoint-history.index', [...$filters, 'page' => 2]))->assertOk()
        ->assertViewHas('scans', fn ($page) => $page->pluck('id')->all() === $ids->skip(20)->values()->all());
});

test('scan time takes priority over insertion order', function () {
    $manager = User::factory()->create(['role' => 'event_manager']);
    $newer = checkpointHistoryFixture($manager, 'Newer', overrides: ['scanned_at' => now()]);
    $older = checkpointHistoryFixture($manager, 'Older', overrides: ['scanned_at' => now()->subDay()]);
    $this->actingAs($manager)->get(route('admin.checkpoint-history.index'))->assertOk()
        ->assertViewHas('scans', fn ($page) => $page->pluck('id')->all() === [$newer['scans']->sole()->id, $older['scans']->sole()->id]);
});

test('elapsed durations do not wrap after 24 hours and late visits are not labelled disqualified', function () {
    $originalTimezone = date_default_timezone_get();
    try {
        // Mirror Laravel's bootstrap when testing a different server timezone.
        config(['app.timezone' => 'Asia/Manila']);
        date_default_timezone_set('Asia/Manila');
        $manager = User::factory()->create(['role' => 'event_manager']);
        $fixture = checkpointHistoryFixture($manager, overrides: ['elapsed_seconds' => 97205, 'after_cutoff' => true,
            'scanned_at' => '2026-11-13 04:13:12']);
        $this->actingAs($manager)->get(route('admin.checkpoint-history.index'))->assertOk()
            ->assertSee('27:00:05')->assertSee('After scheduled end')->assertDontSee('Disqualified')
            ->assertSee('Nov 13, 2026 04:13:12')->assertSee('2026-11-13T04:13:12+08:00')
            ->assertSee('Server time: Asia/Manila');
        expect($fixture['registrations']->sole()->fresh()->status)->toBe('checked_in');
    } finally {
        date_default_timezone_set($originalTimezone);
    }
});

test('retired checkpoints renamed people and deleted scanning staff retain recorded names', function () {
    $manager = User::factory()->create(['role' => 'event_manager']);
    $marshal = User::factory()->create(['role' => 'super_admin']);
    $fixture = checkpointHistoryFixture($manager, marshal: $marshal);
    $fixture['checkpoint']->update(['label' => 'Renamed checkpoint', 'retired_at' => now()]);
    $marshal->delete();
    $this->actingAs($manager)->get(route('admin.checkpoint-history.index'))->assertOk()
        ->assertSee('Recorded runner Alpha 1')->assertSee('Recorded marshal Alpha')
        ->assertSee('1. Alpha checkpoint')->assertDontSee('Renamed checkpoint')->assertDontSee('Current profile Alpha 1');
});

test('snapshot and filter text is escaped rather than rendered as markup', function () {
    $manager = User::factory()->create(['role' => 'event_manager']);
    $attack = '<script>alert("history")</script>';
    checkpointHistoryFixture($manager, overrides: ['participant_name' => $attack, 'checkpoint_label' => $attack, 'scanned_by_name' => $attack]);
    $this->actingAs($manager)->get(route('admin.checkpoint-history.index', ['search' => $attack]))->assertOk()
        ->assertSee(e($attack), false)->assertDontSee($attack, false)
        ->assertViewHas('scans', fn ($page) => $page->total() === 1);
});

test('malformed history filters fail validation without exposing records', function (array $filters, string $field) {
    $manager = User::factory()->create(['role' => 'event_manager']);
    $this->actingAs($manager)->getJson(route('admin.checkpoint-history.index', $filters))->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    [['event_id' => -1], 'event_id'], [['event_id' => 'invalid'], 'event_id'], [['event_id' => ['1']], 'event_id'],
    [['category_id' => 0], 'category_id'], [['category_id' => ['1']], 'category_id'],
    [['search' => ['bad']], 'search'], [['search' => str_repeat('x', 101)], 'search'],
    [['page' => 0], 'page'], [['page' => ['2']], 'page'],
]);

test('history is GET only and viewing never changes checkpoint or business records', function () {
    $manager = User::factory()->create(['role' => 'event_manager']);
    $fixture = checkpointHistoryFixture($manager);
    $tables = array_values(array_diff(Schema::getTableListing(schemaQualified: false), ['admin_activity_logs']));
    $before = [];
    foreach ($tables as $table) {
        $before[$table] = DB::table($table)->get()->toJson();
    }
    $this->actingAs($manager)->get(route('admin.checkpoint-history.index'))->assertOk();
    foreach ($before as $table => $rows) {
        expect(DB::table($table)->get()->toJson(), $table)->toBe($rows);
    }
    expect(Route::getRoutes()->getByName('admin.checkpoint-history.index')->methods())->toBe(['GET', 'HEAD']);
    foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
        $this->call($method, route('admin.checkpoint-history.index'), ['id' => $fixture['scans']->sole()->id])->assertStatus(405);
    }
    Http::assertNothingSent();
    Notification::assertNothingSent();
});

test('missing history schema shows an unavailable notice instead of a server error or a misleading zero', function (string $table) {
    $manager = User::factory()->create(['role' => 'event_manager']);
    // Test-only SQLite memory connection; application databases are never migrated here.
    expect(DB::connection()->getDriverName())->toBe('sqlite')->and(config('database.connections.sqlite.database'))->toBe(':memory:');
    Schema::disableForeignKeyConstraints();
    try {
        Schema::drop($table);
    } finally {
        Schema::enableForeignKeyConstraints();
    }
    $this->actingAs($manager)->get(route('admin.checkpoint-history.index'))->assertStatus(503)
        ->assertSee('Checkpoint history is not available in this environment yet.')
        ->assertSee('No records have been changed.')->assertDontSee('0 recorded visits')
        ->assertDontSee('No checkpoint visits match these filters.')->assertViewHas('historyAvailable', false);
})->with(['checkpoint_scans', 'checkpoint_scan_plans']);
