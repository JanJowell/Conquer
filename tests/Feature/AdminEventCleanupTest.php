<?php

use App\Models\AdminActivityLog;
use App\Models\Certificate;
use App\Models\CheckpointScan;
use App\Models\CheckpointScanPlan;
use App\Models\Event;
use App\Models\Payment;
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
    $this->travelTo(Carbon::parse('2026-11-15 10:00:00', config('app.timezone')));
});

function cleanupEventFixture(User $manager, bool $completed = false, array $overrides = []): Event
{
    $date = $completed ? today()->subDay() : today()->addMonth();
    $event = Event::forceCreate(array_replace([
        'title' => 'Synthetic cleanup event', 'slug' => 'cleanup-'.Str::uuid(),
        'description' => 'Synthetic event cleanup verification.', 'venue' => 'Synthetic meeting point',
        'manager_id' => $manager->id, 'event_date' => $date, 'event_end_date' => $date,
        'registration_deadline' => $date, 'start_time' => '06:00', 'end_time' => '12:00',
        'interest_type' => 'Marathon', 'type_details' => ['cutoff_time' => '6 hours'],
        'banner_image' => 'events/synthetic.png', 'review_status' => $completed ? 'approved' : 'draft',
        'status' => $completed ? 'completed' : 'draft',
    ], $overrides));
    if ($completed) {
        $event->categories()->create(['name' => 'Synthetic 3K', 'distance_km' => 3, 'status' => 'open',
            'slot_limit' => 100, 'price_cents' => 0, 'checkpoint_map_image' => 'categories/synthetic.png']);
    }

    return $event->fresh();
}

function cleanupInput(Event $event): array
{
    return ['cleanup_fingerprint' => app(EventCleanup::class)->fingerprint($event->fresh()),
        'confirmation' => '1', 'confirmation_title' => $event->title];
}

function cleanupRequest(string $action, Event $event, ?array $input = null)
{
    $route = $action === 'delete' ? 'admin.events.cleanup.destroy' : 'admin.events.'.$action;

    return test()->from(route('admin.events.index'))->call($action === 'delete' ? 'DELETE' : 'POST',
        route($route, $event), $input ?? cleanupInput($event));
}

function cleanupDatabaseState(array $except = []): array
{
    $state = [];
    foreach (array_diff(Schema::getTableListing(schemaQualified: false), $except) as $table) {
        $rows = DB::table($table)->get()->map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR))->sort()->values();
        $state[$table] = hash('sha256', $rows->toJson());
    }
    ksort($state);

    return $state;
}

function cleanupNavigationXPath(string $html): DOMXPath
{
    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument;
    try {
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    return new DOMXPath($document);
}

test('event cleanup requires a signed-in Admin and cannot be used by other roles', function (string $role, string $action) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = cleanupEventFixture($manager);
    if ($role !== 'guest') {
        $user = $role === User::ROLE_EVENT_MANAGER ? $manager : User::factory()->create(['role' => $role]);
        $this->actingAs($user);
    }
    $before = cleanupDatabaseState(['admin_activity_logs']);
    $response = cleanupRequest($action, $event);
    $role === 'guest' ? $response->assertRedirect(route('login')) : $response->assertForbidden();
    expect(cleanupDatabaseState(['admin_activity_logs']))->toBe($before);
})->with(['guest', 'runner', 'event_manager', 'admin', 'executive', 'content_moderator'])->with(['delete', 'archive', 'restore']);

test('administrative account restrictions also protect cleanup', function (string $restriction, string $action) {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, ...match ($restriction) {
        'unverified' => ['email_verified_at' => null], 'suspended' => ['suspended_at' => now()],
        'banned' => ['banned_at' => now()], 'two_factor' => ['two_factor_required' => true],
    }]);
    $event = cleanupEventFixture(User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]));
    $before = $event->getRawOriginal();
    $this->actingAs($admin);
    expect(cleanupRequest($action, $event)->getStatusCode())->toBeIn([302, 403, 423]);
    expect($event->fresh()->getRawOriginal())->toBe($before)
        ->and(AdminActivityLog::where('action', 'like', 'event.cleanup.%')->count())->toBe(0);
})->with(['unverified', 'suspended', 'banned', 'two_factor'])->with(['delete', 'archive', 'restore']);

