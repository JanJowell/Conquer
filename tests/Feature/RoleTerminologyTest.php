<?php

use App\Models\User;

test('role labels use participant and admin terminology', function () {
    expect(User::roleLabels())
        ->toMatchArray([
            User::ROLE_RUNNER => 'Participant',
            User::ROLE_SUPER_ADMIN => 'Admin',
            'user' => 'Participant',
        ]);
});

test('user management displays the renamed role labels', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
    ]);

    User::factory()->create([
        'role' => User::ROLE_RUNNER,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('Participant')
        ->assertSee('Admin')
        ->assertDontSee('Super Admin');
});
