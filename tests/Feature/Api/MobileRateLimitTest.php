<?php

test('mobile login attempts are rate limited', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/login', [
            'identifier' => 'missing-runner',
            'password' => 'incorrect-password',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/login', [
        'identifier' => 'missing-runner',
        'password' => 'incorrect-password',
    ])->assertTooManyRequests();
});
