<?php

return [
    // Deliberately off. Local feature tests opt in without changing any .env.
    'enabled' => (bool) env('CHECKPOINT_SCANNING_ENABLED', false),
];