test('Admin deletes only a confirmed empty never-submitted draft without orphaning its automatic announcement', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = cleanupEventFixture(User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]));
    expect($event->announcements()->where('is_auto_generated', true)->count())->toBe(1);
    $this->actingAs($admin);
    cleanupRequest('delete', $event)->assertRedirect(route('admin.events.index', ['archive' => 'active']))->assertSessionHasNoErrors();
    expect(Event::find($event->id))->toBeNull()
        ->and(DB::table('announcements')->where('event_id', $event->id)->count())->toBe(0)
        ->and(AdminActivityLog::where('action', 'event.cleanup.delete event='.$event->id)->count())->toBe(1);
});

test('cleanup confirmation and the exact current title are enforced by the server', function (string $invalid) {
    $event = cleanupEventFixture(User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]));
    $input = cleanupInput($event);
    match ($invalid) {
        'missing_confirmation' => $input['confirmation'] = '',
        'rejected_confirmation' => $input['confirmation'] = '0',
        'missing_title' => $input['confirmation_title'] = '',
        'wrong_title' => $input['confirmation_title'] = 'Another event',
        'missing_fingerprint' => $input['cleanup_fingerprint'] = '',
        'invalid_fingerprint' => $input['cleanup_fingerprint'] = 'not-a-fingerprint',
    };
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));
    // The Admin fixture is intentionally outside the event-state comparison.
    $before = cleanupDatabaseState(['admin_activity_logs']);
    cleanupRequest('delete', $event, $input)->assertRedirect()->assertSessionHasErrors();
    expect(cleanupDatabaseState(['admin_activity_logs']))->toBe($before);
})->with(['missing_confirmation', 'rejected_confirmation', 'missing_title', 'wrong_title', 'missing_fingerprint', 'invalid_fingerprint']);

test('submitted published returned and formerly submitted events cannot be deleted', function (array $overrides) {
    $event = cleanupEventFixture(User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]), false, $overrides);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));
    $before = cleanupDatabaseState(['admin_activity_logs']);
    cleanupRequest('delete', $event)->assertRedirect()->assertSessionHasErrors('cleanup');
    expect(cleanupDatabaseState(['admin_activity_logs']))->toBe($before);
})->with([
    [['review_status' => 'pending_review']], [['review_status' => 'approved']],
    [['review_status' => 'changes_requested']], [['review_status' => 'discarded']],
    [['submitted_at' => '2026-11-14 08:00:00']], [['approved_at' => '2026-11-14 08:00:00']],
    [['review_version' => 2]], [['submitted_setup_hash' => 'previous-submission']],
]);

test('even unused related setup or manual announcements prevent permanent deletion', function (string $dependency) {
    $event = cleanupEventFixture(User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]));
    match ($dependency) {
        'category' => $event->categories()->create(['name' => 'Unused category', 'distance_km' => 1]),
        'payment_option' => $event->paymentMethods()->create(['provider' => 'GCash', 'account_name' => 'Synthetic', 'account_number' => '09170000000']),
        'announcement' => $event->announcements()->create(['title' => 'Manual draft', 'content' => 'Retain me', 'is_published' => false]),
        'badge' => $event->eBadges()->create(['title' => 'Synthetic award']),
        'review' => $event->reviews()->create(['action' => 'submit', 'to_status' => 'pending_review', 'review_version' => 1, 'setup_hash' => str_repeat('a', 64)]),
        'revision' => cleanupEventFixture($event->manager, false, ['revision_of_id' => $event->id]),
    };
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));
    $before = cleanupDatabaseState(['admin_activity_logs']);
    cleanupRequest('delete', $event)->assertRedirect()->assertSessionHasErrors('cleanup');
    expect(cleanupDatabaseState(['admin_activity_logs']))->toBe($before);
})->with(['category', 'payment_option', 'announcement', 'badge', 'review', 'revision']);

