<?php

use App\Models\User;

function executiveUser(): User
{
    return User::factory()->create([
        'role' => User::ROLE_EXECUTIVE,
    ]);
}

test('executive navigation consolidates analytics and reports without losing exports', function () {
    $executive = executiveUser();

    $this->actingAs($executive)
        ->get(route('admin.analytics'))
        ->assertOk()
        ->assertSee('Analytics &amp; Reports', false)
        ->assertSee('Export Users')
        ->assertSee('Export Events')
        ->assertSee('Export Summary')
        ->assertDontSee('href="'.route('admin.reports').'"', false);

    $this->actingAs($executive)
        ->get(route('admin.reports'))
        ->assertRedirect(route('admin.analytics'));
});

test('executive overview pages render as read only', function () {
    $executive = executiveUser();

    $this->actingAs($executive)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Executive Dashboard')
        ->assertSee('Average Overall Rating')
        ->assertDontSee('Structured ratings are not yet stored');

    $this->actingAs($executive)
        ->withSession(['success' => 'User overview loaded.'])
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('Users Overview')
        ->assertSee('Read only')
        ->assertDontSee('Add New User')
        ->assertDontSee('Create another user');

    $this->actingAs($executive)
        ->withSession(['success' => 'Event overview loaded.'])
        ->get(route('admin.events.index'))
        ->assertOk()
        ->assertSee('Events Overview')
        ->assertDontSee('Create Event')
        ->assertDontSee('Create another event');

    $this->actingAs($executive)
        ->get(route('admin.feedback-insights'))
        ->assertOk()
        ->assertSee('Feedback Insights');
});

test('executive cannot access account event or race operation management routes', function () {
    $executive = executiveUser();

    $this->actingAs($executive)->get(route('admin.users.create'))->assertForbidden();
    $this->actingAs($executive)->get(route('admin.events.create'))->assertForbidden();
    $this->actingAs($executive)->get(route('admin.participants.index'))->assertForbidden();
    $this->actingAs($executive)->get(route('admin.payments.index'))->assertForbidden();
    $this->actingAs($executive)->get(route('admin.check-in.index'))->assertForbidden();
    $this->actingAs($executive)->get(route('admin.results.index'))->assertForbidden();
});

test('admin section helper matches executive routes', function () {
    $executive = executiveUser();

    expect($executive->canAccessAdminSection('dashboard'))->toBeTrue()
        ->and($executive->canAccessAdminSection('analytics'))->toBeTrue()
        ->and($executive->canAccessAdminSection('reports'))->toBeTrue()
        ->and($executive->canAccessAdminSection('users'))->toBeTrue()
        ->and($executive->canAccessAdminSection('events'))->toBeTrue()
        ->and($executive->canAccessAdminSection('feedback'))->toBeTrue()
        ->and($executive->canAccessAdminSection('notifications'))->toBeFalse()
        ->and($executive->canAccessAdminSection('security'))->toBeFalse();
});
