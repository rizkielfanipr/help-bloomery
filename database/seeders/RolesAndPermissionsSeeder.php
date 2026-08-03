<?php

namespace Database\Seeders;

use App\Services\PermissionSynchronizer;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionSynchronizer::class)->sync();

        Role::firstOrCreate(['name' => 'SUPERADMIN', 'guard_name' => 'web'])
            ->syncPermissions(Permission::all());

        Role::firstOrCreate(['name' => 'CASUAL_STAFF', 'guard_name' => 'web'])
            ->syncPermissions([
                'access employee app attendance',
                'view clock records',
                'view overtime requests', 'create overtime requests',
            ]);

        Role::firstOrCreate(['name' => 'HRD_STAFF', 'guard_name' => 'web'])
            ->syncPermissions([
                'access backoffice',
                'access employee app attendance', 'access employee app briefing',
                'access employee app sales report', 'access employee app stock card',
                'access employee app purchasing', 'access employee app design', 'access employee app erp',
                'view casual staff', 'create casual staff', 'edit casual staff', 'delete casual staff',
                'view casual positions', 'create casual positions', 'edit casual positions', 'delete casual positions',
                'view casual openings', 'create casual openings', 'edit casual openings', 'delete casual openings',
                'view clock records', 'create clock records', 'edit clock records', 'delete clock records',
                'view briefing records', 'create briefing records', 'edit briefing records', 'delete briefing records',
                'view briefing items', 'create briefing items', 'edit briefing items', 'delete briefing items',
                'view briefing scores', 'create briefing scores', 'edit briefing scores', 'delete briefing scores',
                'view sales reports',
                'view stock cards',
                'view purchase requests', 'create purchase requests', 'edit purchase requests',
                'view design requests', 'edit design requests',
                'view users',
                'view brands', 'create brands', 'edit brands', 'delete brands',
                'view branches', 'create branches', 'edit branches', 'delete branches',
                'view employees', 'create employees', 'edit employees', 'delete employees',
                // analytics
                'view sales information',
                'view promotion information',
                'view stock information',
            ]);

        Role::firstOrCreate(['name' => 'STORE_STAFF', 'guard_name' => 'web'])
            ->syncPermissions([
                'access backoffice',
                'access employee app technician request', 'access employee app briefing',
                'access employee app sales report', 'access employee app stock card',
                'access employee app purchasing', 'access employee app design', 'access employee app erp',
                'view service requests', 'create service requests',
                'view sales reports',
                'view stock cards', 'create stock cards', 'edit stock cards',
                'view purchase requests', 'create purchase requests',
                'view design requests', 'create design requests',
                'view erp requests', 'create erp requests',
            ]);

        Role::firstOrCreate(['name' => 'DRIVER', 'guard_name' => 'web'])
            ->syncPermissions([
                'access backoffice', 'access employee app driver',
                'view trips', 'view trip routes', 'view vehicles',
                'view fuel types',
            ]);

        Role::firstOrCreate(['name' => 'TECHNICIAN', 'guard_name' => 'web'])
            ->syncPermissions([
                'access backoffice', 'access employee app technician',
                'view service requests', 'create service requests', 'edit service requests', 'delete service requests',
            ]);

        Role::firstOrCreate(['name' => 'IT_STAFF', 'guard_name' => 'web'])
            ->syncPermissions([
                'access backoffice',
                'view erp requests', 'create erp requests', 'edit erp requests',
                'view erp modules',
                'view it request types', 'create it request types', 'edit it request types',
            ]);

        Role::firstOrCreate(['name' => 'RND_STAFF', 'guard_name' => 'web'])
            ->syncPermissions([
                'access backoffice',
                'view bill of materials', 'create bill of materials', 'edit bill of materials',
                'view rnd projects', 'create rnd projects', 'edit rnd projects', 'delete rnd projects',
                'view product price index', 'sync product price index',
            ]);

        Role::firstOrCreate(['name' => 'SUPERVISOR_STORE', 'guard_name' => 'web'])
            ->syncPermissions([
                'access backoffice', 'access employee app briefing', 'access employee app sales report',
                'view briefing items', 'edit briefing items',
                'view sales reports', 'review sales reports as supervisor',
                'view employees', 'create employees', 'edit employees', 'delete employees',
            ]);

        Role::firstOrCreate(['name' => 'FINANCE_STAFF', 'guard_name' => 'web'])
            ->syncPermissions([
                'access backoffice',
                'view sales reports', 'review sales reports as finance',
                'input sales settlements',
            ]);

        $this->command->info('Roles seeded: SUPERADMIN, CASUAL_STAFF, HRD_STAFF, STORE_STAFF, DRIVER, TECHNICIAN, IT_STAFF, RND_STAFF, SUPERVISOR_STORE, FINANCE_STAFF');
    }
}
