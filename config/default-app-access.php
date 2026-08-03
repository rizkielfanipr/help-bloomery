<?php

return [
    'CASUAL_STAFF' => ['access employee app attendance'],
    'HRD_STAFF' => [
        'access backoffice',
        'access employee app attendance',
        'access employee app briefing',
        'access employee app sales report',
        'access employee app stock card',
        'access employee app purchasing',
        'access employee app design',
        'access employee app erp',
    ],
    'STORE_STAFF' => [
        'access backoffice',
        'access employee app technician request',
        'access employee app briefing',
        'access employee app sales report',
        'access employee app stock card',
        'access employee app purchasing',
        'access employee app design',
        'access employee app erp',
    ],
    'DRIVER' => ['access backoffice', 'access employee app driver'],
    'TECHNICIAN' => ['access backoffice', 'access employee app technician'],
    'IT_STAFF' => ['access backoffice'],
    'RND_STAFF' => ['access backoffice'],
    'SUPERVISOR_STORE' => [
        'access backoffice',
        'access employee app briefing',
        'access employee app sales report',
    ],
    'FINANCE_STAFF' => ['access backoffice'],
    'PURCHASING_STAFF' => ['access backoffice'],
];