test('cleanup rejects stale confirmations and rechecks new dependencies', function (string $change) {
    $event = cleanupEventFixture(User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]));
    $input = cleanupInput($event);
    match ($change) {
        'title' => $event->update(['title' => 'Changed after confirmation opened']),
        'review' => $event->forceFill(['review_status' => 'pending_review', 'review_version' => 1])->save(),
        'category' => $event->categories()->create(['name' => 'Added after page load', 'distance_km' => 1]),
    };
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));
    $before = cleanupDatabaseState(['admin_activity_logs']);
    cleanupRequest('delete', $event, $input)->assertRedirect()->assertSessionHasErrors('cleanup');
    expect(cleanupDatabaseState(['admin_activity_logs']))->toBe($before);
})->with(['title', 'review', 'category']);

test('only completed original events without an active revision can be archived', function (string $restriction) {
    $event = cleanupEventFixture(User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]), true);
    if (in_array($restriction, ['upcoming', 'ongoing'], true)) {
        $date = $restriction === 'upcoming' ? today()->addMonth() : today();
        $event->update(['event_date' => $date, 'event_end_date' => $date]);
    } elseif (str_starts_with($restriction, 'revision_')) {
        cleanupEventFixture($event->manager, false, ['revision_of_id' => $event->id, 'review_status' => substr($restriction, 9)]);
    } else {
        $event->forceFill($restriction === 'copy' ? ['revision_of_id' => $event->id] : ['review_status' => $restriction])->save();
    }
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));
    $before = cleanupDatabaseState(['admin_activity_logs']);
    cleanupRequest('archive', $event)->assertRedirect()->assertSessionHasErrors('cleanup');
    expect(cleanupDatabaseState(['admin_activity_logs']))->toBe($before);
})->with(['upcoming', 'ongoing', 'draft', 'pending_review', 'changes_requested', 'copy',
    'revision_draft', 'revision_pending_review', 'revision_changes_requested']);

test('archiving and restoring preserve every non-event business row and participant API response', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = cleanupEventFixture($manager, true);
    $category = $event->categories()->sole();
    $plain = 'synthetic-cleanup-token';
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER, 'api_token' => hash('sha256', $plain), 'api_token_expires_at' => now()->addDay()]);
    $registration = Registration::create(['user_id' => $runner->id, 'event_id' => $event->id, 'category_id' => $category->id,
        'status' => 'completed', 'bib_number' => '001', 'payment_required' => true, 'payment_status' => 'paid']);
    RaceResult::create(['registration_id' => $registration->id, 'user_id' => $runner->id, 'event_id' => $event->id,
        'category_id' => $category->id, 'finish_time' => '00:25:00']);
    Payment::create(['registration_id' => $registration->id, 'user_id' => $runner->id, 'event_id' => $event->id,
        'category_id' => $category->id, 'amount_cents' => 10000, 'status' => 'paid']);
    $certificate = Certificate::create(['registration_id' => $registration->id, 'user_id' => $runner->id,
        'event_id' => $event->id, 'category_id' => $category->id, 'certificate_number' => 'SYNTHETIC-CLEANUP-001',
        'verification_token' => (string) Str::uuid(), 'participant_name' => $runner->name,
        'event_title' => $event->title, 'category_name' => $category->name, 'issued_at' => now()]);
    $point = $category->courseCheckpoints()->create(['position' => 1, 'label' => 'Retained checkpoint', 'latitude' => 14.5, 'longitude' => 121]);
    $plan = CheckpointScanPlan::create(['event_id' => $event->id, 'category_id' => $category->id,
        'fingerprint' => str_repeat('a', 64), 'category_started_at' => now()->subDay(), 'snapshot' => ['event_title' => $event->title]]);
    CheckpointScan::create(['checkpoint_scan_plan_id' => $plan->id, 'event_id' => $event->id, 'category_id' => $category->id,
        'registration_id' => $registration->id, 'checkpoint_id' => $point->id, 'scanned_by_user_id' => $manager->id,
        'scanned_by_name' => $manager->name, 'client_scan_id' => (string) Str::uuid(), 'request_fingerprint' => str_repeat('b', 64),
        'participant_name' => $runner->name, 'bib_number' => '001', 'checkpoint_label' => $point->label,
        'checkpoint_position' => 1, 'scanned_at' => now()->subDay(), 'elapsed_seconds' => 300, 'after_cutoff' => false]);
    $headers = ['Authorization' => 'Bearer '.$plain];
    $activity = $this->getJson('/api/my-registrations', $headers)->assertOk()->json();
    $certificates = $this->getJson('/api/certificates', $headers)->assertOk()->json();
    $before = cleanupDatabaseState(['events', 'admin_activity_logs']);
    $eventState = $event->fresh()->getRawOriginal();
    $this->actingAs($admin);
    cleanupRequest('archive', $event)->assertRedirect(route('admin.events.index', ['archive' => 'archived']))->assertSessionHasNoErrors();
    expect($event->fresh()->archived_at)->not->toBeNull()
        ->and($event->fresh()->archived_by_user_id)->toBe($admin->id)
        ->and($event->fresh()->effective_status)->toBe('completed');
    $archived = $event->fresh()->getRawOriginal();
    foreach (['archived_at', 'archived_by_user_id', 'updated_at'] as $field) {
        unset($archived[$field], $eventState[$field]);
    }
    expect($archived)->toBe($eventState)->and(cleanupDatabaseState(['events', 'admin_activity_logs']))->toBe($before);
    $this->getJson('/api/my-registrations', $headers)->assertOk()->assertExactJson($activity);
    $this->getJson('/api/certificates', $headers)->assertOk()->assertExactJson($certificates);
    $this->get(route('certificates.verify', $certificate->verification_token))->assertOk();
    $this->actingAs($manager)->get(route('admin.checkpoint-history.index', ['event_id' => $event->id]))
        ->assertOk()->assertSee('Retained checkpoint');
    $this->actingAs($admin);
    cleanupRequest('delete', $event)->assertSessionHasErrors('cleanup');
    cleanupRequest('restore', $event)->assertRedirect(route('admin.events.index', ['archive' => 'active']))->assertSessionHasNoErrors();
    expect($event->fresh()->archived_at)->toBeNull()->and($event->fresh()->archived_by_user_id)->toBeNull()
        ->and(cleanupDatabaseState(['events', 'admin_activity_logs']))->toBe($before)
        ->and(AdminActivityLog::where('action', 'like', 'event.cleanup.%')->count())->toBe(2);
    Http::assertNothingSent();
});

