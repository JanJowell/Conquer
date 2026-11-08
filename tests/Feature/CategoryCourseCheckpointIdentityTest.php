<?php

use App\Http\Resources\Api\CategoryResource;
use App\Models\Category;
use App\Models\CategoryCourseCheckpoint;
use App\Models\Event;
use App\Models\User;
use App\Services\CategoryCourseCheckpointSync;
use App\Services\EventReviewWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function identityCourse(array $points = []): array
{
    return ['version' => 1,
        'path' => [['lat' => 14.46, 'lng' => 120.96], ['lat' => 14.48, 'lng' => 120.98]],
        'checkpoints' => $points ?: [
            ['lat' => 14.465, 'lng' => 120.965, 'label' => 'Water'],
            ['lat' => 14.475, 'lng' => 120.975, 'label' => 'Turnaround'],
        ]];
}

function identityEvent(): Event
{
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);

    return Event::create(['title' => 'Checkpoint identities', 'slug' => 'identities-'.uniqid(),
        'description' => 'Synthetic checkpoint setup.', 'venue' => 'Synthetic park',
        'event_date' => today()->addMonth(), 'event_end_date' => today()->addMonth(),
        'registration_deadline' => today()->addWeek(), 'start_time' => '06:00', 'end_time' => '12:00',
        'interest_type' => 'Marathon', 'type_details' => ['cutoff_time' => '6 hours'],
        'banner_image' => 'events/synthetic.png', 'manager_id' => $manager->id]);
}

function identityCategory(?Event $event = null, array $overrides = []): Category
{
    return ($event ?? identityEvent())->categories()->create([
        'name' => '10K Open', 'distance_km' => 10, 'status' => 'open', 'price_cents' => 0,
        'slot_limit' => 100, 'course_map' => identityCourse(), ...$overrides,
    ]);
}

test('drawn points become separate category owned records without changing either map', function () {
    $first = identityCategory();
    $second = identityCategory($first->event);
    $one = $first->activeCourseCheckpoints()->get();
    $two = $second->activeCourseCheckpoints()->get();
    expect($one->pluck('position')->all())->toBe([1, 2])
        ->and($one->pluck('label')->all())->toBe(['Water', 'Turnaround'])
        ->and($one->pluck('id')->intersect($two->pluck('id')))->toHaveCount(0)
        ->and($one->first()->category->id)->toBe($first->id)
        ->and($first->fresh()->course_map)->toEqual(identityCourse())
        ->and($second->fresh()->course_map)->toEqual(identityCourse())
        ->and($first->event->checkpoints()->count())->toBe(0);
});

test('renaming and reordering unchanged locations preserve their IDs', function () {
    $category = identityCategory();
    $ids = $category->activeCourseCheckpoints()->pluck('id')->all();
    $map = identityCourse();
    $map['checkpoints'] = array_reverse($map['checkpoints']);
    $map['checkpoints'][1]['label'] = 'Water station renamed';
    $category->update(['course_map' => $map]);
    expect($category->activeCourseCheckpoints()->pluck('id')->all())->toBe(array_reverse($ids))
        ->and($category->activeCourseCheckpoints()->pluck('label')->all())->toBe(['Turnaround', 'Water station renamed'])
        ->and($category->courseCheckpoints()->count())->toBe(2);
});

test('repeat synchronization is a true no-op including checkpoint timestamps', function () {
    $category = identityCategory();
    $before = $category->courseCheckpoints()->get()->toArray();
    $this->travel(1)->hours();
    $result = app(CategoryCourseCheckpointSync::class)->sync($category);
    expect($result)->toBe(['ready' => true, 'valid' => true, 'created' => 0, 'updated' => 0, 'retired' => 0])
        ->and($category->courseCheckpoints()->get()->toArray())->toBe($before);
});

