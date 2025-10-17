<?php

return [
    'central_domains' => array_filter(array_map('trim', explode(',', env('TENANCY_CENTRAL_DOMAINS', 'localhost,127.0.0.1')))),
    'reserved_subdomains' => ['app', 'www'],
    'header_keys' => ['X-Tenant', 'X-Tenant-ID', 'X-Tenant-Slug'],
];
