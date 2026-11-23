<?php

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\AutomaticEventArchiver;
use App\Services\EventCancellation;
use App\Services\EventCleanup;
use App\Services\EventReactivation;
use App\Services\EventReviewWorkflow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    Http::preventStrayRequests();
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-11-20 09:00:00', config('app.timezone')));
    config(['event_archiving.enabled' => true, 'event_archiving.activated_at' => '2026-11-19T07:03:52+08:00']);
});

function unusedCancelledEvent(): array
{
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = Event::create(['title' => 'Synthetic reactivation', 'slug' => 'reactivate-'.Str::uuid(),
        'description' => 'Synthetic future race.', 'venue' => 'Synthetic venue', 'manager_id' => $manager->id,
        'event_date' => '2026-11-21', 'event_end_date' => '2026-11-21', 'registration_deadline' => '2026-11-21',
        'start_time' => '06:00', 'end_time' => '12:00', 'banner_image' => 'events/synthetic.png',
        'interest_type' => 'Marathon', 'type_details' => ['cutoff_time' => '6 hours']]);
    $event->categories()->create(['name' => '3K Open', 'distance_km' => 3, 'slot_limit' => 100,
        'status' => 'open', 'price_cents' => 0, 'checkpoint_map_image' => 'categories/synthetic.png',
        'scheduled_start_date' => '2026-11-21', 'scheduled_end_date' => '2026-11-21',
        'scheduled_start_time' => '06:00', 'scheduled_end_time' => '12:00']);
    $event = approveEventSetupForTest($event);
    app(EventCancellation::class)->cancel($event, $admin, app(EventCleanup::class)->fingerprint($event),
        'Unsafe weather at original venue.', '127.0.0.1');

    return [$event->fresh(), $manager, $admin];
}

function reactivationInput(Event $event): array
{
    return ['review_version' => $event->review_version, 'reactivation_reason' => 'New safe date and venue.', 'confirmation' => 1];
}

function reactivationDraft(Event $event, User $manager): Event
{
    return app(EventReviewWorkflow::class)->startRevision($event, $manager, $event->review_version, 'New safe date and venue.');
}

function reactivationReview(Event $event, string $action): string
{
    return route('admin.events.review', [$event, $action]);
}

function reactivationCleanup(Event $event, User $admin, string $action): void
{
    app(EventCleanup::class)->perform($event, $admin, $action, app(EventCleanup::class)->fingerprint($event->fresh()));
}

test('only the assigned Event Manager can explicitly request a private reactivation draft', function () {
    [$event, $manager, $admin] = unusedCancelledEvent();
    $this->actingAs($admin)->post(route('admin.events.reactivation', $event), reactivationInput($event))->assertForbidden();
    foreach ([User::ROLE_RUNNER, User::ROLE_EVENT_MANAGER] as $role) {
        $other = User::factory()->create(['role' => $role]);
        $this->actingAs($other)->post(route('admin.events.reactivation', $event), reactivationInput($event))->assertForbidden();
    }
    $this->actingAs($manager)->get(route('admin.events.show', $event))->assertOk()->assertSee('Request Reactivation');
    $this->post(route('admin.events.reactivation', $event), reactivationInput($event))->assertSessionHasNoErrors()->assertRedirect();
    $draft = $event->revisions()->sole();
    expect($draft->isReactivationDraft())->toBeTrue()->and($draft->isCancelled())->toBeFalse()
        ->and($draft->review_status)->toBe('draft')->and($draft->cancelled_at)->toBeNull()
        ->and($draft->revisionRequest->cancellation_snapshot['cancellation_reason'])->toBe($event->cancellation_reason)
        ->and($draft->revisionRequest->cancellation_snapshot['cancelled_by_user_id'])->toBe($admin->id)
        ->and($event->fresh()->isCancelled())->toBeTrue();
    $this->get(route('admin.events.edit', $draft))->assertOk()->assertSee('Reactivation draft:');
    $draft->update(['title' => 'Private unsubmitted reactivation']);
    $this->actingAs($admin)->get(route('admin.events.show', $event))->assertOk()->assertDontSee('Private unsubmitted reactivation');
    $this->get(route('admin.events.show', $draft))->assertForbidden();
    $this->getJson('/api/events/'.$draft->id)->assertNotFound();
    $this->getJson('/api/events')->assertOk()->assertJsonMissing(['id' => $event->id, 'title' => $event->title]);
    $this->getJson('/api/events/'.$event->id)->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.can_register', false);
});