test('moving a point retires its old identity rather than relabelling a different place', function () {
    $category = identityCategory();
    $old = $category->activeCourseCheckpoints()->first();
    $other = $category->activeCourseCheckpoints()->get()->last()->id;
    $map = identityCourse();
    $map['checkpoints'][0]['lat'] = 14.466;
    $category->update(['course_map' => $map]);
    expect($old->fresh()->retired_at)->not->toBeNull()
        ->and($old->fresh()->position)->toBeNull()
        ->and($old->fresh()->latitude)->toBe('14.465000')
        ->and($category->activeCourseCheckpoints()->first()->id)->not->toBe($old->id)
        ->and($category->activeCourseCheckpoints()->get()->last()->id)->toBe($other)
        ->and($category->courseCheckpoints()->count())->toBe(3);
});

test('removing then readding a point never resurrects its retired ID', function () {
    $category = identityCategory();
    $old = $category->activeCourseCheckpoints()->first()->id;
    $map = identityCourse();
    array_shift($map['checkpoints']);
    $category->update(['course_map' => $map]);
    $category->update(['course_map' => identityCourse()]);
    expect(CategoryCourseCheckpoint::findOrFail($old)->retired_at)->not->toBeNull()
        ->and($category->activeCourseCheckpoints()->first()->id)->not->toBe($old)
        ->and($category->activeCourseCheckpoints()->count())->toBe(2);
});

test('clearing the route retires derived points but keeps the uploaded image', function () {
    $category = identityCategory(overrides: ['checkpoint_map_image' => 'categories/maps/keep.png']);
    $ids = $category->activeCourseCheckpoints()->pluck('id')->all();
    $category->update(['course_map' => null]);
    expect($category->activeCourseCheckpoints()->count())->toBe(0)
        ->and($category->courseCheckpoints()->pluck('id')->all())->toBe($ids)
        ->and($category->courseCheckpoints()->whereNotNull('retired_at')->count())->toBe(2)
        ->and($category->fresh()->checkpoint_map_image)->toBe('categories/maps/keep.png');
});

test('image only categories and routes with no intermediate points stay valid and empty', function () {
    $imageOnly = identityCategory(overrides: ['course_map' => null, 'checkpoint_map_image' => 'categories/maps/image.png']);
    $map = identityCourse();
    $map['checkpoints'] = [];
    $drawn = identityCategory(overrides: ['course_map' => $map]);
    expect($imageOnly->courseCheckpoints()->count())->toBe(0)
        ->and($drawn->courseCheckpoints()->count())->toBe(0)
        ->and($imageOnly->fresh()->checkpoint_map_image)->toBe('categories/maps/image.png')
        ->and($drawn->fresh()->course_map)->toBe($map);
});

test('route geometry changes and omitted map updates do not replace unchanged checkpoint IDs', function () {
    $category = identityCategory();
    $ids = $category->activeCourseCheckpoints()->pluck('id')->all();
    $map = identityCourse();
    $map['path'][1]['lat'] = 14.49;
    $category->update(['course_map' => $map]);
    $category->update(['description' => 'Other setup changed']);
    expect($category->activeCourseCheckpoints()->pluck('id')->all())->toBe($ids)
        ->and($category->fresh()->course_map)->toBe($map);
});

test('repeated locations keep exact labelled identities while ambiguous replacements get new IDs', function () {
    $points = [['lat' => 14.465, 'lng' => 120.965, 'label' => 'Outbound'],
        ['lat' => 14.465, 'lng' => 120.965, 'label' => 'Inbound']];
    $category = identityCategory(overrides: ['course_map' => identityCourse($points)]);
    $ids = $category->activeCourseCheckpoints()->pluck('id')->all();
    $category->update(['course_map' => identityCourse(array_reverse($points))]);
    expect($category->activeCourseCheckpoints()->pluck('id')->all())->toBe(array_reverse($ids));
    $points[0]['label'] = 'New pass one';
    $points[1]['label'] = 'New pass two';
    $category->update(['course_map' => identityCourse($points)]);
    expect($category->activeCourseCheckpoints()->pluck('id')->intersect($ids))->toHaveCount(0)
        ->and($category->courseCheckpoints()->whereNotNull('retired_at')->count())->toBe(2);
});

