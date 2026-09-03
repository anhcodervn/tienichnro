<?php

return [
    'enabled' => (bool) env('TENANCY_ENABLED', false),

    'main_domain' => strtolower((string) env('TENANCY_MAIN_DOMAIN', 'napcarot.com')),

    'main_aliases' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('TENANCY_MAIN_ALIASES', 'www.napcarot.com')),
    ))),

    'fallback_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('TENANCY_FALLBACK_HOSTS', 'localhost,127.0.0.1')),
    ))),
];
