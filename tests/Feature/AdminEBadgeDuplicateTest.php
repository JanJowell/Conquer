<?php

use App\Models\Category;
use App\Models\EBadge;
use App\Models\Event;
use App\Models\User;
use App\Services\EBadgeAutoIssuer;

function adminEBadgeUser(): User
{
    return User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
    ]);
}

function adminEBadgeEvent(): Event
{
    return Event::create([
        'title' => 'Night Run',
        'slug' => 'night-run-'.uniqid(),
        'venue' => 'Bacoor City',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'draft',
    ]);
}

function adminEBadgeCategory(Event $event): Category
{
    return Category::create([
        'event_id' => $event->id,
        'name' => '5K Open',
        'distance_km' => 5,
        'status' => 'open',
    ]);
}

function badgePayload(Event $event, Category $category, array $overrides = []): array
{
    return array_merge([
        'event_id' => $event->id,
        'category_id' => $category->id,
        'title' => '5K Finisher',
        'description' => 'Awarded to 5K finishers.',
        'criteria' => null,
        'auto_issue_rule' => EBadgeAutoIssuer::COMPLETED_EVENT,
        'image_path' => null,
        'is_active' => '1',
    ], $overrides);
}

test('admin cannot create an exact duplicate e-badge template', function () {
    $admin = adminEBadgeUser();
    $event = adminEBadgeEvent();
    $category = adminEBadgeCategory($event);

    EBadge::create([
        ...badgePayload($event, $category),
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.e-badges.store'), badgePayload($event, $category))
        ->assertSessionHasErrors('title');

    expect(EBadge::where('title', '5K Finisher')->count())->toBe(1);
});
test('admin can create overlapping but not exact duplicate e-badge templates', function () {
    $admin = adminEBadgeUser();
    $event = adminEBadgeEvent();
    $category = adminEBadgeCategory($event);

    EBadge::create([
        ...badgePayload($event, $category),
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.e-badges.store'), badgePayload($event, $category, [
            'title' => '5K Podium',
            'auto_issue_rule' => EBadgeAutoIssuer::TOP_3_CATEGORY,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(EBadge::count())->toBe(2);
});

test('admin can update an e-badge without being blocked by itself', function () {
    $admin = adminEBadgeUser();
    $event = adminEBadgeEvent();
    $category = adminEBadgeCategory($event);

    $badge = EBadge::create([
        ...badgePayload($event, $category),
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->patch(route('admin.e-badges.update', $badge), badgePayload($event, $category, [
            'description' => 'Updated description.',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($badge->fresh()->description)->toBe('Updated description.');
});
