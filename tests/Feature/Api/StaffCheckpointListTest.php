<?php

use App\Models\Category;
use App\Models\CategoryCourseCheckpoint;
use App\Models\Event;
use App\Models\User;
use App\Services\EventReviewWorkflow;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

function checkpointListCourse(): array
{
    return ['version' => 1,
        'path' => [['lat' => 14.46, 'lng' => 120.96], ['lat' => 14.48, 'lng' => 120.98]],
        'checkpoints' => [['lat' => 14.465, 'lng' => 120.965, 'label' => 'Water station'],
            ['lat' => 14.475, 'lng' => 120.975, 'label' => 'Turnaround']]];
}

function checkpointListFixture(?User $manager = null, int $days = 30, bool $approve = true, bool $sync = true): array
{
    $manager ??= User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $date = today()->addDays($days)->toDateString();
    $event = Event::create(['title' => 'Checkpoint list '.uniqid(), 'slug' => 'checkpoint-list-'.uniqid(),
        'description' => 'Synthetic checkpoint listing.', 'venue' => 'Test venue',
        'event_date' => $date, 'event_end_date' => $date, 'start_time' => '06:00', 'end_time' => '12:00',
        'registration_deadline' => today()->addWeek(), 'interest_type' => 'Marathon',
        'type_details' => ['cutoff_time' => '6 hours'], 'banner_image' => 'events/synthetic.png',
        'manager_id' => $manager->id]);
    $create = fn () => $event->categories()->create(['name' => '10K Open', 'distance_km' => 10,
        'status' => 'open', 'slot_limit' => 100, 'price_cents' => 0, 'course_map' => checkpointListCourse()]);
    $category = $sync ? $create() : Category::withoutEvents($create);
    if ($approve) {
        $event = approveEventSetupForTest($event);
    }

    return [$manager, $event, $category];
}

function checkpointListHeaders(User $user, bool $expired = false): array
{
    $token = 'checkpoint-list-'.bin2hex(random_bytes(24));
    $user->forceFill(['api_token' => hash('sha256', $token),
        'api_token_expires_at' => $expired ? now()->subMinute() : now()->addHour()])->save();

    return ['Authorization' => 'Bearer '.$token];
}

function checkpointListUrl(array $query = []): string
{
    return '/api/staff/checkpoint-scanner/context'.($query === [] ? '' : '?'.http_build_query($query));
}

test('verified assigned managers can read ordered approved checkpoint identities with a minimal contract', function () {
    [$manager, $event, $category] = checkpointListFixture();
    $ids = $category->activeCourseCheckpoints()->pluck('id')->all();
    $response = $this->getJson(checkpointListUrl(), checkpointListHeaders($manager))->assertOk()
        ->assertJsonPath('meta.read_only', true)->assertJsonPath('meta.scan_recording_available', false)
        ->assertJsonPath('meta.total', 1)->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $event->id)
        ->assertJsonPath('data.0.categories.0.id', $category->id)
        ->assertJsonPath('data.0.categories.0.checkpoint_list_status', 'ready')
        ->assertJsonPath('data.0.categories.0.checkpoints', [
            ['id' => $ids[0], 'position' => 1, 'label' => 'Water station'],
            ['id' => $ids[1], 'position' => 2, 'label' => 'Turnaround'],
        ]);
    expect(array_keys($response->json('data.0')))->toBe(['id', 'title', 'event_date', 'categories'])
        ->and(array_keys($response->json('data.0.categories.0')))->toBe(['id', 'name', 'checkpoint_list_status', 'checkpoints'])
        ->and($response->headers->get('Cache-Control'))->toContain('private', 'no-store')
        ->and($response->headers->get('Vary'))->toBe('Authorization');
});

test('admins see approved events from different managers while each manager sees only their assignment', function () {
    [$one, $first] = checkpointListFixture();
    [$two, $second] = checkpointListFixture();
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->getJson(checkpointListUrl(), checkpointListHeaders($admin))->assertOk()->assertJsonPath('meta.total', 2);
    foreach ([[$one, $first], [$two, $second]] as [$manager, $event]) {
        $this->getJson(checkpointListUrl(), checkpointListHeaders($manager))->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $event->id);
    }
});

