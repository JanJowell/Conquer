<?php

return [
    // Explicit opt-in after migration, preview and reviewed scheduler rollout.
    'enabled' => env('EVENT_AUTO_ARCHIVE_ENABLED', false) === true,
    // Set once, explicitly, at rollout. Never substitute now() or a deployment
    // date: restarting a service must not move the protected publication cohort.
    'activated_at' => env('EVENT_AUTO_ARCHIVE_ACTIVATED_AT'),
];