test('reactivation requires reason acknowledgement and current version', function (array $override) {
    [$event, $manager] = unusedCancelledEvent();
    $this->actingAs($manager)->post(route('admin.events.reactivation', $event), array_replace(reactivationInput($event), $override))
        ->assertStatus(isset($override['review_version']) ? 409 : 302);
    expect($event->revisions()->count())->toBe(0)->and($event->fresh()->isCancelled())->toBeTrue();
})->with([[['reactivation_reason' => '   ']], [['reactivation_reason' => str_repeat('x', 1001)]], [['confirmation' => 0]], [['review_version' => 999]]]);

test('ordinary revisions and forged direct setup writes cannot reopen a cancelled event', function () {
    [$event, $manager] = unusedCancelledEvent();
    $this->actingAs($manager)->post(route('admin.events.revision', $event), ['review_version' => $event->review_version])->assertConflict();
    $this->put(route('admin.events.update', $event), ['status' => 'upcoming', 'cancelled_at' => null])->assertForbidden();
    expect($event->fresh()->isCancelled())->toBeTrue()->and($event->revisions()->count())->toBe(0);
});

test('unused means no historical registration category start or saved recording plan', function (string $history) {
    [$event, $manager] = unusedCancelledEvent();
    $category = $event->categories()->sole();
    if ($history === 'registration') {
        Registration::create(['event_id' => $event->id, 'category_id' => $category->id,
            'user_id' => User::factory()->create()->id, 'status' => 'rejected']);
    } elseif ($history === 'start') {
        $category->update(['started_at' => now()->subHour(), 'started_by_user_id' => $manager->id]);
    } else {
        DB::table('checkpoint_scan_plans')->insert(['event_id' => $event->id, 'category_id' => $category->id,
            'fingerprint' => str_repeat('a', 64), 'category_started_at' => now(), 'snapshot' => '{}']);
    }
    $this->actingAs($manager)->post(route('admin.events.reactivation', $event), reactivationInput($event))->assertSessionHasErrors('reactivation');
    expect($event->revisions()->count())->toBe(0);
})->with(['registration', 'start', 'plan']);

test('Admin approval alone reactivates the same event and preserves cancellation audit and category identity', function () {
    [$event, $manager, $admin] = unusedCancelledEvent();
    $categoryId = $event->categories()->sole()->id;
    $firstPublication = $event->first_published_at->toIso8601String();
    $draft = reactivationDraft($event, $manager);
    expect(reactivationDraft($event, $manager)->id)->toBe($draft->id);
    $draft->update(['title' => 'New approved safe race']);
    $workflow = app(EventReviewWorkflow::class);
    $workflow->transition($draft, $manager, 'submit', 0);
    expect($event->fresh()->isCancelled())->toBeTrue();
    $this->actingAs($admin)->get(route('admin.events.show', $draft))->assertOk()->assertSee('Approve Reactivation &amp; Publish', false);
    $this->post(reactivationReview($draft, 'approve'), ['review_version' => 1])->assertSessionHasNoErrors()->assertRedirect(route('admin.events.show', $event));
    $event->refresh();
    expect($event->isCancelled())->toBeFalse()->and($event->cancelled_at)->toBeNull()
        ->and($event->cancellation_reason)->toBeNull()->and($event->cancelled_by_user_id)->toBeNull()
        ->and($event->effective_status)->toBe('upcoming')->and($event->title)->toBe('New approved safe race')
        ->and($event->categories()->sole()->id)->toBe($categoryId)
        ->and($event->first_published_at->toIso8601String())->toBe($firstPublication)
        ->and($draft->revisionRequest->fresh()->applied_at)->not->toBeNull()
        ->and($draft->revisionRequest->fresh()->cancellation_snapshot['cancellation_reason'])->toBe('Unsafe weather at original venue.')
        ->and($event->reviews()->where('action', 'reactivation_approved')->count())->toBe(1);
    $this->get(route('admin.events.review-history', $draft))->assertOk()->assertSee('Preserved cancellation history')->assertSee('Unsafe weather at original venue.');
    $this->getJson('/api/events/'.$event->id)->assertOk()->assertJsonPath('data.status', 'upcoming');
    $this->post(reactivationReview($draft, 'approve'), ['review_version' => 1])->assertSessionHasErrors('reactivation');
});