test('event and category filters stay inside assignment scope and cannot cross event ownership', function () {
    [$manager, $first, $category] = checkpointListFixture();
    [, $other, $otherCategory] = checkpointListFixture();
    $headers = checkpointListHeaders($manager);
    $this->getJson(checkpointListUrl(['event_id' => $first->id, 'category_id' => $category->id]), $headers)
        ->assertOk()->assertJsonPath('data.0.categories.0.id', $category->id);
    foreach ([['event_id' => $other->id], ['category_id' => $otherCategory->id],
        ['event_id' => $first->id, 'category_id' => $otherCategory->id], ['event_id' => 999999999]] as $filters) {
        $this->getJson(checkpointListUrl($filters), $headers)->assertOk()->assertExactJson([
            'data' => [], 'meta' => ['read_only' => true, 'scan_recording_available' => false,
                'current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0],
        ]);
    }
});

test('missing invalid and expired bearer tokens cannot read checkpoint lists', function () {
    [$manager] = checkpointListFixture();
    $this->getJson(checkpointListUrl())->assertUnauthorized();
    $this->getJson(checkpointListUrl(), ['Authorization' => 'Bearer unknown-test-token'])->assertUnauthorized();
    $this->getJson(checkpointListUrl(), checkpointListHeaders($manager, expired: true))->assertUnauthorized();
});

test('each selected category returns only its own identities even when route locations are identical', function () {
    [$manager, $event, $first] = checkpointListFixture(approve: false);
    $second = $event->categories()->create(['name' => '5K Open', 'distance_km' => 5, 'status' => 'open',
        'slot_limit' => 100, 'price_cents' => 0, 'course_map' => checkpointListCourse()]);
    approveEventSetupForTest($event);
    $headers = checkpointListHeaders($manager);
    foreach ([$first, $second] as $selected) {
        $response = $this->getJson(checkpointListUrl(['event_id' => $event->id, 'category_id' => $selected->id]), $headers)
            ->assertOk()->assertJsonCount(1, 'data.0.categories')->assertJsonPath('data.0.categories.0.id', $selected->id);
        expect(array_column($response->json('data.0.categories.0.checkpoints'), 'id'))
            ->toBe($selected->activeCourseCheckpoints()->pluck('id')->all());
    }
    expect($first->activeCourseCheckpoints()->pluck('id')->intersect($second->activeCourseCheckpoints()->pluck('id')))
        ->toHaveCount(0);
});

test('non staff roles cannot read checkpoint lists even with a valid token', function (string $role) {
    checkpointListFixture();
    $user = User::factory()->create(['role' => $role]);
    $this->getJson(checkpointListUrl(), checkpointListHeaders($user))
        ->assertForbidden()->assertJsonPath('code', 'checkpoint_list_forbidden')->assertJsonMissingPath('data');
})->with(['runner', 'user', 'content_moderator', 'executive']);

test('legacy admin role retains only its existing event manager assignment scope', function () {
    [$manager, $event] = checkpointListFixture();
    checkpointListFixture();
    $manager->update(['role' => User::ROLE_LEGACY_ADMIN]);
    $this->getJson(checkpointListUrl(), checkpointListHeaders($manager))->assertOk()
        ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $event->id);
});

test('unverified banned and suspended staff cannot read checkpoint lists', function () {
    [$manager] = checkpointListFixture();
    $headers = checkpointListHeaders($manager);
    $manager->forceFill(['email_verified_at' => null])->save();
    $this->getJson(checkpointListUrl(), $headers)->assertForbidden()->assertJsonPath('code', 'staff_email_unverified');
    $manager->forceFill(['email_verified_at' => now(), 'suspended_at' => now()])->save();
    $this->getJson(checkpointListUrl(), $headers)->assertStatus(423);
    $manager->forceFill(['suspended_at' => null, 'banned_at' => now()])->save();
    $this->getJson(checkpointListUrl(), $headers)->assertForbidden();
});

test('reassigned managers immediately lose list access on subsequent requests', function () {
    [$manager, $event] = checkpointListFixture();
    $headers = checkpointListHeaders($manager);
    $newManager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event->update(['manager_id' => $newManager->id]);
    $this->getJson(checkpointListUrl(['event_id' => $event->id]), $headers)->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(checkpointListUrl(['event_id' => $event->id]), checkpointListHeaders($newManager))
        ->assertOk()->assertJsonPath('data.0.id', $event->id);
});