test('indistinguishable repeated points keep occurrence IDs only while their count is unchanged', function () {
    $point = ['lat' => 14.465, 'lng' => 120.965, 'label' => 'Same point'];
    $category = identityCategory(overrides: ['course_map' => identityCourse([$point, $point])]);
    $ids = $category->activeCourseCheckpoints()->pluck('id')->all();
    app(CategoryCourseCheckpointSync::class)->sync($category);
    expect($category->activeCourseCheckpoints()->pluck('id')->all())->toBe($ids);
    $category->update(['course_map' => identityCourse([$point])]);
    expect($category->activeCourseCheckpoints()->first()->id)->not->toBeIn($ids)
        ->and($category->courseCheckpoints()->whereNotNull('retired_at')->count())->toBe(2);
});

test('preview never materializes old routes and explicit backfill is scoped and idempotent', function () {
    $first = Category::withoutEvents(fn () => identityCategory());
    $second = Category::withoutEvents(fn () => identityCategory($first->event));
    $hash = app(EventReviewWorkflow::class)->setupHash($first->event);
    $this->artisan('course-checkpoints:sync', ['--category' => [$first->id]])
        ->expectsOutputToContain('Preview only; no writes: categories=1, created=2')->assertSuccessful();
    expect(CategoryCourseCheckpoint::count())->toBe(0);
    $this->artisan('course-checkpoints:sync', ['--category' => [$first->id], '--write' => true])->assertSuccessful();
    $ids = $first->activeCourseCheckpoints()->pluck('id')->all();
    $this->artisan('course-checkpoints:sync', ['--category' => [$first->id], '--write' => true])
        ->expectsOutputToContain('created=0')->assertSuccessful();
    expect($ids)->toHaveCount(2)
        ->and($first->activeCourseCheckpoints()->pluck('id')->all())->toBe($ids)
        ->and($second->courseCheckpoints()->count())->toBe(0)
        ->and(app(EventReviewWorkflow::class)->setupHash($first->event))->toBe($hash);
});

test('invalid legacy JSON is skipped without retiring known good records', function () {
    $category = identityCategory();
    $before = $category->courseCheckpoints()->get()->toArray();
    DB::table('categories')->where('id', $category->id)->update(['course_map' => '{broken']);
    $this->artisan('course-checkpoints:sync', ['--category' => [$category->id], '--write' => true])
        ->expectsOutputToContain('invalid=1')->assertFailed();
    expect($category->courseCheckpoints()->get()->toArray())->toBe($before)
        ->and($category->fresh()->getRawOriginal('course_map'))->toBe('{broken');
});

test('invalid backfill selectors cannot broaden writes', function (string $selector) {
    Category::withoutEvents(fn () => identityCategory());
    $this->artisan('course-checkpoints:sync', ['--category' => [$selector], '--write' => true])->assertFailed();
    expect(CategoryCourseCheckpoint::count())->toBe(0);
})->with(['0', '-1', 'all', '1 OR 1=1']);

test('backfill of a cleared route retires active records without changing its category', function () {
    $category = identityCategory();
    DB::table('categories')->where('id', $category->id)->update(['course_map' => null]);
    $this->artisan('course-checkpoints:sync', ['--write' => true])->expectsOutputToContain('retired=2')->assertSuccessful();
    expect($category->activeCourseCheckpoints()->count())->toBe(0)
        ->and($category->courseCheckpoints()->count())->toBe(2)
        ->and($category->fresh()->course_map)->toBeNull();
});

test('category and identity changes roll back together in the existing write transaction', function () {
    $category = identityCategory();
    $before = $category->courseCheckpoints()->get()->toArray();
    expect(function () use ($category) {
        DB::transaction(function () use ($category) {
            $category->update(['course_map' => null]);
            throw new RuntimeException('Synthetic failure after category write');
        });
    })->toThrow(RuntimeException::class);
    expect($category->fresh()->course_map)->toEqual(identityCourse())
        ->and($category->courseCheckpoints()->get()->toArray())->toBe($before);
});