test('archived filtering preserves ownership search pagination and Admin-only action visibility', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $other = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $draft = cleanupEventFixture($manager);
    $completed = cleanupEventFixture($manager, true);
    $archived = cleanupEventFixture($manager, true, ['archived_at' => now(), 'archived_by_user_id' => $admin->id]);
    cleanupEventFixture($other, true, ['archived_at' => now()]);
    $this->actingAs($admin)->get(route('admin.events.index'))->assertOk()
        ->assertSee('Delete empty draft')->assertSee('Archive event')->assertDontSee('Restore event')
        ->assertDontSee('data-open-event-modal="edit-event-', false)
        ->assertViewHas('events', fn ($page) => $page->pluck('id')->all() === [$completed->id, $draft->id]);
    $this->get(route('admin.events.index', ['archive' => 'archived']))->assertOk()->assertSee('Restore event')->assertDontSee('Delete empty draft');
    $this->actingAs($manager)->get(route('admin.events.index', ['archive' => 'archived', 'search' => 'Synthetic']))->assertOk()
        ->assertViewHas('events', fn ($page) => $page->pluck('id')->all() === [$archived->id])
        ->assertDontSee('data-event-cleanup-open=', false);
    $this->get(route('admin.events.index', ['archive' => 'all']))->assertOk()
        ->assertViewHas('archiveFilter', 'active')
        ->assertViewHas('events', fn ($page) => $page->total() === 2);
    $this->get(route('admin.events.index', ['archive' => 'invalid']))->assertSessionHasErrors('archive');
});

test('cleanup does not grant Admin access to create or edit event setup', function () {
    $event = cleanupEventFixture(User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]));
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));
    $this->get(route('admin.events.create'))->assertForbidden();
    $this->get(route('admin.events.edit', $event))->assertForbidden();
    $this->put(route('admin.events.update', $event), [])->assertForbidden();
    $this->delete(route('admin.events.destroy', $event))->assertForbidden();
});