test('reactivation can be returned withdrawn edited and resubmitted without reopening the original', function () {
    [$event, $manager, $admin] = unusedCancelledEvent();
    $draft = reactivationDraft($event, $manager);
    $workflow = app(EventReviewWorkflow::class);
    $workflow->transition($draft, $manager, 'submit', 0);
    $workflow->transition($draft, $admin, 'return', 1, 'Clarify the new venue.');
    $this->actingAs($manager)->get(route('admin.events.edit', $draft))->assertOk();
    $workflow->transition($draft, $manager, 'submit', 2);
    $workflow->transition($draft, $manager, 'withdraw', 3);
    $workflow->transition($draft, $manager, 'discard', 4);
    expect($event->fresh()->isCancelled())->toBeTrue()->and($draft->fresh()->review_status)->toBe('discarded');
    expect(reactivationDraft($event, $manager)->id)->not->toBe($draft->id);
});

test('participant history appearing after request blocks submission and approval but not withdrawal or discard', function (bool $submitted) {
    [$event, $manager, $admin] = unusedCancelledEvent();
    $draft = reactivationDraft($event, $manager);
    if ($submitted) {
        app(EventReviewWorkflow::class)->transition($draft, $manager, 'submit', 0);
    }
    Registration::create(['event_id' => $event->id, 'category_id' => $event->categories()->sole()->id,
        'user_id' => User::factory()->create()->id, 'status' => 'rejected']);
    $this->actingAs($submitted ? $admin : $manager)->post(reactivationReview($draft, $submitted ? 'approve' : 'submit'),
        ['review_version' => $submitted ? 1 : 0])->assertSessionHasErrors('reactivation');
    $this->actingAs($manager)->get(route('admin.events.edit', $draft))->assertForbidden();
    if ($submitted) {
        $this->post(reactivationReview($draft, 'withdraw'), ['review_version' => 1])->assertSessionHasNoErrors();
    }
    $this->post(reactivationReview($draft, 'discard'), ['review_version' => $submitted ? 2 : 0])->assertSessionHasNoErrors();
    expect($event->fresh()->isCancelled())->toBeTrue()->and($draft->fresh()->review_status)->toBe('discarded');
})->with([false, true]);

test('future timing deadline category schedule and manager availability are rechecked at submission and approval', function (string $problem, bool $submitted) {
    [$event, $manager, $admin] = unusedCancelledEvent();
    $draft = reactivationDraft($event, $manager);
    if ($submitted) {
        app(EventReviewWorkflow::class)->transition($draft, $manager, 'submit', 0);
    }
    if ($problem === 'time') {
        $this->travelTo(Carbon::parse('2026-11-21 07:00:00', config('app.timezone')));
    } elseif ($problem === 'deadline') {
        $draft->update(['registration_deadline' => today()->subDay()]);
    } elseif ($problem === 'category') {
        $draft->categories()->sole()->update(['scheduled_end_time' => '23:00']);
    } else {
        $other = $event->replicate();
        $other->forceFill(['slug' => 'conflict-'.Str::uuid(), 'cancelled_at' => null,
            'cancellation_reason' => null, 'cancelled_by_user_id' => null, 'review_status' => 'draft', 'status' => 'draft'])->save();
    }
    // A changed submitted payload is rejected as a conflict before readiness.
    $response = $this->actingAs($submitted ? $admin : $manager)->post(reactivationReview($draft, $submitted ? 'approve' : 'submit'),
        ['review_version' => $submitted ? 1 : 0]);
    if ($submitted && in_array($problem, ['deadline', 'category'], true)) {
        $response->assertConflict();
    } else {
        $response->assertSessionHasErrors('review');
    }
    expect($event->fresh()->isCancelled())->toBeTrue()->and($draft->revisionRequest->applied_at)->toBeNull();
})->with(['time', 'deadline', 'category', 'conflict'])->with([false, true]);

