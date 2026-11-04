<?php

use App\Models\Event;
use App\Models\EventRevision;
use App\Models\Payment;
use App\Models\RaceResult;
use App\Models\Registration;
use App\Models\User;
use App\Services\EventReviewWorkflow;
use Illuminate\Support\Facades\Storage;

function reviewReadyEvent(User $manager, array $extra = []): Event
{
    $event = Event::forceCreate(array_replace([
        'title' => 'Reviewed race', 'slug' => 'review-'.uniqid(), 'description' => 'Reviewed details',
        'venue' => 'Meeting point', 'event_date' => today()->addMonth(), 'event_end_date' => today()->addMonth(),
        'registration_deadline' => today()->addWeek(), 'start_time' => '06:00', 'end_time' => '12:00',
        'interest_type' => 'Marathon', 'type_details' => ['cutoff_time' => '6 hours'],
        'banner_image' => 'events/banners/test.jpg', 'manager_id' => $manager->id,
        'review_status' => 'draft', 'status' => 'draft',
    ], $extra));
    $event->categories()->create(['name' => '5K Open', 'distance_km' => 5, 'status' => 'open',
        'slot_limit' => 100, 'checkpoint_map_image' => 'categories/checkpoint-maps/review-ready.png',
        'price_cents' => 0, 'price_currency' => 'PHP', 'scheduled_start_date' => $event->event_date,
        'scheduled_end_date' => $event->event_date, 'scheduled_start_time' => '06:00', 'scheduled_end_time' => '12:00']);
    $event->refreshAutomaticStatus();

    return $event->fresh();
}

function reviewAction(Event $event, string $action): string
{
    return route('admin.events.review', [$event, $action]);
}

test('only event managers create drafts and approval fields cannot be forged', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->actingAs($admin)->get(route('admin.events.create'))->assertForbidden();
    $this->post(route('admin.events.store'), [])->assertForbidden();
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $other = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($manager)->get(route('admin.events.create'))->assertOk();
    $this->post(route('admin.events.store'), ['title' => 'Private draft', 'venue' => 'Assembly hall',
        'event_date' => today()->addMonth()->toDateString(), 'interest_type' => 'Marathon',
        'manager_id' => $other->id, 'review_status' => 'approved', 'status' => 'upcoming',
        'approved_by' => $admin->id, 'revision_of_id' => 123])->assertSessionHasNoErrors()->assertRedirect();
    $event = Event::where('title', 'Private draft')->firstOrFail();
    expect($event->review_status)->toBe('draft')->and($event->status)->toBe('draft')
        ->and($event->manager_id)->toBe($manager->id)->and($event->approved_by)->toBeNull()
        ->and($event->revision_of_id)->toBeNull();
});

test('complete drafts stay private until admin approval publishes them immediately', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = reviewReadyEvent($manager);
    expect($event->publicReadinessErrors())->toBe([])->and($event->effective_status)->toBe('draft');
    $this->getJson('/api/events/'.$event->id)->assertNotFound();
    $this->actingAs($manager)->post(reviewAction($event, 'submit'), ['review_version' => 0])->assertSessionHasNoErrors();
    expect($event->fresh()->review_status)->toBe('pending_review');
    $this->getJson('/api/events/'.$event->id)->assertNotFound();
    $this->get(route('admin.events.edit', $event))->assertForbidden();
    $this->post(reviewAction($event, 'approve'), ['review_version' => 1])->assertForbidden();
    $this->actingAs($admin)->get(route('admin.events.show', $event))->assertOk()->assertSee('Approve &amp; Publish', false);
    $this->post(reviewAction($event, 'approve'), ['review_version' => 1])->assertSessionHasNoErrors();
    expect($event->fresh()->review_status)->toBe('approved')->and($event->fresh()->approved_by)->toBe($admin->id)
        ->and($event->fresh()->effective_status)->toBe('upcoming')->and($event->reviews()->count())->toBe(2);
    $this->getJson('/api/events/'.$event->id)->assertOk();
    $this->post(reviewAction($event, 'approve'), ['review_version' => 1])->assertConflict();
    $this->actingAs($manager)->get(route('admin.events.edit', $event))->assertForbidden();
});