test('unapproved and legacy setup never enters the operational checkpoint list', function ($reviewStatus) {
    [$manager, $event] = checkpointListFixture(approve: false);
    DB::table('events')->where('id', $event->id)->update(['review_status' => $reviewStatus, 'status' => 'upcoming']);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    foreach ([$manager, $admin] as $staff) {
        $this->getJson(checkpointListUrl(), checkpointListHeaders($staff))->assertOk()
            ->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
    }
})->with([null, 'draft', 'pending_review', 'changes_requested', 'discarded']);

test('closed categories are readable but draft categories and draft event lifecycles are excluded', function () {
    [$manager, $event, $category] = checkpointListFixture();
    $category->update(['status' => 'closed']);
    $draft = $event->categories()->create(['name' => 'Private draft category', 'distance_km' => 5,
        'status' => 'draft', 'course_map' => checkpointListCourse()]);
    $headers = checkpointListHeaders($manager);
    $this->getJson(checkpointListUrl(), $headers)->assertOk()->assertJsonCount(1, 'data.0.categories')
        ->assertJsonPath('data.0.categories.0.id', $category->id);
    $this->getJson(checkpointListUrl(['category_id' => $draft->id]), $headers)->assertOk()->assertJsonCount(0, 'data');
    DB::table('events')->where('id', $event->id)->update(['status' => 'draft']);
    $this->getJson(checkpointListUrl(), $headers)->assertOk()->assertJsonCount(0, 'data');
});

test('approved ongoing and completed events remain readable without any participant registration', function (string $status) {
    [$manager, $event] = checkpointListFixture();
    DB::table('events')->where('id', $event->id)->update(['status' => $status]);
    $this->getJson(checkpointListUrl(), checkpointListHeaders($manager))->assertOk()
        ->assertJsonPath('data.0.id', $event->id)->assertJsonPath('data.0.categories.0.checkpoint_list_status', 'ready');
    expect($event->registrations()->count())->toBe(0);
})->with(['ongoing', 'completed']);

test('private and pending revisions never replace the approved list and approval publishes matching live identities', function () {
    [$manager, $event, $category] = checkpointListFixture();
    $headers = checkpointListHeaders($manager);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $adminHeaders = checkpointListHeaders($admin);
    $ids = $category->activeCourseCheckpoints()->pluck('id')->all();
    $workflow = app(EventReviewWorkflow::class);
    $draft = $workflow->startRevision($event, $manager, $event->review_version);
    $draftCategory = $draft->categories()->sole();
    $privateIds = $draftCategory->activeCourseCheckpoints()->pluck('id')->all();
    $map = checkpointListCourse();
    $map['checkpoints'] = array_reverse($map['checkpoints']);
    $map['checkpoints'][0]['label'] = 'Reviewed turnaround';
    $draftCategory->update(['course_map' => $map]);
    foreach (['draft', 'pending_review'] as $state) {
        if ($state === 'pending_review') {
            $draft = $workflow->transition($draft, $manager, 'submit', $draft->review_version);
        }
        foreach ([$headers, $adminHeaders] as $staffHeaders) {
            $this->getJson(checkpointListUrl(), $staffHeaders)->assertOk()->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.id', $event->id)
                ->assertJsonPath('data.0.categories.0.checkpoints.0.id', $ids[0])
                ->assertJsonPath('data.0.categories.0.checkpoints.0.label', 'Water station');
            $this->getJson(checkpointListUrl(['event_id' => $draft->id]), $staffHeaders)->assertOk()->assertJsonCount(0, 'data');
            $this->getJson(checkpointListUrl(['category_id' => $draftCategory->id]), $staffHeaders)->assertOk()->assertJsonCount(0, 'data');
        }
    }
    $workflow->transition($draft, $admin, 'approve', $draft->review_version);
    $response = $this->getJson(checkpointListUrl(), $headers)->assertOk()->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.categories.0.id', $category->id)
        ->assertJsonPath('data.0.categories.0.checkpoints.0.id', $ids[1])
        ->assertJsonPath('data.0.categories.0.checkpoints.0.label', 'Reviewed turnaround');
    expect(array_intersect(array_column($response->json('data.0.categories.0.checkpoints'), 'id'), $privateIds))->toBe([]);
    $this->getJson(checkpointListUrl(['event_id' => $draft->id]), $adminHeaders)->assertOk()->assertJsonCount(0, 'data');
});

