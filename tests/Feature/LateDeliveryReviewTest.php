<?php

use App\Actions\Purchasing\VendorCompliance\ReviewLateDeliveryIncidentAction;
use App\Enums\VendorComplianceLiabilityStatus;
use App\Filament\Helpdesk\Resources\GoodsReceipts\Pages\ViewGoodsReceipt;
use App\Filament\Helpdesk\Resources\VendorComplianceIncidents\Pages\EditVendorComplianceIncident;
use App\Filament\Helpdesk\Resources\VendorComplianceIncidents\Pages\ListVendorComplianceIncidents;
use App\Filament\Helpdesk\Resources\VendorComplianceIncidents\Pages\ViewVendorComplianceIncident;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\User;
use App\Models\VendorComplianceIncident;
use App\Policies\VendorComplianceIncidentPolicy;
use App\Services\Purchasing\VendorCompliance\LateDeliveryScoring;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->reviewer = User::factory()->create(['is_active' => true]);
    $this->reviewer->givePermissionTo(['access backoffice', 'view vendor compliance incidents', 'edit vendor compliance incidents']);
    $this->viewer = User::factory()->create(['is_active' => true]);
    $this->viewer->givePermissionTo(['access backoffice', 'view vendor compliance incidents']);

    $this->receipt = GoodsReceipt::factory()->create(['company_code' => 'BLSS', 'reference_number' => 'PO-LATE-001', 'supplier_name' => 'Vendor Lambat', 'goods_receipt_date' => '2026-10-15', 'po_required_date' => '2026-10-10', 'delivery_delay_days' => 5, 'delivery_assessment_status' => 'late']);
    $this->late = VendorComplianceIncident::factory()->lateDelivery(5)->create(['goods_receipt_id' => $this->receipt->id, 'supplier_name' => 'Vendor Lambat', 'required_date' => '2026-10-10', 'latest_late_receipt_date' => '2026-10-15']);
});

function lrQcIncident(GoodsReceipt $receipt): VendorComplianceIncident
{
    return VendorComplianceIncident::factory()->create([
        'goods_receipt_id' => $receipt->id,
        'goods_receipt_item_id' => GoodsReceiptItem::factory()->for($receipt)->create(['product_name' => 'Susu Segar'])->id,
        'category' => 'packaging', 'demerit_points' => 10,
    ]);
}

it('confirms a pending candidate with the versioned matrix, reviewer, and an audit event', function (int $delay, string $severity, int $points) {
    $this->late->update(['delay_days' => $delay]);

    $confirmed = app(ReviewLateDeliveryIncidentAction::class)->confirm($this->reviewer, $this->late);

    expect($confirmed->liability_status)->toBe(VendorComplianceLiabilityStatus::Confirmed)
        ->and($confirmed->severity)->toBe($severity)
        ->and($confirmed->demerit_points)->toBe($points)
        ->and($confirmed->scoring_rule_version)->toBe(LateDeliveryScoring::VERSION)
        ->and($confirmed->liability_reviewed_by)->toBe($this->reviewer->id)
        ->and($confirmed->liability_reviewed_at)->not->toBeNull()
        ->and($confirmed->status)->toBe('open');

    $event = $confirmed->events()->sole();
    expect($event->event)->toBe('confirmed')
        ->and($event->actor_id)->toBe($this->reviewer->id)
        ->and($event->metadata['points'])->toBe($points);
})->with([[2, 'low', 5], [5, 'medium', 10], [9, 'high', 20]]);

it('waives with a required reason, 0 points, and closes the handling status', function () {
    expect(fn () => app(ReviewLateDeliveryIncidentAction::class)->waive($this->reviewer, $this->late, ' '))->toThrow(ValidationException::class);

    $waived = app(ReviewLateDeliveryIncidentAction::class)->waive($this->reviewer, $this->late, 'Tanggal dibutuhkan PO diubah setelah jadwal kirim.');

    expect($waived->liability_status)->toBe(VendorComplianceLiabilityStatus::Waived)
        ->and($waived->demerit_points)->toBe(0)
        ->and($waived->waiver_reason)->toBe('Tanggal dibutuhkan PO diubah setelah jadwal kirim.')
        ->and($waived->status)->toBe('resolved')
        ->and($waived->resolved_at)->not->toBeNull()
        ->and($waived->events()->sole()->reason)->toBe('Tanggal dibutuhkan PO diubah setelah jadwal kirim.');
});

