<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Administrative account limits
    |--------------------------------------------------------------------------
    |
    | Every account assigned to a limited role consumes a slot, including
    | pending, suspended, and banned accounts. A slot is released only when the
    | account is deleted or moved to another role. Set a limit to null to make
    | that role unlimited.
    |
    */
    'limits' => [
        'super_admin' => env('MAX_ADMIN_ACCOUNTS', 2),
        'executive' => env('MAX_EXECUTIVE_ACCOUNTS', 3),
        'content_moderator' => env('MAX_CONTENT_MODERATOR_ACCOUNTS', 5),
        'event_manager' => env('MAX_EVENT_MANAGER_ACCOUNTS'),
    ],
];