test('retired checkpoints are absent and no old event level checkpoints are guessed into a category', function () {
    [$manager, $event, $category] = checkpointListFixture();
    $retired = $category->activeCourseCheckpoints()->first()->id;
    $map = checkpointListCourse();
    array_shift($map['checkpoints']);
    $category->update(['course_map' => $map]);
    $event->checkpoints()->create(['name' => 'Old event checkpoint', 'order' => 1, 'type' => 'checkpoint',
        'location' => 'Synthetic location', 'latitude' => 14.465, 'longitude' => 120.965]);
    $response = $this->getJson(checkpointListUrl(), checkpointListHeaders($manager))->assertOk()
        ->assertJsonCount(1, 'data.0.categories.0.checkpoints')
        ->assertJsonPath('data.0.categories.0.checkpoints.0.label', 'Turnaround');
    expect($response->json('data.0.categories.0.checkpoints.0.id'))->not->toBe($retired)
        ->and(CategoryCourseCheckpoint::findOrFail($retired)->retired_at)->not->toBeNull();
});

test('image only and zero checkpoint routes return explicit empty lists without inventing identities', function () {
    [$manager, , $category] = checkpointListFixture();
    $headers = checkpointListHeaders($manager);
    $category->update(['course_map' => null, 'checkpoint_map_image' => 'categories/maps/test.png']);
    $this->getJson(checkpointListUrl(), $headers)->assertOk()
        ->assertJsonPath('data.0.categories.0.checkpoint_list_status', 'no_drawn_route')
        ->assertJsonPath('data.0.categories.0.checkpoints', []);
    $map = checkpointListCourse();
    $map['checkpoints'] = [];
    $category->update(['course_map' => $map]);
    $this->getJson(checkpointListUrl(), $headers)->assertOk()
        ->assertJsonPath('data.0.categories.0.checkpoint_list_status', 'no_checkpoints')
        ->assertJsonPath('data.0.categories.0.checkpoints', []);
});

test('missing derived identities are unavailable and GET does not run a backfill', function () {
    [$manager, , $category] = checkpointListFixture(sync: false);
    expect($category->courseCheckpoints()->count())->toBe(0);
    $this->getJson(checkpointListUrl(), checkpointListHeaders($manager))->assertOk()
        ->assertJsonPath('data.0.categories.0.checkpoint_list_status', 'checkpoint_list_needs_sync')
        ->assertJsonPath('data.0.categories.0.checkpoints', []);
    expect($category->courseCheckpoints()->count())->toBe(0)
        ->and($category->fresh()->course_map)->toBe(checkpointListCourse());
});

test('stale labels positions coordinates and route removals never expose mismatched checkpoint IDs', function (string $change) {
    [$manager, , $category] = checkpointListFixture();
    $map = checkpointListCourse();
    if ($change === 'label') {
        $map['checkpoints'][0]['label'] = 'Raw unsynchronized label';
    } elseif ($change === 'order') {
        $map['checkpoints'] = array_reverse($map['checkpoints']);
    } elseif ($change === 'coordinates') {
        $map['checkpoints'][0]['lat'] = 14.466;
    }
    DB::table('categories')->where('id', $category->id)->update([
        'course_map' => $change === 'clear' ? null : json_encode($map),
    ]);
    $before = $category->courseCheckpoints()->get()->toArray();
    $this->getJson(checkpointListUrl(), checkpointListHeaders($manager))->assertOk()
        ->assertJsonPath('data.0.categories.0.checkpoint_list_status', 'checkpoint_list_needs_sync')
        ->assertJsonPath('data.0.categories.0.checkpoints', []);
    expect($category->courseCheckpoints()->get()->toArray())->toBe($before);
})->with(['label', 'order', 'coordinates', 'clear']);