it('keeps decisions final and refuses non Late Delivery incidents', function () {
    $review = app(ReviewLateDeliveryIncidentAction::class);
    $review->confirm($this->reviewer, $this->late);

    expect(fn () => $review->confirm($this->reviewer, $this->late))->toThrow(ValidationException::class)
        ->and(fn () => $review->waive($this->reviewer, $this->late, 'Ubah keputusan.'))->toThrow(ValidationException::class)
        ->and(fn () => $review->confirm($this->reviewer, lrQcIncident($this->receipt)))->toThrow(ValidationException::class);

    expect($this->late->refresh()->events()->count())->toBe(1);
});

it('refuses a review from a user without the edit permission', function () {
    expect(fn () => app(ReviewLateDeliveryIncidentAction::class)->confirm($this->viewer, $this->late))->toThrow(AuthorizationException::class);
    expect($this->late->refresh()->liability_status)->toBe(VendorComplianceLiabilityStatus::Pending)
        ->and($this->late->demerit_points)->toBe(0);
});

it('applies the liability review policy matrix', function () {
    $policy = app(VendorComplianceIncidentPolicy::class);
    $outsider = User::factory()->create(['is_active' => true]);
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $decided = VendorComplianceIncident::factory()->lateDelivery(2, 'PO-DONE')->create(['liability_status' => VendorComplianceLiabilityStatus::Confirmed]);
    $qc = lrQcIncident($this->receipt);

    expect($policy->reviewLiability($this->reviewer, $this->late))->toBeTrue()
        ->and($policy->reviewLiability($this->viewer, $this->late))->toBeFalse()
        ->and($policy->reviewLiability($outsider, $this->late))->toBeFalse()
        ->and($policy->reviewLiability($this->reviewer, $decided))->toBeFalse()
        ->and($policy->reviewLiability($this->reviewer, $qc))->toBeFalse()
        ->and($policy->reviewLiability($admin, $decided))->toBeFalse()
        ->and($this->viewer->can('view', $this->late))->toBeTrue()
        ->and($this->reviewer->can('create', VendorComplianceIncident::class))->toBeFalse()
        ->and($this->reviewer->can('delete', $this->late))->toBeFalse();
});

it('lists Late Delivery next to QC incidents with category and liability filters without N+1 queries', function () {
    $qc = lrQcIncident($this->receipt);
    $this->actingAs($this->viewer);

    Livewire::test(ListVendorComplianceIncidents::class)
        ->assertCanSeeTableRecords([$this->late, $qc])
        ->assertSee('Level PO')
        ->assertSee('Late Delivery')
        ->assertSee('5 hari')
        ->assertSee('Butuh 10 Oct · Terima 15 Oct')
        ->assertSee('Menunggu Verifikasi')
        ->assertSee('Susu Segar')
        ->filterTable('category', 'late_delivery')
        ->assertCanSeeTableRecords([$this->late])
        ->assertCanNotSeeTableRecords([$qc])
        ->resetTableFilters()
        ->filterTable('liability_status', 'pending')
        ->assertCanSeeTableRecords([$this->late])
        ->assertCanNotSeeTableRecords([$qc]);

    DB::enableQueryLog();
    Livewire::test(ListVendorComplianceIncidents::class);
    $few = count(DB::getQueryLog());
    foreach (range(1, 8) as $index) {
        $receipt = GoodsReceipt::factory()->create(['reference_number' => "PO-N{$index}"]);
        lrQcIncident($receipt);
        VendorComplianceIncident::factory()->lateDelivery(2, "PO-N{$index}")->create(['goods_receipt_id' => $receipt->id]);
    }
    DB::flushQueryLog();
    Livewire::test(ListVendorComplianceIncidents::class);
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual($few + 2);
    DB::disableQueryLog();
});