test('return requires a reason and withdrawal resubmission invalidates stale approvals', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = reviewReadyEvent($manager);
    $this->actingAs($manager)->post(reviewAction($event, 'submit'), ['review_version' => 0])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(reviewAction($event, 'return'), ['review_version' => 1, 'reason' => '  '])->assertSessionHasErrors('reason');
    $this->post(reviewAction($event, 'return'), ['review_version' => 1, 'reason' => 'Clarify the assembly point.'])->assertSessionHasNoErrors();
    expect($event->fresh()->review_status)->toBe('changes_requested');
    $this->actingAs($manager)->get(route('admin.events.edit', $event))->assertOk();
    $this->post(reviewAction($event, 'submit'), ['review_version' => 2])->assertSessionHasNoErrors();
    $this->post(reviewAction($event, 'withdraw'), ['review_version' => 3])->assertSessionHasNoErrors();
    $this->post(reviewAction($event, 'submit'), ['review_version' => 4])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(reviewAction($event, 'approve'), ['review_version' => 3])->assertConflict();
    expect($event->fresh()->review_status)->toBe('pending_review')->and($event->reviews()->count())->toBe(5);
});

test('submission and approval recheck readiness and reject post submission tampering', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = reviewReadyEvent($manager);
    $event->update(['description' => null]);
    $this->actingAs($manager)->post(reviewAction($event, 'submit'), ['review_version' => 0])->assertSessionHasErrors('review');
    $event->update(['description' => 'Complete']);
    $this->post(reviewAction($event, 'submit'), ['review_version' => 0])->assertSessionHasNoErrors();
    $event->categories()->first()->update(['price_cents' => 100]);
    $this->actingAs($admin)->post(reviewAction($event, 'approve'), ['review_version' => 1])->assertConflict();
    expect($event->fresh()->review_status)->toBe('pending_review')->and($event->fresh()->approved_at)->toBeNull();
});

test('omitting structured details cannot bypass submission validation', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = reviewReadyEvent($manager);
    $event->update(['type_details' => null]);
    $this->actingAs($manager)->post(reviewAction($event, 'submit'), ['review_version' => 0])->assertSessionHasErrors('review');
    expect($event->fresh()->review_status)->toBe('draft');
});

test('other managers participants and guests cannot review withdraw or revise another event', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = reviewReadyEvent($manager);
    $this->post(reviewAction($event, 'submit'), ['review_version' => 0])->assertRedirect(route('login'));
    foreach ([User::ROLE_EVENT_MANAGER, User::ROLE_RUNNER] as $role) {
        $other = User::factory()->create(['role' => $role]);
        $this->actingAs($other)->get(route('admin.events.show', $event))->assertForbidden();
        foreach (['submit', 'withdraw', 'approve', 'return'] as $action) {
            $this->post(reviewAction($event, $action), ['review_version' => 0, 'reason' => 'No'])->assertForbidden();
        }
    }
});

