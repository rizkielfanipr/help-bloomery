<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchEsbCode;
use App\Models\GoodsReceipt;
use App\Models\User;
use App\Services\SandboxEsbGoodsReceiptService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Local test data for Receiving with the sandbox ESB (config esb.receiving_sandbox). Re-running it
 * resets every PO-DUMMY-* receipt so the dummy Purchase Orders are fully outstanding again.
 */
class ReceivingSandboxSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('ReceivingSandboxSeeder hanya berjalan pada APP_ENV=local.');

            return;
        }

        GoodsReceipt::query()->where('reference_number', 'like', 'PO-DUMMY-%')->delete();

        $centralBranch = Branch::query()->firstOrCreate(
            ['name' => 'Sandbox Inventory Pusat'],
            ['address' => 'Sandbox Receiving lokal', 'radius_meters' => 100, 'is_active' => true],
        );
        $mappedBranch = Branch::query()->firstOrCreate(
            ['name' => 'Sandbox Cabang Termapping'],
            ['address' => 'Sandbox Receiving lokal', 'radius_meters' => 100, 'is_active' => true],
        );
        BranchEsbCode::query()->updateOrCreate(
            ['branch_id' => $mappedBranch->id, 'esb_branch_code' => 'DMY-MAP', 'esb_comcode' => SandboxEsbGoodsReceiptService::COMPANY_CODE],
            ['esb_branch_id' => SandboxEsbGoodsReceiptService::MAPPED_ESB_BRANCH_ID, 'label' => 'Sandbox Cabang Termapping', 'is_active' => true],
        );

        foreach (['access employee app goods receipt', 'access backoffice', 'view goods receipts', 'view vendor compliance incidents'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $receiver = $this->user('receiving.pusat', 'Sandbox Receiving Pusat', $centralBranch, accessAllBranches: false);
        $receiver->syncPermissions(['access employee app goods receipt']);

        $viewer = $this->user('receiving.viewer', 'Sandbox Viewer Receiving', $centralBranch, accessAllBranches: true);
        $viewer->syncPermissions(['access backoffice', 'view goods receipts', 'view vendor compliance incidents']);

        $purchasing = $this->user('receiving.purchasing', 'Sandbox Purchasing', $centralBranch, accessAllBranches: true);
        $purchasing->syncRoles([Role::findOrCreate('PURCHASING_STAFF', 'web')]);
        $purchasing->syncPermissions(['access backoffice', 'view vendor compliance incidents']);

        $history = [
            ['PO-DUMMY-HIST-UNMAPPED', 9101, null, 'Dummy Kemang', 'Kemang · Gudang Kering', 910101],
            ['PO-DUMMY-HIST-MAPPED', SandboxEsbGoodsReceiptService::MAPPED_ESB_BRANCH_ID, $mappedBranch->id, 'Dummy Cabang Termapping', 'Termapping · Gudang Utama', 910301],
        ];
        foreach ($history as [$reference, $esbBranchId, $localBranchId, $branchName, $locationName, $locationId]) {
            GoodsReceipt::factory()->create([
                'reference_number' => $reference, 'esb_goods_receipt_number' => 'GR-'.$reference,
                'goods_receipt_date' => now()->subDays(3)->toDateString(), 'purchase_date' => now()->subDays(5)->toDateString(),
                'esb_branch_id' => $esbBranchId, 'local_branch_id' => $localBranchId, 'branch_name' => $branchName,
                'supplier_id' => 98001, 'supplier_name' => 'PT Dummy Susu Segar',
                'location_id' => $locationId, 'location_name' => $locationName,
                'document_type' => 'delivery_note', 'document_number' => 'DO-'.$reference, 'document_date' => now()->subDays(3)->toDateString(),
                'qc_outcome' => 'accepted', 'submitted_by' => $receiver->id, 'submitted_at' => now()->subDays(3), 'synced_at' => now()->subDays(3),
            ]);
        }

        $this->command?->info('Receiving sandbox siap. Login (password: '.self::PASSWORD.'): receiving.pusat (Employee App), receiving.viewer dan receiving.purchasing (Back Office).');
    }

    private function user(string $username, string $name, Branch $branch, bool $accessAllBranches): User
    {
        return User::query()->updateOrCreate(['username' => $username], [
            'name' => $name,
            'email' => "{$username}@sandbox.test",
            'password' => bcrypt(self::PASSWORD),
            'is_active' => true,
            'branch_id' => $branch->id,
            'access_all_branches' => $accessAllBranches,
        ]);
    }
}