test('a failed audit rolls back both cleanup metadata and permanent draft deletion', function (string $action) {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = cleanupEventFixture(User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]), $action !== 'delete');
    if ($action === 'restore') {
        $event->forceFill(['archived_at' => now(), 'archived_by_user_id' => $admin->id])->save();
    }
    $before = cleanupDatabaseState();
    AdminActivityLog::creating(fn () => throw new RuntimeException('Synthetic failed audit.'));
    expect(fn () => app(EventCleanup::class)->perform($event, $admin, $action, cleanupInput($event)['cleanup_fingerprint'], $event->title))
        ->toThrow(RuntimeException::class);
    expect(cleanupDatabaseState())->toBe($before);
})->with(['archive', 'delete', 'restore']);

test('repeated archive and restore requests cannot duplicate successful cleanup outcomes', function (string $action) {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = cleanupEventFixture(User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]), true,
        $action === 'restore' ? ['archived_at' => now(), 'archived_by_user_id' => $admin->id] : []);
    $input = cleanupInput($event);
    $this->actingAs($admin);
    cleanupRequest($action, $event, $input)->assertSessionHasNoErrors();
    $after = cleanupDatabaseState(['admin_activity_logs']);
    cleanupRequest($action, $event, $input)->assertSessionHasErrors('cleanup');
    cleanupRequest($action, $event)->assertSessionHasErrors('cleanup');
    expect(cleanupDatabaseState(['admin_activity_logs']))->toBe($after)
        ->and(AdminActivityLog::where('action', 'event.cleanup.'.$action.' event='.$event->id)->count())->toBe(1);
})->with(['archive', 'restore']);

test('every foreign-key reference to events has an explicit cleanup dependency guard', function () {
    $guarded = (new ReflectionClass(EventCleanup::class))->getConstant('DEPENDENT_TABLES');
    // Revisions/self references and automatic announcements have dedicated rules.
    $guarded = [...$guarded, 'events', 'event_revisions', 'announcements'];
    foreach (Schema::getTableListing(schemaQualified: false) as $table) {
        foreach (Schema::getForeignKeys($table) as $foreign) {
            if ($foreign['foreign_table'] === 'events') {
                expect($guarded, $table)->toContain($table);
            }
        }
    }
});

test('archive metadata is not mass assignable and rollback refuses to discard an archive', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = cleanupEventFixture($manager, true);
    $event->fill(['archived_at' => now(), 'archived_by_user_id' => $manager->id])->save();
    expect($event->fresh()->archived_at)->toBeNull()->and($event->fresh()->archived_by_user_id)->toBeNull();
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    app(EventCleanup::class)->perform($event, $admin, 'archive', cleanupInput($event)['cleanup_fingerprint']);
    $migration = require database_path('migrations/2026_11_15_000001_add_event_archive_metadata.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class)
        ->and(Schema::hasColumn('events', 'archived_at'))->toBeTrue();
});

test('missing archive migration leaves the old list available and cleanup fails closed', function () {
    $event = cleanupEventFixture(User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]));
    $input = cleanupInput($event);
    $migration = require database_path('migrations/2026_11_15_000001_add_event_archive_metadata.php');
    $migration->down();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));
    $this->get(route('admin.events.index'))->assertOk()->assertSee('Event cleanup is unavailable')
        ->assertDontSee('data-event-cleanup-open=', false)
        ->assertDontSee('aria-label="Event sections"', false);
    $this->get(route('admin.events.index', ['archive' => 'archived']))->assertOk()
        ->assertViewHas('events', fn ($page) => $page->total() === 0);
    cleanupRequest('delete', $event, $input)->assertSessionHasErrors('cleanup');
    expect(Event::find($event->id))->not->toBeNull();
});