test('reviewed revisions keep public data unchanged then update the original category identities', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $live = reviewReadyEvent($manager, ['review_status' => 'approved']);
    $category = $live->categories()->first();
    $workflow = app(EventReviewWorkflow::class);
    $original = $workflow->setupHash($live);
    $this->actingAs($manager)->post(route('admin.events.revision', $live), ['review_version' => 0])->assertSessionHasNoErrors();
    $draft = Event::where('revision_of_id', $live->id)->firstOrFail();
    $draft->update(['title' => 'Revised title', 'venue' => 'Revised assembly point']);
    $draft->categories()->first()->update(['qualification_notes' => 'Bring water']);
    expect($workflow->setupHash($live))->toBe($original);
    $this->getJson('/api/events/'.$draft->id)->assertNotFound();
    $this->getJson('/api/events/'.$live->id)->assertOk()->assertJsonPath('data.title', 'Reviewed race');
    $this->post(reviewAction($draft, 'submit'), ['review_version' => 0])->assertSessionHasNoErrors();
    $this->actingAs($admin)->get(route('admin.events.show', $draft))->assertOk()->assertSee('Compare current and proposed setup');
    $this->post(reviewAction($draft, 'approve'), ['review_version' => 1])->assertSessionHasNoErrors();
    expect($live->fresh()->title)->toBe('Revised title')->and($category->fresh()->qualification_notes)->toBe('Bring water')
        ->and($live->categories()->first()->id)->toBe($category->id)
        ->and(EventRevision::first()->applied_at)->not->toBeNull();
    $this->getJson('/api/events/'.$draft->id)->assertNotFound();
    $this->getJson('/api/events/'.$live->id)->assertOk()->assertJsonPath('data.title', 'Revised title');
});

test('pending setup writes cannot bypass review through category or checkpoint endpoints', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = reviewReadyEvent($manager, ['review_status' => 'pending_review']);
    $category = $event->categories()->first();
    $this->actingAs($manager)->put(route('admin.events.update', $event), [])->assertForbidden();
    $this->delete(route('admin.events.destroy', $event))->assertForbidden();
    $this->put(route('admin.categories.update', $category), [])->assertForbidden();
    $this->delete(route('admin.categories.destroy', $category))->assertForbidden();
    $this->post(route('admin.categories.store'), ['event_id' => $event->id])->assertForbidden();
    $this->post(route('admin.content.checkpoints.store'), ['event_id' => $event->id])->assertForbidden();
    $this->post(route('admin.categories.start', $category))->assertForbidden();
    expect($category->fresh())->not->toBeNull()->and($event->fresh()->review_status)->toBe('pending_review');
});

test('legacy events are not falsely approved or unpublished and draft announcements remain private', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $legacy = reviewReadyEvent($manager, ['review_status' => null]);
    $legacy->update(['end_time' => null]);
    $legacy->categories()->update(['slot_limit' => null, 'checkpoint_map_image' => null]);
    $legacy->refreshAutomaticStatus();
    expect($legacy->review_status)->toBeNull()->and($legacy->effective_status)->toBe('upcoming')
        ->and($legacy->approved_at)->toBeNull()->and($legacy->reviews()->count())->toBe(0);
    $draft = reviewReadyEvent(User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]));
    $draft->announcements()->create(['title' => 'Private announcement', 'content' => 'Private', 'is_published' => true]);
    $this->getJson('/api/announcements')->assertOk()->assertDontSee('Private announcement');
    $this->getJson('/api/events/'.$legacy->id)->assertOk();
});

