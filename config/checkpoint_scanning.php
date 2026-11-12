<?php

return [
    // Deliberately off. Local feature tests opt in without changing any .env.
    'enabled' => (bool) env('CHECKPOINT_SCANNING_ENABLED', false),

    // Both exact IDs are mandatory, even when enabled. No unrestricted mode.
    // Keep raw values: invalid IDs must fail closed, not be coerced into a match.
    'pilot_event_id' => env('CHECKPOINT_SCANNING_PILOT_EVENT_ID'),
    'pilot_category_id' => env('CHECKPOINT_SCANNING_PILOT_CATEGORY_ID'),
];