test('event sections replace the dropdown and select exactly one separate list', function (string $role, string $section) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $viewer = $role === User::ROLE_EVENT_MANAGER ? $manager : User::factory()->create(['role' => $role]);
    $active = cleanupEventFixture($manager, true, ['title' => 'Visible completed event']);
    $archived = cleanupEventFixture($manager, true, ['title' => 'Archived completed event', 'archived_at' => now()]);
    $query = $section === 'active' ? [] : ['archive' => $section];
    $response = $this->actingAs($viewer)->get(route('admin.events.index', $query))->assertOk()
        ->assertViewHas('events', fn ($page) => $page->pluck('id')->all() === [($section === 'active' ? $active : $archived)->id]);
    $xpath = cleanupNavigationXPath($response->getContent());
    $links = $xpath->query('//nav[@aria-label="Event sections"]/a');
    $selected = $xpath->query('//nav[@aria-label="Event sections"]/a[@aria-current="page"]');
    expect($links->length)->toBe(2)
        ->and(trim($links->item(0)->textContent))->toBe('Events')
        ->and(trim($links->item(1)->textContent))->toBe('Archived Events')
        ->and($selected->length)->toBe(1)
        ->and(trim($selected->item(0)->textContent))->toBe($section === 'active' ? 'Events' : 'Archived Events')
        ->and($xpath->query('//select[@name="archive"]')->length)->toBe(0)
        ->and($xpath->query('//option[@value="all"]')->length)->toBe(0)
        ->and($xpath->query('//form[@data-event-list-filters]//input[@name="search"]')->length)->toBe(1)
        ->and($xpath->query('//form[@data-event-list-filters]//select[@name="interest_type"]')->length)->toBe(1)
        ->and($xpath->query('//form[@data-event-list-filters]//select[@name="status"]')->length)->toBe($section === 'active' ? 1 : 0)
        ->and($xpath->query('//form[@data-event-list-filters]//select[@name="review_status"]')->length)->toBe($section === 'active' ? 1 : 0)
        ->and($xpath->query('//a[@href="'.route('admin.events.create').'"]')->length)->toBe($section === 'active' && $role === User::ROLE_EVENT_MANAGER ? 1 : 0);
})->with([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER])->with(['active', 'archived']);

test('section links retain only supported filters and reset pagination while Clear stays in the current section', function (string $section) {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $filters = ['search' => 'Synthetic <checkpoint> & trail', 'interest_type' => 'Marathon', 'status' => 'completed', 'review_status' => 'approved'];
    $before = cleanupDatabaseState(['admin_activity_logs']);
    $response = $this->actingAs($admin)->get(route('admin.events.index', [
        ...$filters, 'archive' => $section, 'page' => 2, 'unrelated' => 'discard-me',
    ]))->assertOk();
    $xpath = cleanupNavigationXPath($response->getContent());
    foreach ($xpath->query('//nav[@aria-label="Event sections"]/a') as $position => $link) {
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
        $keptFilters = $section === 'active' && $position === 0 ? $filters : array_intersect_key($filters, array_flip(['search', 'interest_type']));
        expect($query)->toBe([...$keptFilters, 'archive' => $position === 0 ? 'active' : 'archived'])
            ->and(parse_url($link->getAttribute('href'), PHP_URL_PATH))->toBe('/admin/events');
    }
    $hidden = $xpath->query('//form[@data-event-list-filters]/input[@type="hidden"][@name="archive"]');
    $clear = $xpath->query('//a[@aria-label="Clear event filters"]');
    parse_str(parse_url($clear->item(0)->getAttribute('href'), PHP_URL_QUERY), $clearQuery);
    expect($hidden->length)->toBe(1)
        ->and($hidden->item(0)->getAttribute('value'))->toBe($section)
        ->and($clearQuery)->toBe(['archive' => $section])
        ->and(cleanupDatabaseState(['admin_activity_logs']))->toBe($before)
        ->and(AdminActivityLog::where('action', 'like', 'event.cleanup.%')->count())->toBe(0);
    $this->get($clear->item(0)->getAttribute('href'))->assertOk()->assertViewHas('archiveFilter', $section);
    expect(cleanupDatabaseState(['admin_activity_logs']))->toBe($before);
})->with(['active', 'archived']);

