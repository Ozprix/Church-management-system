<?php

return [
    'driver' => env('BILLING_DRIVER', 'log'),

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'product_map' => [
            // 'starter' => 'prod_xxx',
        ],
    ],
];
