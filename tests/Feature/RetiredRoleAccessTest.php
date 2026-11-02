<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;

test('only admin event manager and participant roles are assignable', function () {
    expect(User::manageableRoles())->toBe(['runner', 'super_admin', 'event_manager'])
        ->and(User::adminWebRoles())->toBe(['super_admin', 'event_manager'])
        ->and(User::storedAdminRoles())->toBe(['super_admin', 'event_manager', 'admin']);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    foreach (['admin.users.index', 'admin.users.create'] as $route) {
        $this->actingAs($admin)->get(route($route))->assertOk()
            ->assertDontSee('value="executive"', false)
            ->assertDontSee('value="content_moderator"', false)
            ->assertDontSee('Executive (CEO/COO)')->assertDontSee('Content Moderator');
    }
});

test('retired role values cannot be assigned by forged create or update requests', function (string $role) {
    Notification::fake();
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $participant = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Rejected role', 'email' => 'retired-role@example.test', 'role' => $role,
    ])->assertSessionHasErrors('role');
    $this->put(route('admin.users.update', $participant), [
        'name' => $participant->name, 'email' => $participant->email, 'role' => $role,
    ])->assertSessionHasErrors('role');
    expect($participant->fresh()->role)->toBe(User::ROLE_RUNNER);
    $this->assertDatabaseMissing('users', ['email' => 'retired-role@example.test']);
    Notification::assertNothingSent();
})->with(['executive', 'content_moderator']);

test('retired and participant accounts have no administrative access even with an existing session', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    expect($user->isAdmin())->toBeFalse();
    foreach (['dashboard', 'analytics', 'reports', 'users', 'events', 'feedback', 'notifications', 'community', 'training', 'security'] as $section) {
        expect($user->canAccessAdminSection($section))->toBeFalse();
    }
    foreach (['admin.dashboard', 'admin.analytics', 'admin.reports', 'admin.users.index', 'admin.events.create',
        'admin.content.pending-review', 'admin.content.community-posts', 'admin.content.training-modules',
        'admin.notifications.index', 'admin.announcements.index', 'admin.feedback-insights'] as $route) {
        $this->actingAs($user)->get(route($route))->assertForbidden();
    }
})->with(['executive', 'content_moderator', 'runner']);

test('admins retain every moderation and reporting entry point', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    foreach (['admin.dashboard', 'admin.analytics', 'admin.reports', 'admin.users.index',
        'admin.content.pending-review', 'admin.content.community-posts', 'admin.content.training-modules',
        'admin.content.training-modules.create', 'admin.notifications.index', 'admin.announcements.index',
        'admin.feedback-insights'] as $route) {
        $this->actingAs($admin)->get(route($route))->assertOk();
    }
    foreach (['users', 'events', 'summary'] as $type) {
        $this->get(route('admin.reports.export', $type))->assertOk()->assertDownload();
    }
    $this->get(route('admin.analytics'))->assertSee('Export Users')->assertSee('Export Events')->assertSee('Export Summary');
});

test('event managers do not gain global moderation or user analytics access', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    foreach (['admin.content.pending-review', 'admin.content.community-posts', 'admin.content.training-modules',
        'admin.users.index', 'admin.analytics'] as $route) {
        $this->actingAs($manager)->get(route($route))->assertForbidden();
    }
    $this->get(route('admin.reports.export', 'users'))->assertForbidden();
    foreach (['admin.dashboard', 'admin.reports', 'admin.events.index', 'admin.announcements.index',
        'admin.notifications.index', 'admin.feedback-insights'] as $route) {
        $this->get(route($route))->assertOk();
    }
});
