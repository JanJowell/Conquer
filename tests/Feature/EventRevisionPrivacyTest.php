<?php

use App\Models\Event;
use App\Models\User;
use App\Services\EventReviewWorkflow;

function privateRevisionFixture(): array
{
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $live = Event::forceCreate([
        'title' => 'Published race', 'slug' => 'private-revision-'.uniqid(), 'description' => 'Public description',
        'venue' => 'Public park', 'event_date' => today()->addMonth(), 'event_end_date' => today()->addMonth(),
        'registration_deadline' => today()->addWeek(), 'start_time' => '06:00', 'end_time' => '12:00',
        'interest_type' => 'Marathon', 'type_details' => ['cutoff_time' => '6 hours'],
        'banner_image' => 'events/test.jpg', 'manager_id' => $manager->id, 'review_status' => 'approved',
    ]);
    $live->categories()->create(['name' => '5K Open', 'distance_km' => 5, 'status' => 'open', 'price_cents' => 0,
        'slot_limit' => 100, 'checkpoint_map_image' => 'categories/checkpoint-maps/private-revision.png']);
    $live->refreshAutomaticStatus();
    $workflow = app(EventReviewWorkflow::class);
    $draft = $workflow->startRevision($live, $manager, 0);
    $draft->update(['title' => 'Unsubmitted private title', 'venue' => 'Secret assembly point']);
    $draft->categories()->sole()->update(['qualification_notes' => 'Private category notes']);
    $draft->checkpoints()->create(['name' => 'Private checkpoint', 'location' => 'Secret checkpoint location',
        'latitude' => 14, 'longitude' => 121, 'type' => 'hydration', 'order' => 1]);

    return [$live, $draft, $manager, $admin, $workflow];
}

test('working revisions are manager private and do not replace the published event in the list', function () {
    [$live, $draft, $manager, $admin, $workflow] = privateRevisionFixture();
    $hash = $workflow->setupHash($live);
    $this->actingAs($manager)->get(route('admin.events.index'))->assertOk()
        ->assertSee('Draft revision')->assertSee('published event unchanged')->assertDontSee('Secret assembly point')
        ->assertViewHas('events', fn ($events) => $events->total() === 1 && $events->first()->id === $live->id
            && $events->first()->effective_status === 'upcoming' && $events->first()->activeRevision->id === $draft->id);
    $this->get(route('admin.events.show', $draft))->assertOk()->assertSee('Draft revision');
    $this->actingAs($admin)->get(route('admin.events.index'))->assertOk()->assertDontSee('Unsubmitted private title')
        ->assertDontSee('Draft revision')->assertViewHas('events', fn ($events) => $events->total() === 1
            && $events->first()->activeRevision === null && $events->first()->status === 'upcoming');
    $this->get(route('admin.events.show', $draft))->assertForbidden();
    $this->get(route('admin.events.show', $live))->assertOk()->assertDontSee('Open Active Revision')
        ->assertDontSee('Unsubmitted private title');
    $this->get(route('admin.events.review-history', $draft))->assertNotFound();
    $this->get(route('admin.events.index', ['review_status' => 'draft']))->assertOk()
        ->assertViewHas('events', fn ($events) => $events->isEmpty());
    $this->getJson('/api/events/'.$draft->id)->assertNotFound();
    $this->getJson('/api/events/'.$live->id)->assertOk()->assertJsonPath('data.title', 'Published race');
    $this->get(route('home'))->assertOk()->assertSee('Published race')->assertDontSee('Unsubmitted private title');
    expect($workflow->setupHash($live))->toBe($hash)->and($live->fresh()->status)->toBe('upcoming');
});

test('admin cannot discover working revision details through lists selectors or search', function () {
    [$live, $draft, $manager, $admin] = privateRevisionFixture();
    $this->actingAs($admin)->get(route('admin.categories.index', ['event_id' => $draft->id]))->assertOk()
        ->assertDontSee('Private category notes')->assertDontSee('Unsubmitted private title')
        ->assertViewHas('categories', fn ($categories) => $categories->total() === 0);
    $this->get(route('admin.content.checkpoints', ['event_id' => $draft->id]))->assertOk()
        ->assertDontSee('Private checkpoint')->assertDontSee('Unsubmitted private title');
    foreach (['admin.search' => ['q' => 'Unsubmitted private'], 'admin.payments.index' => [],
        'admin.participants.index' => [], 'admin.announcements.index' => [], 'admin.certificates.index' => [], 'admin.e-badges.index' => []] as $route => $parameters) {
        $this->get(route($route, $parameters))->assertOk()->assertDontSee('Unsubmitted private title');
    }
    $this->actingAs($manager)->get(route('admin.categories.index', ['event_id' => $draft->id]))->assertOk()
        ->assertSee('Unsubmitted private title');
});