test('public course map responses retain the original version one contract without internal IDs', function () {
    $category = identityCategory();
    $payload = (new CategoryResource($category))->resolve(Request::create('/api/events'));
    expect($payload['course_map']['checkpoints'])->toBe([
        ['lat' => 14.465, 'lng' => 120.965, 'label' => 'Water', 'number' => 1],
        ['lat' => 14.475, 'lng' => 120.975, 'label' => 'Turnaround', 'number' => 2],
    ])->and(array_key_exists('course_checkpoints', $payload))->toBeFalse();
});

test('working revisions have private identities and approval preserves matching live IDs', function () {
    $category = identityCategory();
    $event = approveEventSetupForTest($category->event);
    $ids = $category->activeCourseCheckpoints()->pluck('id')->all();
    $workflow = app(EventReviewWorkflow::class);
    $draft = $workflow->startRevision($event, $event->manager, $event->review_version);
    $copy = $draft->categories()->sole();
    expect($copy->activeCourseCheckpoints()->pluck('id')->intersect($ids))->toHaveCount(0);
    $map = identityCourse();
    $map['checkpoints'] = array_reverse($map['checkpoints']);
    $map['checkpoints'][0]['label'] = 'Reviewed turnaround';
    $copy->update(['course_map' => $map]);
    expect($category->fresh()->course_map)->toEqual(identityCourse())
        ->and($category->activeCourseCheckpoints()->pluck('id')->all())->toBe($ids);
    $draft = $workflow->transition($draft, $event->manager, 'submit', $draft->review_version);
    $reviewer = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $workflow->transition($draft, $reviewer, 'approve', $draft->review_version);
    expect($category->fresh()->course_map)->toBe($map)
        ->and($category->activeCourseCheckpoints()->pluck('id')->all())->toBe(array_reverse($ids))
        ->and($category->courseCheckpoints()->count())->toBe(2);
});

test('discarding a revision cannot retire published checkpoint records', function () {
    $category = identityCategory();
    $event = approveEventSetupForTest($category->event);
    $before = $category->courseCheckpoints()->get()->toArray();
    $workflow = app(EventReviewWorkflow::class);
    $draft = $workflow->startRevision($event, $event->manager, $event->review_version);
    $draft->categories()->sole()->update(['course_map' => null, 'checkpoint_map_image' => 'synthetic/image.png']);
    $workflow->transition($draft, $event->manager, 'discard', $draft->review_version);
    expect($category->courseCheckpoints()->get()->toArray())->toBe($before)
        ->and($category->fresh()->course_map)->toEqual(identityCourse());
});

test('backfill does not invalidate a pending submission or require a fabricated approval', function () {
    $category = Category::withoutEvents(fn () => identityCategory());
    $event = $category->event;
    $workflow = app(EventReviewWorkflow::class);
    $submitted = $workflow->transition($event, $event->manager, 'submit', $event->review_version);
    $hash = $submitted->submitted_setup_hash;
    $this->artisan('course-checkpoints:sync', ['--category' => [$category->id], '--write' => true])->assertSuccessful();
    expect($submitted->fresh()->submitted_setup_hash)->toBe($hash)
        ->and($workflow->setupHash($submitted))->toBe($hash);
    $reviewer = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    expect($workflow->transition($submitted, $reviewer, 'approve', $submitted->review_version)->review_status)->toBe('approved');
});

test('missing migration leaves existing saves usable and schema creation does not backfill real setup', function () {
    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getConfig('database'))->toBe(':memory:');
    Schema::drop('category_course_checkpoints');
    $migration = require database_path('migrations/2026_11_09_000001_create_category_course_checkpoints_table.php');
    try {
        $category = identityCategory();
        expect($category->fresh()->course_map)->toEqual(identityCourse());
        $this->artisan('course-checkpoints:sync')->expectsOutputToContain('Apply the category course checkpoint migration')->assertFailed();
    } finally {
        $migration->up();
    }
    expect(CategoryCourseCheckpoint::count())->toBe(0);
});

