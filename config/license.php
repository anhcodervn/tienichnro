<?php

return [
    'cache_store' => env('LICENSE_CACHE_STORE', 'redis'),
    'challenge_ttl' => 120,
    'timestamp_tolerance' => 60,
    'retention_days' => 90,
    'services_visible' => env('SERVICES_VISIBLE', false),
];
