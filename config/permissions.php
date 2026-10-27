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
        // Separate from the tile/page-access permission above: docs/daily-briefing-remediation-prd.md
        // §7/§10.1 keeps "can see the page" and "can submit" as independent permissions so a role
        // can have one without the other.
        'Submit Daily Briefing' => ['submit daily briefing'],
        'Sales Report' => ['access employee app sales report'],
        'Stock Card' => ['access employee app stock card'],
        'Penerimaan Barang' => ['access employee app goods receipt'],
        'Purchasing' => ['access employee app purchasing'],
        'Desain' => ['access employee app design'],
        'Konten' => ['access employee app content'],
        'ERP' => ['access employee app erp'],
        'Quality Control' => ['access employee app quality control'],
        'SOP Store' => ['access employee app store sop'],
        // Deliberately the same permission used by CustomerComplaintPolicy::create() and the
        // back office (not a separate "access employee app ..." flag): the PRD requires the
        // Form Komplain tile to be gated by exactly one permission, `create customer complaints`
        // (docs/customer-complaints-prd.md §12), and User::canAccessPanel('casual') only checks
        // membership in this config group.
        'Customer Complaints' => ['create customer complaints'],
        // Same single-permission-gates-everything pattern as Customer Complaints (docs/
        // store-sales-order-prd.md §16): `create store sales orders` is both the Casual panel
        // gate and the tile/submission permission.
        'Store Sales Order' => ['create store sales orders'],
    ],

    'Human Resources' => [
        'Casual Staff' => ['view casual staff', 'create casual staff', 'edit casual staff', 'delete casual staff'],
        'Posisi Casual' => ['view casual positions', 'create casual positions', 'edit casual positions', 'delete casual positions'],
        'Lowongan Posisi' => ['view casual openings', 'create casual openings', 'edit casual openings', 'delete casual openings'],
        'Absensi Casual' => ['view clock records', 'create clock records', 'edit clock records', 'delete clock records'],
        'Daily Briefing' => ['view briefing records', 'create briefing records', 'edit briefing records', 'delete briefing records'],
        'Monitoring Poin' => ['view briefing items', 'create briefing items', 'edit briefing items', 'delete briefing items'],
        // docs/daily-briefing-remediation-prd.md §10.2/§12.3: review (approve/reject/reopen) is
        // gated by this dedicated permission, never by `review sales reports as supervisor`.
        'Review Monitoring Poin' => ['review briefing items'],
        'Nilai Briefing' => ['view briefing scores', 'create briefing scores', 'edit briefing scores', 'delete briefing scores'],
        'Pengaturan Briefing' => ['edit briefing settings'],
        'Bobot Penilaian Briefing' => ['edit briefing period weights'],
    ],

    'Driver' => [
        'Uang Makan Driver' => [
            'view driver meal allowance periods',
            'create driver meal allowance periods',
            'edit driver meal allowance periods',
            'finalize driver meal allowance periods',
            'reopen driver meal allowance periods',
            'export driver meal allowance periods',
        ],
        'Perjalanan' => ['view trips', 'create trips', 'edit trips', 'delete trips'],
        'Rute Perjalanan' => ['view trip routes', 'create trip routes', 'edit trip routes', 'delete trip routes'],
        'Kendaraan' => ['view vehicles', 'create vehicles', 'edit vehicles', 'delete vehicles'],
    ],

    'Teknisi' => [
        'Service Request' => ['view service requests', 'create service requests', 'edit service requests', 'delete service requests'],
        'Asset QR' => ['view assets', 'create assets', 'edit assets', 'delete assets', 'generate asset qr codes', 'print asset labels'],
        'Maintenance Bulanan' => ['view technician monthly maintenance', 'create technician monthly maintenance', 'edit technician monthly maintenance', 'delete technician monthly maintenance'],
    ],

    'Inventory' => [
        'Stock Card Settings' => ['view stock card settings', 'edit stock card settings'],
        'Stock Card' => ['view stock cards', 'create stock cards', 'edit stock cards', 'delete stock cards', 'review stock cards as supervisor', 'review stock cards as finance'],
        'Penerimaan' => ['view goods receipts'],
        'Product List' => ['view product list', 'edit product list'],
        'Proses Material Marketing' => ['process marketing material as inventory'],
    ],

    'Quality Control' => [
        'Item Journal' => ['view quality control item journals', 'view all quality control item journals', 'create quality control item journals', 'submit quality control item journals', 'delete quality control item journal attachments'],
    ],

    'Finance' => [
        'Sales Report Settings' => ['edit sales report settings'],
        'Sales Report Scores' => ['view sales report scores', 'edit sales report assessment settings'],
        'Sales Report' => [
            'view sales reports', 'create sales reports', 'edit sales reports', 'delete sales reports',
            'review sales reports as supervisor', 'review sales reports as finance',
            'input sales settlements',
        ],
        'Compliment Types' => ['view compliment types', 'create compliment types', 'edit compliment types', 'delete compliment types'],
        'Basket Size' => ['view basket sizes', 'recalculate basket sizes', 'export basket sizes'],
        'Sourcing Bahan' => ['review material sourcing as finance'],
        'Product Pricelist' => ['view material pricelist', 'manage material pricelist'],
    ],

    'Purchasing' => [
        'Vendor Compliance' => ['view vendor compliance incidents', 'edit vendor compliance incidents'],
        'Permintaan Pembelian' => ['view purchase requests', 'create purchase requests', 'edit purchase requests', 'delete purchase requests'],
        'Sourcing Bahan' => ['view material sourcings', 'submit material sourcing', 'hand over material samples'],
        'Proses Material Marketing' => ['view marketing material fulfillments', 'process marketing material as purchasing'],
    ],

    'Brand Marketing' => [
        'Permintaan Design' => ['view design requests', 'create design requests', 'edit design requests', 'delete design requests'],
        'Kategori Design' => ['view design categories', 'create design categories', 'edit design categories', 'delete design categories'],
        'Permintaan Konten' => ['view content requests', 'create content requests', 'edit content requests', 'delete content requests'],
        'Marketing Material' => ['upload marketing materials'],
    ],

    'Information Technology' => [
        'Permintaan ERP' => ['view erp requests', 'create erp requests', 'edit erp requests', 'delete erp requests'],
        'Modul ERP' => ['view erp modules', 'create erp modules', 'edit erp modules', 'delete erp modules'],
        'Request Type' => ['view it request types', 'create it request types', 'edit it request types', 'delete it request types'],
        'Bulk Data' => ['view bulk product submissions', 'create bulk product submissions', 'edit bulk product submissions'],
    ],

    'Research & Development' => [
        'Bill of Material' => [
            'view bill of materials',
            'create bill of materials',
            'edit bill of materials',
            'add existing bill of materials',
            'export kitchen bill of materials',
            'export store bill of materials',
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
        'Sourcing Bahan' => ['review material sourcing as rnd'],
        'Memo Internal' => [
            'view any rnd internal memo',
            'view rnd internal memo',
            'create rnd internal memo',
            'update rnd internal memo',
            'sync rnd internal memo',
            'finalize rnd internal memo',
            'create rnd internal memo revision',
            'generate rnd internal memo pdf',
            'download rnd internal memo pdf',
            'archive rnd internal memo',
            'delete rnd internal memo',
        ],
        // docs/material-sourcing-lifecycle-prd.md §20: Master Bahan workflow. R&D creates and
        // edits materials with `edit rnd projects`; every other step has its own permission.
        'Master Bahan Workflow' => [
            'submit rnd material review',
            'review rnd materials as it',
            'edit rnd material technical data',
            'approve rnd material esb push',
            'reconcile rnd material esb',
            'receive material samples',
            'review material samples as rnd',
            'reopen material sample approval',
            'retry material notifications',
            'view material workflow audit',
        ],
        'Recipe Adjustment' => [
            'view bom adjustment history',
            'reconcile bom adjustments',
        ],
        'Shelf Life WIP' => [
            'view wip shelf life',
            'manage wip shelf life',
        ],
        'Material Forecast' => [
            'view material forecast',
            'edit material forecast',
        ],
        'Task Calendar' => [
            'view rnd project tasks',
            'create rnd project tasks',
            'update rnd project tasks',
            'assign rnd project tasks',
            'respond rnd project tasks',
            'review rnd project task follow ups',
            'cancel rnd project tasks',
            'view all branch rnd project tasks',
            'copy rnd project tasks',
        ],
        'Task Template' => [
            'view rnd project task templates',
            'manage rnd project task templates',
            'apply rnd project task templates',
        ],
    ],

    'Kitchen' => [
        'Jadwal Produksi' => [
            'view kitchen production schedules',
            'view all branch kitchen production schedules',
            'create kitchen production schedules',
            'update kitchen production schedules',
            'cancel kitchen production schedules',
        ],
    ],

    'Sales & Growth' => [
        'Sales Projection' => ['view sales projections'],
    ],

    'Operational' => [
        'SOP Kategori' => [
            'view store sop categories',
            'create store sop categories',
            'edit store sop categories',
            'delete store sop categories',
        ],
        'SOP Store' => [
            'view store sops',
            'create store sops',
            'edit store sops',
            'delete store sops',
            'publish store sops',
            'view store sop reports',
        ],
        'Customer Complaints' => [
            'view any customer complaints',
            'view customer complaints',
            'update customer complaints',
            'delete customer complaints',
        ],
        'Store Sales Orders' => [
            'view any store sales orders',
            'view store sales orders',
            'update store sales orders',
            'update store sales order status',
            'delete store sales orders',
        ],
    ],

    // docs/system-monitoring-prd.md §7: every monitoring Page, query, record, and action checks
    // these server-side; Management roles should only receive `view system monitoring`.
    'System Monitoring' => [
        'Monitoring' => [
            'view system monitoring',
            'view monitoring technical details',
            'view monitoring sanitized payloads',
            'manage monitoring incidents',
            'acknowledge monitoring alerts',
            'reconcile esb transactions',
            'retry safe esb transactions',
            'export monitoring reports',
            'manage monitoring settings',
        ],
    ],

    'Management Access' => [
        'Pengguna' => ['view users', 'create users', 'edit users', 'delete users'],
        'Role & Permission' => ['view roles', 'create roles', 'edit roles', 'delete roles'],
    ],

    'Analytics' => [
        'Sales Information' => ['view sales information'],
        'Ordering Recommendation' => ['view ordering recommendations', 'export ordering recommendations'],
        'Promotion Information' => ['view promotion information'],
        'Stock Information' => ['view stock information'],
    ],

    'Master' => [
        'Employee' => ['view employees', 'create employees', 'edit employees', 'delete employees'],
        'Brand' => ['view brands', 'create brands', 'edit brands', 'delete brands'],
        'Cabang' => ['view branches', 'create branches', 'edit branches', 'delete branches'],
        'Shift Basket Size' => ['edit branch shifts'],
        'WhatsApp' => ['edit whatsapp settings'],
    ],

];
