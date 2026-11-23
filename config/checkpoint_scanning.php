<?php

return [
    // Deliberately off. Local feature tests opt in without changing any .env.
    'enabled' => (bool) env('CHECKPOINT_SCANNING_ENABLED', false),

    // Explicit rollout choice: pilot (default) or eligible (normal access rules).
    // Neither scope bypasses authorization, reviewed setup or scan eligibility.
    'scope' => env('CHECKPOINT_SCANNING_SCOPE', 'pilot'),

    // Pilot scope requires both exact IDs. Eligible scope does not use them.
    // Keep raw values: invalid IDs must fail closed, not be coerced into a match.
    'pilot_event_id' => env('CHECKPOINT_SCANNING_PILOT_EVENT_ID'),
    'pilot_category_id' => env('CHECKPOINT_SCANNING_PILOT_CATEGORY_ID'),
];