test('stale cancellation snapshots and stale published setup cannot be approved', function (string $change) {
    [$event, $manager, $admin] = unusedCancelledEvent();
    $draft = reactivationDraft($event, $manager);
    app(EventReviewWorkflow::class)->transition($draft, $manager, 'submit', 0);
    DB::table('events')->where('id', $event->id)->update($change === 'cancellation'
        ? ['cancellation_reason' => 'Another cancellation decision.'] : ['description' => 'External setup change.']);
    $this->actingAs($admin)->post(reactivationReview($draft, 'approve'), ['review_version' => 1])->assertConflict();
    expect($event->fresh()->isCancelled())->toBeTrue()->and($draft->revisionRequest->applied_at)->toBeNull();
})->with(['cancellation', 'setup']);

test('Admin can archive a cancelled event immediately and restoration keeps cancellation and delays rearchiving', function () {
    [$event, $manager, $admin] = unusedCancelledEvent();
    $cancellation = app(EventReactivation::class)->snapshot($event);
    $draft = reactivationDraft($event, $manager);
    app(EventReviewWorkflow::class)->transition($draft, $manager, 'submit', 0);
    $this->actingAs($manager)->post(route('admin.events.archive', $event), [])->assertForbidden();
    reactivationCleanup($event, $admin, 'archive');
    $this->actingAs($admin)->post(reactivationReview($draft, 'approve'), ['review_version' => 1])->assertSessionHasErrors('reactivation');
    $this->get(route('admin.events.index', ['archive' => 'archived']))->assertOk()->assertSee('It stays Cancelled');
    $this->travel(35)->days();
    reactivationCleanup($event, $admin, 'restore');
    expect($event->fresh()->isCancelled())->toBeTrue()->and(app(EventReactivation::class)->snapshot($event->fresh()))->toEqual($cancellation);
    $this->getJson('/api/events')->assertOk()->assertJsonMissing(['id' => $event->id, 'title' => $event->title]);
    $this->getJson('/api/events/'.$event->id)->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.can_register', false);
    $this->travel(29)->days();
    expect(app(AutomaticEventArchiver::class)->run())->toBe(0);
    $this->travel(1)->days();
    expect(app(AutomaticEventArchiver::class)->run())->toBe(1)->and($event->fresh()->isCancelled())->toBeTrue();
});

test('cancelled retention uses cancellation timestamp even for old publication exemptions and a future event end', function (int $offset, bool $eligible) {
    [$event] = unusedCancelledEvent();
    DB::table('events')->where('id', $event->id)->update(['cancelled_at' => now()->subDays(30)->addSeconds($offset),
        'first_published_at' => null, 'auto_archive_exempt_at' => now()]);
    expect(app(AutomaticEventArchiver::class)->run(true))->toBe((int) $eligible)->and($event->fresh()->archived_at)->toBeNull();
    config(['event_archiving.enabled' => false]);
    expect(app(AutomaticEventArchiver::class)->run())->toBe(0);
    config(['event_archiving.enabled' => true]);
    expect(app(AutomaticEventArchiver::class)->run())->toBe((int) $eligible)
        ->and($event->fresh()->archived_at !== null)->toBe($eligible)->and($event->fresh()->isCancelled())->toBeTrue();
})->with([[1, false], [0, true], [-1, true]]);

test('JSON object key order does not invalidate cancellation identity and populated migration rollback is refused', function () {
    [$event, $manager] = unusedCancelledEvent();
    $draft = reactivationDraft($event, $manager);
    $revision = $draft->revisionRequest;
    $snapshot = $revision->cancellation_snapshot;
    ksort($snapshot);
    $revision->update(['cancellation_snapshot' => $snapshot]);
    expect(app(EventReactivation::class)->matchesCancellation($draft->fresh(), $event))->toBeTrue();
    $migration = require database_path('migrations/2026_11_20_000002_add_reviewed_event_reactivation.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'history must be preserved');
});
