<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('admin.dashboard'));
});

test('admin layout includes an accessible mobile navigation drawer', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('id="admin-sidebar"', false)
        ->assertSee('id="admin-sidebar-backdrop"', false)
        ->assertSee('data-admin-sidebar-open', false)
        ->assertSee('data-admin-sidebar-close', false)
        ->assertSee('aria-controls="admin-sidebar"', false)
        ->assertSee("event.key === 'Escape'", false);
});
