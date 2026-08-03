<?php

return [
    'Akses Back Office' => [
        'Back Office' => ['access backoffice'],
    ],

    'Akses Employee App' => [
        'Absensi' => ['access employee app attendance'],
        'Driver' => ['access employee app driver'],
        'Teknisi' => ['access employee app technician'],
        'Request Teknisi' => ['access employee app technician request'],
        'Briefing' => ['access employee app briefing'],
        'Sales Report' => ['access employee app sales report'],
        'Stock Card' => ['access employee app stock card'],
        'Purchasing' => ['access employee app purchasing'],
        'Desain' => ['access employee app design'],
        'ERP' => ['access employee app erp'],
    ],

    'Human Resources' => [
        'Casual Staff' => ['view casual staff', 'create casual staff', 'edit casual staff', 'delete casual staff'],
        'Posisi Casual' => ['view casual positions', 'create casual positions', 'edit casual positions', 'delete casual positions'],
        'Lowongan Posisi' => ['view casual openings', 'create casual openings', 'edit casual openings', 'delete casual openings'],
        'Absensi Casual' => ['view clock records', 'create clock records', 'edit clock records', 'delete clock records'],
        'Daily Briefing' => ['view briefing records', 'create briefing records', 'edit briefing records', 'delete briefing records'],
        'Monitoring Poin' => ['view briefing items', 'create briefing items', 'edit briefing items', 'delete briefing items'],
        'Nilai Briefing' => ['view briefing scores', 'create briefing scores', 'edit briefing scores', 'delete briefing scores'],
    ],

    'Driver' => [
        'Perjalanan' => ['view trips', 'create trips', 'edit trips', 'delete trips'],
        'Rute Perjalanan' => ['view trip routes', 'create trip routes', 'edit trip routes', 'delete trip routes'],
        'Kendaraan' => ['view vehicles', 'create vehicles', 'edit vehicles', 'delete vehicles'],
    ],

    'Teknisi' => [
        'Service Request' => ['view service requests', 'create service requests', 'edit service requests', 'delete service requests'],
    ],

    'Inventory' => [
        'Stock Card' => ['view stock cards', 'create stock cards', 'edit stock cards', 'delete stock cards'],
    ],

    'Finance' => [
        'Sales Report' => [
            'view sales reports', 'create sales reports', 'edit sales reports', 'delete sales reports',
            'review sales reports as supervisor', 'review sales reports as finance',
            'input sales settlements',
        ],
        'Metode Pembayaran' => ['view payment methods', 'create payment methods', 'edit payment methods', 'delete payment methods'],
    ],

    'Purchasing' => [
        'Permintaan Pembelian' => ['view purchase requests', 'create purchase requests', 'edit purchase requests', 'delete purchase requests'],
    ],

    'Brand Marketing' => [
        'Permintaan Design' => ['view design requests', 'create design requests', 'edit design requests', 'delete design requests'],
        'Kategori Design' => ['view design categories', 'create design categories', 'edit design categories', 'delete design categories'],
    ],

    'Information Technology' => [
        'Permintaan ERP' => ['view erp requests', 'create erp requests', 'edit erp requests', 'delete erp requests'],
        'Modul ERP' => ['view erp modules', 'create erp modules', 'edit erp modules', 'delete erp modules'],
        'Request Type' => ['view it request types', 'create it request types', 'edit it request types', 'delete it request types'],
    ],

    'Research & Development' => [
        'Bill of Material' => [
            'view bill of materials',
            'create bill of materials',
            'edit bill of materials',
        ],
        'Project' => [
            'view rnd projects',
            'create rnd projects',
            'edit rnd projects',
            'delete rnd projects',
        ],
        'Product Price Index' => [
            'view product price index',
            'sync product price index',
        ],
    ],

    'Management Access' => [
        'Pengguna' => ['view users', 'create users', 'edit users', 'delete users'],
        'Role & Permission' => ['view roles', 'create roles', 'edit roles', 'delete roles'],
    ],

    'Analytics' => [
        'Sales Information' => ['view sales information'],
        'Promotion Information' => ['view promotion information'],
        'Stock Information' => ['view stock information'],
    ],

    'Master' => [
        'Employee' => ['view employees', 'create employees', 'edit employees', 'delete employees'],
        'Brand' => ['view brands', 'create brands', 'edit brands', 'delete brands'],
        'Cabang' => ['view branches', 'create branches', 'edit branches', 'delete branches'],
    ],

];