test('both sections preserve filters newest-first pagination and assigned-manager scope', function (string $role, string $section) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $viewer = $role === User::ROLE_EVENT_MANAGER ? $manager : User::factory()->create(['role' => $role]);
    $events = collect();
    for ($index = 12; $index >= 1; $index--) {
        $events->push(cleanupEventFixture($manager, true, [
            'title' => 'Synthetic navigation '.$index, 'created_at' => now()->subDays(30)->addMinutes($index),
            'archived_at' => $section === 'archived' ? now() : null,
        ]));
    }
    cleanupEventFixture($manager, true, ['title' => 'Synthetic navigation opposite section',
        'archived_at' => $section === 'active' ? now() : null]);
    if ($role === User::ROLE_EVENT_MANAGER) {
        cleanupEventFixture(User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]), true,
            ['title' => 'Synthetic navigation other manager', 'archived_at' => $section === 'archived' ? now() : null]);
    }
    $filters = ['archive' => $section, 'search' => 'Synthetic navigation', 'interest_type' => 'Marathon',
        'status' => 'completed', 'review_status' => 'approved'];
    $response = $this->actingAs($viewer)->get(route('admin.events.index', $filters))->assertOk()
        ->assertViewHas('events', fn ($page) => $page->total() === 12 && $page->pluck('id')->all() === $events->take(10)->pluck('id')->all());
    $paginator = $response->viewData('events');
    parse_str(parse_url($paginator->nextPageUrl(), PHP_URL_QUERY), $query);
    $paginationFilters = $section === 'archived' ? array_diff_key($filters, array_flip(['status', 'review_status'])) : $filters;
    expect($query)->toBe([...$paginationFilters, 'page' => '2']);
    $this->get($paginator->nextPageUrl())->assertOk()
        ->assertViewHas('archiveFilter', $section)
        ->assertViewHas('events', fn ($page) => $page->pluck('id')->all() === $events->skip(10)->pluck('id')->all());
})->with([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER])->with(['active', 'archived']);

test('the archived section has an explicit empty state without destructive actions', function (string $role) {
    $viewer = User::factory()->create(['role' => $role]);
    $this->actingAs($viewer)->get(route('admin.events.index', ['archive' => 'archived']))->assertOk()
        ->assertSee('No archived events found for the current filters.')
        ->assertDontSee('data-event-cleanup-open=', false)
        ->assertViewHas('events', fn ($page) => $page->total() === 0);
})->with([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER]);

test('manager archived navigation is read-only and does not expose other assignments', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $owned = cleanupEventFixture($manager, true, ['archived_at' => now()]);
    $other = cleanupEventFixture(User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]), true, ['archived_at' => now()]);
    $before = cleanupDatabaseState(['admin_activity_logs']);
    $this->actingAs($manager)->get(route('admin.events.index', ['archive' => 'archived']))->assertOk()
        ->assertViewHas('events', fn ($page) => $page->pluck('id')->all() === [$owned->id])
        ->assertDontSee('data-event-cleanup-open=', false)
        ->assertDontSee('data-open-event-modal="edit-event-', false)
        ->assertDontSee('data-open-event-modal="delete-event-', false)
        ->assertDontSee('js/event-cleanup.js');
    $this->get(route('admin.events.show', $other))->assertForbidden();
    expect(cleanupDatabaseState(['admin_activity_logs']))->toBe($before)
        ->and(AdminActivityLog::where('action', 'like', 'event.cleanup.%')->count())->toBe(0);
});

test('legacy all links select Events without mixing archived rows', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $active = cleanupEventFixture($manager);
    cleanupEventFixture($manager, true, ['archived_at' => now()]);
    $response = $this->actingAs($manager)->get(route('admin.events.index', ['archive' => 'all']))->assertOk()
        ->assertViewHas('archiveFilter', 'active')
        ->assertViewHas('events', fn ($page) => $page->pluck('id')->all() === [$active->id]);
    parse_str(parse_url($response->viewData('events')->url(2), PHP_URL_QUERY), $query);
    expect($query['archive'])->toBe('active');
});

