<?php

return [
    'base_url' => env('ESB_BASE_URL', 'https://core-api.esb.co.id'),

    /*
    |--------------------------------------------------------------------------
    | ESB Core
    |--------------------------------------------------------------------------
    |
    | Credentials are intentionally read from the environment. Use a dedicated
    | integration account because ESB Core closes an existing session when the
    | same credentials are used to log in again.
    |
    */
    'core' => [
        'base_url' => env('ESB_CORE_BASE_URL', 'https://services.esb.co.id/core'),
        'username' => env('ESB_CORE_USERNAME'),
        'password' => env('ESB_CORE_PASSWORD'),
        'timeout' => (int) env('ESB_CORE_TIMEOUT', 60),
        'token_ttl' => (int) env('ESB_CORE_TOKEN_TTL', 3300),
        'uoms' => [
            2 => 'PCS',
            5 => 'GR',
            6 => 'ML',
            8 => 'BUTIR',
            16 => 'Resep',
            26 => 'Porsi',
        ],
    ],

    'master_product' => [
        'base_url' => env('ESB_MASTER_PRODUCT_BASE_URL', 'https://core-api.esb.co.id'),
        'token' => env('ESB_MASTER_PRODUCT_TOKEN', env('ESB_TOKEN_BLO7', '')),
    ],

    'tokens' => [
        'BLO7' => env('ESB_TOKEN_BLO7', ''),
        'BLO6' => env('ESB_TOKEN_BLO6', ''),
        'BLO18' => env('ESB_TOKEN_BLO18', ''),
        'BLO16' => env('ESB_TOKEN_BLO16', ''),
        'BLO15' => env('ESB_TOKEN_BLO15', ''),
        'BLO14' => env('ESB_TOKEN_BLO14', ''),
        'BLO13' => env('ESB_TOKEN_BLO13', ''),
        'BLO12' => env('ESB_TOKEN_BLO12', ''),
        'BLO11' => env('ESB_TOKEN_BLO11', ''),
        'BLO10' => env('ESB_TOKEN_BLO10', ''),
        'BLMN' => env('ESB_TOKEN_BLMN', ''),
        'BLAR' => env('ESB_TOKEN_BLAR', ''),
        'BLSS' => env('ESB_TOKEN_BLSS', ''),
    ],
];
