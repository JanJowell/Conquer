<?php

test('mobile config exposes profile interests and focus option lists', function () {
    $this
        ->getJson('/api/config')
        ->assertOk()
        ->assertJsonPath('event_interest_types.0', 'Cycling')
        ->assertJsonPath('training_focus_types.0', 'Cycling')
        ->assertJsonPath('interests.0', 'Cycling')
        ->assertJsonPath('shirt_sizes.0', 'XS');
});