test('event sections do not grant access to guests or participants', function (string $role) {
    if ($role !== 'guest') {
        $this->actingAs(User::factory()->create(['role' => $role]));
    }
    $before = cleanupDatabaseState();
    $response = $this->get(route('admin.events.index', ['archive' => 'archived']));
    $role === 'guest' ? $response->assertRedirect(route('login')) : $response->assertForbidden();
    expect(cleanupDatabaseState())->toBe($before);
})->with(['guest', User::ROLE_RUNNER]);

test('archived results ignore stale hidden filters and remove them from all navigation', function (string $role, array $hiddenFilters) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $viewer = $role === User::ROLE_EVENT_MANAGER ? $manager : User::factory()->create(['role' => $role]);
    $archived = cleanupEventFixture($manager, true, ['title' => 'Matching archive event', 'archived_at' => now()]);
    $active = cleanupEventFixture($manager, true, ['title' => 'Matching current event']);
    $common = ['search' => 'Matching', 'interest_type' => 'Marathon'];
    $before = cleanupDatabaseState(['admin_activity_logs']);
    $response = $this->actingAs($viewer)->get(route('admin.events.index', [
        'archive' => 'archived', ...$common, ...$hiddenFilters,
    ]))->assertOk()->assertViewHas('events', fn ($page) => $page->pluck('id')->all() === [$archived->id]);
    parse_str(parse_url($response->viewData('events')->url(2), PHP_URL_QUERY), $pagination);
    expect($pagination)->toBe(['archive' => 'archived', ...$common, 'page' => '2']);
    $xpath = cleanupNavigationXPath($response->getContent());
    expect($xpath->query('//form[@data-event-list-filters]//*[@name="status" or @name="review_status"]')->length)->toBe(0)
        ->and($xpath->query('//a[@href="'.route('admin.events.create').'"]')->length)->toBe(0);
    $links = $xpath->query('//nav[@aria-label="Event sections"]/a');
    foreach ($links as $position => $link) {
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
        expect($query)->toBe([...$common, 'archive' => $position === 0 ? 'active' : 'archived']);
    }
    $this->get($links->item(0)->getAttribute('href'))->assertOk()
        ->assertViewHas('events', fn ($page) => $page->pluck('id')->all() === [$active->id]);
    expect(cleanupDatabaseState(['admin_activity_logs']))->toBe($before)
        ->and(AdminActivityLog::where('action', 'like', 'event.cleanup.%')->count())->toBe(0);
})->with([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER])->with([
    'draft filters' => [['status' => 'draft', 'review_status' => 'draft']],
    'upcoming pending filters' => [['status' => 'upcoming', 'review_status' => 'pending_review']],
    'unknown filters' => [['status' => 'unknown', 'review_status' => 'unknown']],
    'array filters' => [['status' => ['draft'], 'review_status' => ['draft']]],
]);

test('the regular Events section retains status and approval filtering and existing creation permission', function (string $role) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $viewer = $role === User::ROLE_EVENT_MANAGER ? $manager : User::factory()->create(['role' => $role]);
    $draft = cleanupEventFixture($manager);
    cleanupEventFixture($manager, false, ['review_status' => 'changes_requested']);
    cleanupEventFixture($manager, true);
    cleanupEventFixture($manager, true, ['archived_at' => now()]);
    $response = $this->actingAs($viewer)->get(route('admin.events.index', ['status' => 'draft', 'review_status' => 'draft']))
        ->assertOk()->assertViewHas('events', fn ($page) => $page->pluck('id')->all() === [$draft->id]);
    $xpath = cleanupNavigationXPath($response->getContent());
    expect($xpath->query('//select[@name="status"]/option[@selected]')->item(0)->getAttribute('value'))->toBe('draft')
        ->and($xpath->query('//select[@name="review_status"]/option[@selected]')->item(0)->getAttribute('value'))->toBe('draft')
        ->and($xpath->query('//a[@href="'.route('admin.events.create').'"]')->length)->toBe($role === User::ROLE_EVENT_MANAGER ? 1 : 0);
    $archivedLink = $xpath->query('//nav[@aria-label="Event sections"]/a')->item(1)->getAttribute('href');
    parse_str(parse_url($archivedLink, PHP_URL_QUERY), $query);
    expect($query)->toBe(['archive' => 'archived']);
})->with([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER]);