test('submission exposes a reviewable revision without changing the published event row', function () {
    [$live, $draft, $manager, $admin, $workflow] = privateRevisionFixture();
    $workflow->transition($draft, $manager, 'submit', 0);
    $this->actingAs($admin)->get(route('admin.events.index', ['review_status' => 'pending_review']))->assertOk()
        ->assertSee('Revision awaiting review')->assertDontSee('Secret assembly point')
        ->assertViewHas('events', fn ($events) => $events->total() === 1 && $events->first()->id === $live->id
            && $events->first()->activeRevision->id === $draft->id && $events->first()->effective_status === 'upcoming');
    $this->get(route('admin.events.show', $draft))->assertOk()->assertSee('Secret assembly point')
        ->assertSee('Approve &amp; Publish', false)->assertSee('Private checkpoint');
    $this->get(route('admin.categories.index', ['event_id' => $draft->id]))->assertOk()
        ->assertViewHas('categories', fn ($categories) => $categories->total() === 1);
    $this->get(route('admin.events.review-history', $draft))->assertOk()->assertSee('Secret assembly point');
    expect($live->fresh()->title)->toBe('Published race')->and($live->fresh()->status)->toBe('upcoming');
});

test('returned revisions hide ongoing edits but preserve each submitted version and return reason', function () {
    [$live, $draft, $manager, $admin, $workflow] = privateRevisionFixture();
    $workflow->transition($draft, $manager, 'submit', 0);
    $saved = $draft->reviews()->where('action', 'submit')->sole()->submission_snapshot;
    $this->actingAs($admin)->post(route('admin.events.review', [$draft, 'return']), [
        'review_version' => 1, 'reason' => 'Clarify the assembly point.',
    ])->assertRedirect(route('admin.events.review-history', $draft));
    $draft->refresh()->update(['title' => 'Still private correction', 'venue' => 'Not yet submitted venue']);
    $this->get(route('admin.events.show', $draft))->assertForbidden();
    $this->get(route('admin.events.review-history', $draft))->assertOk()
        ->assertSee('Secret assembly point')->assertSee('Clarify the assembly point.')
        ->assertDontSee('Still private correction')->assertDontSee('Not yet submitted venue');
    $this->get(route('admin.events.show', $live))->assertOk()->assertSee('Submitted review history')
        ->assertDontSee('Open Active Revision')->assertDontSee('Still private correction');
    $workflow->transition($draft, $manager, 'submit', 2);
    $this->get(route('admin.events.show', $draft))->assertOk()->assertSee('Not yet submitted venue');
    $this->get(route('admin.events.review-history', $draft))->assertOk()
        ->assertSee('Secret assembly point')->assertSee('Not yet submitted venue')->assertSee('Clarify the assembly point.');
    expect($draft->reviews()->where('action', 'submit')->reorder('id')->first()->submission_snapshot)->toBe($saved);
    $workflow->transition($draft, $admin, 'approve', 3);
    expect($live->fresh()->venue)->toBe('Not yet submitted venue')
        ->and($draft->reviews()->where('action', 'submit')->reorder('id')->first()->submission_snapshot)->toBe($saved);
});

test('withdrawal immediately revokes admin draft access and stale approval cannot publish edits', function () {
    [$live, $draft, $manager, $admin, $workflow] = privateRevisionFixture();
    $workflow->transition($draft, $manager, 'submit', 0);
    $workflow->transition($draft, $manager, 'withdraw', 1);
    $draft->refresh()->update(['venue' => 'Private withdrawn edit']);
    $this->actingAs($admin)->get(route('admin.events.show', $draft))->assertForbidden();
    $this->post(route('admin.events.review', [$draft, 'approve']), ['review_version' => 1])->assertConflict();
    $this->get(route('admin.events.review-history', $draft))->assertOk()
        ->assertSee('Secret assembly point')->assertDontSee('Private withdrawn edit');
    $this->get(route('admin.content.checkpoints', ['event_id' => $draft->id]))->assertOk()->assertDontSee('Private checkpoint');
    expect($live->fresh()->venue)->toBe('Public park')->and($draft->fresh()->review_status)->toBe('draft');
});

test('review history is access controlled and never invents snapshots for older submissions', function () {
    [, $draft, $manager, $admin, $workflow] = privateRevisionFixture();
    $workflow->transition($draft, $manager, 'submit', 0);
    $draft->reviews()->where('action', 'submit')->update(['submission_snapshot' => null]);
    $workflow->transition($draft, $admin, 'return', 1, 'Retained older explanation');
    $this->actingAs($admin)->get(route('admin.events.review-history', $draft))->assertOk()
        ->assertSee('This older submission has no saved setup snapshot.')->assertSee('Retained older explanation')
        ->assertDontSee('Secret assembly point');
    $other = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($other)->get(route('admin.events.review-history', $draft))->assertNotFound();
    $this->get(route('admin.events.show', $draft))->assertForbidden();
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $this->actingAs($runner)->get(route('admin.events.review-history', $draft))->assertForbidden();
    $this->actingAs($manager)->get(route('admin.events.review-history', $draft))->assertOk();
});