test('rollback cannot silently delete populated permanent identities', function () {
    $category = identityCategory();
    $migration = require database_path('migrations/2026_11_09_000001_create_category_course_checkpoints_table.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class)
        ->and($category->activeCourseCheckpoints()->count())->toBe(2);
});

test('real category create and edit requests synchronize records without a second form list', function () {
    $event = identityEvent();
    $payload = ['event_id' => $event->id, 'category_type' => 'open', 'distance_option' => '10',
        'scheduled_start_date' => $event->event_date->toDateString(), 'scheduled_start_time' => '07:00',
        'scheduled_end_date' => $event->event_date->toDateString(), 'scheduled_end_time' => '10:00',
        'price_amount' => '0.00', 'price_currency' => 'PHP', 'status' => 'open', 'course_map' => json_encode(identityCourse())];
    $this->actingAs($event->manager)->post(route('admin.categories.store'), $payload)->assertSessionHasNoErrors();
    $category = $event->categories()->sole();
    $ids = $category->activeCourseCheckpoints()->pluck('id')->all();
    expect($ids)->toHaveCount(2);
    $this->get(route('admin.categories.edit', $category))
        ->assertOk()
        ->assertViewHas('category', fn (Category $reopened) => $reopened->course_map === identityCourse())
        ->assertSee(e(json_encode(identityCourse())), false);
    $map = identityCourse();
    $map['checkpoints'][0]['label'] = 'HTTP rename';
    $this->put(route('admin.categories.update', $category), [...$payload, 'course_map' => json_encode($map)])
        ->assertSessionHasNoErrors();
    expect($category->activeCourseCheckpoints()->pluck('id')->all())->toBe($ids)
        ->and($category->activeCourseCheckpoints()->first()->label)->toBe('HTTP rename');
    $this->get(route('admin.categories.edit', $category))
        ->assertOk()
        ->assertViewHas('category', fn (Category $reopened) => $reopened->course_map === $map)
        ->assertSee(e(json_encode($map)), false);
    $map['checkpoints'] = array_reverse($map['checkpoints']);
    $this->put(route('admin.categories.update', $category), [...$payload, 'course_map' => json_encode($map)])
        ->assertSessionHasNoErrors();
    expect($category->activeCourseCheckpoints()->pluck('id')->all())->toBe(array_reverse($ids))
        ->and($category->activeCourseCheckpoints()->pluck('position')->all())->toBe([1, 2])
        ->and($category->courseCheckpoints()->count())->toBe(2);
    $this->get(route('admin.categories.edit', $category))
        ->assertOk()
        ->assertViewHas('category', fn (Category $reopened) => $reopened->course_map === $map)
        ->assertSee(e(json_encode($map)), false);
    $other = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($other)->put(route('admin.categories.update', $category), $payload)->assertForbidden();
    $this->get(route('admin.categories.edit', $category))->assertForbidden();
    expect($category->activeCourseCheckpoints()->pluck('id')->all())->toBe(array_reverse($ids))
        ->and($category->fresh()->course_map)->toBe($map);
});

test('event form saves synchronize each category without crossing checkpoint ownership', function () {
    $event = identityEvent();
    $category = identityCategory($event);
    $ids = $category->activeCourseCheckpoints()->pluck('id')->all();
    $map = identityCourse();
    $map['checkpoints'][0]['label'] = 'Event form edit';
    $row = ['category_type' => 'open', 'distance_option' => '10', 'price_amount' => '0.00',
        'price_currency' => 'PHP', 'status' => 'open', 'slot_limit' => 100,
        'scheduled_start_date' => $event->event_date->toDateString(), 'scheduled_start_time' => '07:00',
        'scheduled_end_date' => $event->event_date->toDateString(), 'scheduled_end_time' => '10:00'];
    $this->actingAs($event->manager)->put(route('admin.events.update', $event), [
        'title' => $event->title, 'description' => $event->description, 'venue' => $event->venue,
        'event_date' => $event->event_date->toDateString(), 'event_end_date' => $event->event_end_date->toDateString(),
        'registration_deadline' => $event->registration_deadline->toDateString(),
        'start_time' => '06:00', 'end_time' => '12:00', 'interest_type' => 'Marathon',
        'manager_id' => $event->manager_id, 'type_details' => ['Marathon' => ['cutoff_time' => '6 hours']], 'intent' => 'draft',
        'categories' => [[...$row, 'id' => $category->id, 'course_map' => json_encode($map)],
            [...$row, 'category_type' => 'female', 'course_map' => json_encode(identityCourse())]],
    ])->assertSessionHasNoErrors();
    $new = $event->categories()->whereKeyNot($category->id)->sole();
    expect($category->activeCourseCheckpoints()->pluck('id')->all())->toBe($ids)
        ->and($new->activeCourseCheckpoints()->count())->toBe(2)
        ->and($new->activeCourseCheckpoints()->pluck('id')->intersect($ids))->toHaveCount(0);
});

test('approved moved and new revision points get live category IDs without reusing retired records', function () {
    $category = identityCategory();
    $event = approveEventSetupForTest($category->event);
    $old = $category->activeCourseCheckpoints()->first()->id;
    $unchanged = $category->activeCourseCheckpoints()->get()->last()->id;
    $workflow = app(EventReviewWorkflow::class);
    $draft = $workflow->startRevision($event, $event->manager, $event->review_version);
    $map = identityCourse();
    $map['checkpoints'][0]['lat'] = 14.466;
    $map['checkpoints'][] = ['lat' => 14.477, 'lng' => 120.977, 'label' => 'New point'];
    $draft->categories()->sole()->update(['course_map' => $map]);
    $privateIds = $draft->categories()->sole()->activeCourseCheckpoints()->pluck('id')->all();
    $draft = $workflow->transition($draft, $event->manager, 'submit', $draft->review_version);
    $reviewer = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $workflow->transition($draft, $reviewer, 'approve', $draft->review_version);
    expect($category->activeCourseCheckpoints()->count())->toBe(3)
        ->and($category->activeCourseCheckpoints()->pluck('id')->intersect($privateIds))->toHaveCount(0)
        ->and($category->activeCourseCheckpoints()->get()[1]->id)->toBe($unchanged)
        ->and(CategoryCourseCheckpoint::findOrFail($old)->retired_at)->not->toBeNull();
});

test('backfill normalizes derived coordinates and names without rewriting legacy route JSON', function () {
    $map = identityCourse();
    $map['checkpoints'][0] = ['lat' => '14.46500001', 'lng' => '120.96500001', 'label' => '  Water  '];
    $category = Category::withoutEvents(fn () => identityCategory(overrides: ['course_map' => $map]));
    $raw = $category->getRawOriginal('course_map');
    app(CategoryCourseCheckpointSync::class)->sync($category);
    expect($category->activeCourseCheckpoints()->first()->latitude)->toBe('14.465000')
        ->and($category->activeCourseCheckpoints()->first()->longitude)->toBe('120.965000')
        ->and($category->activeCourseCheckpoints()->first()->label)->toBe('Water')
        ->and($category->fresh()->getRawOriginal('course_map'))->toBe($raw);
});

test('new categories added through reviewed revisions get their own published identities', function () {
    $category = identityCategory();
    $event = approveEventSetupForTest($category->event);
    $workflow = app(EventReviewWorkflow::class);
    $draft = $workflow->startRevision($event, $event->manager, $event->review_version);
    $new = identityCategory($draft, ['name' => '21K Open', 'distance_km' => 21]);
    $privateIds = $new->activeCourseCheckpoints()->pluck('id')->all();
    $draft = $workflow->transition($draft, $event->manager, 'submit', $draft->review_version);
    $reviewer = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $workflow->transition($draft, $reviewer, 'approve', $draft->review_version);
    $liveNew = $event->categories()->where('name', '21K Open')->sole();
    expect($liveNew->id)->not->toBe($new->id)
        ->and($liveNew->activeCourseCheckpoints()->count())->toBe(2)
        ->and($liveNew->activeCourseCheckpoints()->pluck('id')->intersect($privateIds))->toHaveCount(0);
});
