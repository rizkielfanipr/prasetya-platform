<?php

declare(strict_types=1);

return [
    /*
    | Content Studio (ADR-0011). Post images live on a private disk and are only streamed to signed-in users.
    | Never point this at a public disk.
    */
    'disk' => env('CONTENT_DISK', 'local'),
];