test('revision form edits and replacement payment rows preserve public data and identifiers', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $live = reviewReadyEvent($manager, ['review_status' => 'approved']);
    $method = $live->paymentMethods()->create(['provider' => 'GCash', 'account_name' => 'Original', 'account_number' => '123', 'is_enabled' => true]);
    $workflow = app(EventReviewWorkflow::class);
    $draft = $workflow->startRevision($live, $manager, 0);
    expect($workflow->startRevision($live, $manager, 0)->id)->toBe($draft->id);
    $this->actingAs($manager)->put(route('admin.events.update', $draft), [
        'title' => 'HTTP reviewed revision', 'venue' => $draft->venue, 'event_date' => $draft->event_date->toDateString(),
        'event_end_date' => $draft->event_end_date->toDateString(), 'start_time' => '06:00', 'end_time' => '12:00',
        'interest_type' => 'Marathon', 'payment_methods_submitted' => 1,
        'payment_methods' => [['provider' => 'GCash', 'account_name' => 'Updated', 'account_number' => '456', 'is_enabled' => 1]],
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($live->fresh()->title)->toBe('Reviewed race')->and($method->fresh()->account_name)->toBe('Original');
    $workflow->transition($draft, $manager, 'submit', 0);
    $workflow->transition($draft, $admin, 'approve', 1);
    expect($live->fresh()->title)->toBe('HTTP reviewed revision')
        ->and($live->paymentMethods()->sole()->id)->toBe($method->id)->and($method->fresh()->account_name)->toBe('Updated');
});

test('revision approval preserves registration payment and official result rows exactly', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $live = reviewReadyEvent($manager, ['review_status' => 'approved']);
    $category = $live->categories()->sole();
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $registration = Registration::create(['user_id' => $runner->id, 'event_id' => $live->id,
        'category_id' => $category->id, 'status' => 'completed', 'bib_number' => 'TEST-1']);
    $payment = Payment::create(['registration_id' => $registration->id, 'user_id' => $runner->id,
        'event_id' => $live->id, 'category_id' => $category->id, 'provider' => 'GCash', 'amount_cents' => 10000,
        'currency' => 'PHP', 'status' => 'paid']);
    $result = RaceResult::create(['registration_id' => $registration->id, 'user_id' => $runner->id,
        'event_id' => $live->id, 'category_id' => $category->id, 'finish_time' => '00:30:00']);
    $before = array_map(fn ($item) => $item->fresh()->getAttributes(), [$registration, $payment, $result]);
    $workflow = app(EventReviewWorkflow::class);
    $draft = $workflow->startRevision($live, $manager, 0);
    $draft->update(['description' => 'Clearer description']);
    $draft->categories()->sole()->update(['qualification_notes' => 'Additional assembly information']);
    $workflow->transition($draft, $manager, 'submit', 0);
    $workflow->transition($draft, $admin, 'approve', 1);
    expect(array_map(fn ($item) => $item->fresh()->getAttributes(), [$registration, $payment, $result]))->toBe($before)
        ->and($live->categories()->sole()->id)->toBe($category->id)
        ->and($draft->registrations()->count())->toBe(0)->and($draft->raceResults()->count())->toBe(0);
});

test('registrations arriving after a revision starts still protect category identity during approval', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $live = reviewReadyEvent($manager, ['review_status' => 'approved']);
    $workflow = app(EventReviewWorkflow::class);
    $draft = $workflow->startRevision($live, $manager, 0);
    $draft->categories()->sole()->update(['distance_km' => 10]);
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $registration = Registration::create(['user_id' => $runner->id, 'event_id' => $live->id,
        'category_id' => $live->categories()->sole()->id, 'status' => 'approved']);
    $workflow->transition($draft, $manager, 'submit', 0);
    $this->actingAs($admin)->post(reviewAction($draft, 'approve'), ['review_version' => 1])->assertSessionHasErrors('review');
    expect((float) $live->categories()->sole()->distance_km)->toBe(5.0)
        ->and($registration->fresh())->not->toBeNull()->and(EventRevision::first()->applied_at)->toBeNull()
        ->and($draft->fresh()->review_status)->toBe('pending_review');
});

test('stale base revisions fail safely and can be withdrawn discarded and recreated', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $live = reviewReadyEvent($manager, ['review_status' => 'approved']);
    $workflow = app(EventReviewWorkflow::class);
    $draft = $workflow->startRevision($live, $manager, 0);
    $workflow->transition($draft, $manager, 'submit', 0);
    $live->update(['description' => 'An external integration changed the base']);
    $this->actingAs($admin)->post(reviewAction($draft, 'approve'), ['review_version' => 1])->assertConflict();
    expect($live->fresh()->description)->toBe('An external integration changed the base');
    $this->actingAs($manager)->post(reviewAction($draft, 'withdraw'), ['review_version' => 1])->assertSessionHasNoErrors();
    $this->post(reviewAction($draft, 'discard'), ['review_version' => 2])->assertSessionHasNoErrors();
    $new = $workflow->startRevision($live, $manager, 0);
    expect($new->id)->not->toBe($draft->id)->and($draft->fresh()->review_status)->toBe('discarded')
        ->and($new->description)->toBe($live->fresh()->description);
});