it('shows the delay section, receipts of the PO, and review actions only to reviewers of pending candidates', function () {
    $this->actingAs($this->viewer);
    Livewire::test(ViewVendorComplianceIncident::class, ['record' => $this->late->getKey()])
        ->assertSee('Informasi Keterlambatan')
        ->assertSee('Penerimaan PO PO-LATE-001')
        ->assertSee('(pemicu)')
        ->assertSee('Level PO')
        ->assertDontSee('Lokasi Quarantine')
        ->assertActionHidden('confirmLiability')
        ->assertActionHidden('waiveLiability');

    $this->actingAs($this->reviewer);
    Livewire::test(ViewVendorComplianceIncident::class, ['record' => $this->late->getKey()])
        ->assertActionVisible('confirmLiability')
        ->assertActionVisible('waiveLiability')
        ->mountAction('confirmLiability')
        ->assertMountedActionModalSee('10 poin demerit')
        ->callMountedAction()
        ->assertActionHidden('confirmLiability')
        ->assertActionHidden('waiveLiability');

    expect($this->late->refresh()->liability_status)->toBe(VendorComplianceLiabilityStatus::Confirmed);
});

it('requires a waiver reason in the modal', function () {
    $this->actingAs($this->reviewer);

    Livewire::test(ViewVendorComplianceIncident::class, ['record' => $this->late->getKey()])
        ->callAction('waiveLiability', ['waiver_reason' => ''])
        ->assertHasActionErrors(['waiver_reason' => 'required']);

    Livewire::test(ViewVendorComplianceIncident::class, ['record' => $this->late->getKey()])
        ->callAction('waiveLiability', ['waiver_reason' => 'Perubahan jadwal dari Purchasing.'])
        ->assertHasNoActionErrors();

    expect($this->late->refresh()->liability_status)->toBe(VendorComplianceLiabilityStatus::Waived);
});

it('hides review actions from SUPERADMIN once the candidate is decided', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->late->update(['liability_status' => VendorComplianceLiabilityStatus::Waived]);
    $this->actingAs($admin);

    Livewire::test(ViewVendorComplianceIncident::class, ['record' => $this->late->getKey()])
        ->assertActionHidden('confirmLiability')
        ->assertActionHidden('waiveLiability');
});

it('does not let the follow-up form resolve a pending candidate or touch liability fields', function () {
    $this->actingAs($this->reviewer);

    Livewire::test(EditVendorComplianceIncident::class, ['record' => $this->late->getKey()])
        ->fillForm(['status' => 'in_progress', 'follow_up_notes' => 'Menghubungi vendor.'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->late->refresh()->status)->toBe('in_progress')
        ->and($this->late->liability_status)->toBe(VendorComplianceLiabilityStatus::Pending);

    Livewire::test(EditVendorComplianceIncident::class, ['record' => $this->late->getKey()])
        ->fillForm(['status' => 'resolved'])
        ->call('save')
        ->assertHasErrors(['data.status']);

    expect($this->late->refresh()->status)->toBe('in_progress');
});

it('reports late receipts without a candidate and the review queue without writing anything', function () {
    GoodsReceipt::factory()->create(['company_code' => 'BLSS', 'reference_number' => 'PO-GAP', 'delivery_assessment_status' => 'late', 'delivery_delay_days' => 2]);
    $before = VendorComplianceIncident::query()->count();

    $this->artisan('vendor-compliance:reconcile-late-delivery')
        ->expectsTable(['Pemeriksaan', 'Jumlah'], [
            ['Goods Receipt terlambat', 2],
            ['PO terlambat tanpa kandidat Late Delivery', 1],
            ['Insiden Late Delivery tanpa source key', 0],
            ['Menunggu verifikasi', 1],
            ['Dikonfirmasi', 0],
            ['Dikecualikan', 0],
        ])
        ->assertFailed();

    expect(VendorComplianceIncident::query()->count())->toBe($before);
});

it('shows the delivery assessment on the Goods Receipt detail, with older receipts as not yet assessed', function () {
    $this->reviewer->givePermissionTo('view goods receipts');
    $this->reviewer->update(['access_all_branches' => true]);
    $this->actingAs($this->reviewer->fresh());
    $legacy = GoodsReceipt::factory()->create();

    Livewire::test(ViewGoodsReceipt::class, ['record' => $this->receipt->getRouteKey()])
        ->assertSee('Tanggal Dibutuhkan PO')
        ->assertSee('Terlambat')
        ->assertSee('5 hari');

    Livewire::test(ViewGoodsReceipt::class, ['record' => $legacy->getRouteKey()])
        ->assertSee('Belum Dinilai');
});