test('invalid saved route data returns a safe unavailable state without leaking or rewriting it', function () {
    [$manager, , $category] = checkpointListFixture();
    DB::table('categories')->where('id', $category->id)->update(['course_map' => '{private-invalid-route']);
    $this->getJson(checkpointListUrl(), checkpointListHeaders($manager))->assertOk()
        ->assertJsonPath('data.0.categories.0.checkpoint_list_status', 'invalid_drawn_route')
        ->assertJsonPath('data.0.categories.0.checkpoints', [])->assertDontSee('private-invalid-route');
    expect($category->fresh()->getRawOriginal('course_map'))->toBe('{private-invalid-route');
});

test('a derived position gap is unavailable rather than silently renumbering its permanent records', function () {
    [$manager, , $category] = checkpointListFixture();
    $first = $category->activeCourseCheckpoints()->first();
    DB::table('category_course_checkpoints')->where('id', $first->id)->update(['position' => 99]);
    $this->getJson(checkpointListUrl(), checkpointListHeaders($manager))->assertOk()
        ->assertJsonPath('data.0.categories.0.checkpoint_list_status', 'checkpoint_list_needs_sync')
        ->assertJsonPath('data.0.categories.0.checkpoints', []);
    expect($first->fresh()->position)->toBe(99);
});

test('missing checkpoint schema fails closed for authorized staff without trying to create it', function () {
    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getConfig('database'))->toBe(':memory:');
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $headers = checkpointListHeaders($manager);
    Schema::drop('category_course_checkpoints');
    try {
        $this->getJson(checkpointListUrl(), $headers)->assertStatus(503)
            ->assertJsonPath('code', 'checkpoint_setup_unavailable')->assertJsonMissingPath('data');
        expect(Schema::hasTable('category_course_checkpoints'))->toBeFalse();
    } finally {
        (require database_path('migrations/2026_11_09_000001_create_category_course_checkpoints_table.php'))->up();
    }
});

test('pagination is bounded deterministic and counts only accessible approved events', function () {
    [$manager] = checkpointListFixture(days: 30);
    [, $newest] = checkpointListFixture($manager, days: 31);
    checkpointListFixture(days: 32);
    checkpointListFixture($manager, days: 33, approve: false);
    $headers = checkpointListHeaders($manager);
    $first = $this->getJson(checkpointListUrl(['per_page' => 1]), $headers)->assertOk()
        ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2)->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('data.0.id', $newest->id);
    $second = $this->getJson(checkpointListUrl(['per_page' => 1, 'page' => 2]), $headers)->assertOk()
        ->assertJsonPath('meta.current_page', 2)->assertJsonCount(1, 'data');
    expect($second->json('data.0.id'))->not->toBe($first->json('data.0.id'));
});

test('invalid list selectors and pagination do not widen access', function (array $filters, string $field) {
    [$manager] = checkpointListFixture();
    $this->getJson(checkpointListUrl($filters), checkpointListHeaders($manager))->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    [['event_id' => '1 OR 1=1'], 'event_id'], [['event_id' => 0], 'event_id'],
    [['category_id' => -1], 'category_id'], [['category_id' => [1, 2]], 'category_id'],
    [['page' => 0], 'page'], [['per_page' => 51], 'per_page'], [['per_page' => 0], 'per_page'],
]);

test('checkpoint list requests write no application data and never request Google maps', function () {
    [$manager, $event, $category] = checkpointListFixture();
    $headers = checkpointListHeaders($manager);
    $before = [$event->fresh()->getAttributes(), $category->fresh()->getAttributes(),
        $category->courseCheckpoints()->get()->toArray()];
    Http::preventStrayRequests();
    $writes = [];
    DB::listen(function (QueryExecuted $query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    $this->getJson(checkpointListUrl(), $headers)->assertOk();
    $this->getJson(checkpointListUrl(['category_id' => $category->id]), $headers)->assertOk();
    $this->postJson(checkpointListUrl(), [], $headers)->assertStatus(405);
    expect($writes)->toBe([])
        ->and([$event->fresh()->getAttributes(), $category->fresh()->getAttributes(),
            $category->courseCheckpoints()->get()->toArray()])->toBe($before);
    Http::assertNothingSent();
});
