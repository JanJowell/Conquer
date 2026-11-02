<?php

use App\Models\Event;
use App\Models\User;

test('event lists show newest creations first across pages regardless of event schedule', function (string $role) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $viewer = $role === User::ROLE_EVENT_MANAGER
        ? $manager : User::factory()->create(['role' => $role]);
    $events = collect();

    // Insert oldest last, so ordering by ID alone would also be incorrect.
    for ($index = 12; $index >= 1; $index--) {
        $events->push(Event::forceCreate([
            'title' => 'List event '.$index,
            'slug' => 'list-event-'.$index,
            'venue' => 'Assembly point',
            'manager_id' => $manager->id,
            'event_date' => today()->addDays(30 + $index),
            'created_at' => now()->subDays(20)->addDays($index),
        ]));
    }

    $this->actingAs($viewer)->get(route('admin.events.index'))->assertOk()
        ->assertViewHas('events', fn ($page) => $page->total() === 12
            && $page->pluck('id')->all() === $events->take(10)->pluck('id')->all());
    $this->get(route('admin.events.index', ['page' => 2]))->assertOk()
        ->assertViewHas('events', fn ($page) => $page->pluck('id')->all() === $events->skip(10)->pluck('id')->all());
})->with([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER]);

test('equal creation times use newest ID first and preserve filters and manager ownership', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $other = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $createdAt = now()->subDay();
    $makeEvent = fn (array $overrides) => Event::forceCreate(array_replace([
        'title' => 'Matching race', 'slug' => 'list-'.uniqid(), 'venue' => 'Assembly point',
        'manager_id' => $manager->id, 'event_date' => today()->addMonth(),
        'interest_type' => 'Marathon', 'created_at' => $createdAt,
    ], $overrides));

    $older = $makeEvent([]);
    $newer = $makeEvent([]);
    $makeEvent(['manager_id' => $other->id]);
    $makeEvent(['title' => 'Unrelated event']);
    $makeEvent(['interest_type' => 'Cycling']);
    $makeEvent(['review_status' => 'changes_requested']);

    $this->actingAs($manager)->get(route('admin.events.index', [
        'search' => 'Matching', 'interest_type' => 'Marathon', 'status' => 'draft', 'review_status' => 'draft',
    ]))->assertOk()->assertViewHas('events', fn ($page) => $page->total() === 2
        && $page->pluck('id')->all() === [$newer->id, $older->id]);
});