test('approval rechecks deadlines and manager eligibility after submission', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = reviewReadyEvent($manager);
    app(EventReviewWorkflow::class)->transition($event, $manager, 'submit', 0);
    $manager->update(['suspended_at' => now()]);
    $this->actingAs($admin)->post(reviewAction($event, 'approve'), ['review_version' => 1])->assertSessionHasErrors('review');
    $manager->update(['suspended_at' => null]);
    $this->travelTo($event->registration_deadline->copy()->addDay());
    $this->post(reviewAction($event, 'approve'), ['review_version' => 1])->assertSessionHasErrors('review');
    expect($event->fresh()->approved_at)->toBeNull()->and($event->fresh()->review_status)->toBe('pending_review');
});

test('draft course image removal cannot delete the current public image', function () {
    Storage::fake('public');
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $live = reviewReadyEvent($manager, ['review_status' => 'approved']);
    $category = $live->categories()->sole();
    $path = 'categories/checkpoint-maps/shared.png';
    Storage::disk('public')->put($path, 'private test image');
    $category->update(['checkpoint_map_image' => $path]);
    $draft = app(EventReviewWorkflow::class)->startRevision($live, $manager, 0);
    $copy = $draft->categories()->sole();
    $this->actingAs($manager)->put(route('admin.categories.update', $copy), [
        'category_type' => 'open', 'distance_option' => '5', 'scheduled_start_time' => '06:00',
        'scheduled_end_time' => '12:00', 'price_amount' => '0', 'price_currency' => 'PHP',
        'status' => 'open', 'remove_checkpoint_map_image' => 1,
    ])->assertSessionHasNoErrors();
    expect($copy->fresh()->checkpoint_map_image)->toBeNull()->and($category->fresh()->checkpoint_map_image)->toBe($path);
    Storage::disk('public')->assertExists($path);
});

test('private revisions do not inflate event exports and cannot be linked in participant posts', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $live = reviewReadyEvent($manager, ['review_status' => 'approved']);
    $draft = app(EventReviewWorkflow::class)->startRevision($live, $manager, 0);
    $draft->update(['title' => 'Private revision must not be exported']);
    $response = $this->actingAs($admin)->get(route('admin.reports.export', 'events'))->assertOk();
    expect($response->streamedContent())->not->toContain('Private revision must not be exported');
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER, 'api_token' => hash('sha256', 'review-test-token'), 'api_token_expires_at' => now()->addHour()]);
    $this->withToken('review-test-token')->postJson('/api/community-posts', ['content' => 'Test', 'event_id' => $draft->id])->assertUnprocessable();
});

test('removing unused revision options closes categories and disables methods without deleting public identifiers', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $live = reviewReadyEvent($manager, ['review_status' => 'approved']);
    $old = $live->categories()->create(['name' => 'Unused option', 'distance_km' => 10, 'status' => 'open']);
    $method = $live->paymentMethods()->create(['provider' => 'GCash', 'account_name' => 'Original', 'account_number' => '123', 'is_enabled' => true]);
    $workflow = app(EventReviewWorkflow::class);
    $draft = $workflow->startRevision($live, $manager, 0);
    $draft->categories()->where('name', 'Unused option')->delete();
    $draft->paymentMethods()->delete();
    $workflow->transition($draft, $manager, 'submit', 0);
    $workflow->transition($draft, $admin, 'approve', 1);
    expect($old->fresh()->status)->toBe('closed')->and($method->fresh()->is_enabled)->toBeFalse()
        ->and($live->categories()->where('status', 'open')->count())->toBe(1);
});
