<?php

use App\Filament\Helpdesk\Resources\VendorComplianceIncidents\Pages\EditVendorComplianceIncident;
use App\Filament\Helpdesk\Resources\VendorComplianceIncidents\Pages\ListVendorComplianceIncidents;
use App\Filament\Helpdesk\Resources\VendorComplianceIncidents\VendorComplianceIncidentResource;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\User;
use App\Models\VendorComplianceIncident;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    Permission::findOrCreate('access backoffice');
    Permission::findOrCreate('view vendor compliance incidents');
    Permission::findOrCreate('edit vendor compliance incidents');
    $this->purchasing = User::factory()->create(['is_active' => true]);
    $this->purchasing->givePermissionTo(['access backoffice', 'view vendor compliance incidents', 'edit vendor compliance incidents']);
    $this->actingAs($this->purchasing);

    $receipt = GoodsReceipt::factory()->create(['reference_number' => 'PO-VC-001', 'supplier_name' => 'Vendor Uji']);
    $item = GoodsReceiptItem::factory()->for($receipt)->create(['product_name' => 'Susu Segar', 'quarantine_location' => 'Area Q-01']);
    $this->incident = VendorComplianceIncident::factory()->create([
        'goods_receipt_id' => $receipt->id, 'goods_receipt_item_id' => $item->id,
        'supplier_name' => 'Vendor Uji', 'category' => 'cold_chain',
        'demerit_points' => 20, 'status' => 'open',
    ]);
});

it('shows vendor incidents in the single purchasing compliance menu', function () {
    expect(VendorComplianceIncidentResource::getUrl())->toContain('vendor-compliance-incidents');

    Livewire::test(ListVendorComplianceIncidents::class)
        ->assertCanSeeTableRecords([$this->incident])
        ->assertSee('Vendor Uji')
        ->assertSee('Susu Segar')
        ->assertSee('Cold Chain');
});

it('records the purchasing follow up owner and resolution time', function () {
    Livewire::test(EditVendorComplianceIncident::class, ['record' => $this->incident->id])
        ->fillForm(['status' => 'resolved', 'action_type' => 'return', 'follow_up_notes' => 'Retur disetujui vendor.'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->incident->refresh())
        ->status->toBe('resolved')
        ->action_type->toBe('return')
        ->follow_up_notes->toBe('Retur disetujui vendor.')
        ->handled_by->toBe($this->purchasing->id)
        ->resolved_at->not->toBeNull();
});
