<?php

use App\Filament\Casual\Pages\DesignRequestPage;
use App\Filament\Casual\Pages\ErpRequestPage;
use App\Filament\Casual\Pages\PurchaseRequestPage;
use App\Filament\Casual\Pages\TechnicianRequestPage;
use App\Models\Branch;
use App\Models\DesignCategory;
use App\Models\DesignRequest;
use App\Models\ErpModule;
use App\Models\ErpRepairRequest;
use App\Models\ItRequestType;
use App\Models\PurchaseRequest;
use App\Models\ServiceRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->user);
});

it('generates a random DS- code for a design request and shows it on the success card', function () {
    $category = DesignCategory::create(['name' => 'Social Media', 'sort_order' => 1, 'is_active' => true]);

    $page = Livewire::test(DesignRequestPage::class)
        ->set('judulPermintaan', 'Banner Promo Agustus')
        ->set('designCategoryId', (string) $category->id)
        ->set('ringkasanBrief', 'Banner untuk promo bulan Agustus.')
        ->call('submit')
        ->assertHasNoErrors();

    $request = DesignRequest::firstOrFail();

    expect($request->code)->toMatch('/^DS-\d{6}$/');
    $page->assertSet('requestCode', $request->code)->assertSee($request->code);
});

it('shows the existing IT- ticket number for an ERP request on the success card', function () {
    $module = ErpModule::create(['name' => 'Sales', 'sort_order' => 1, 'is_active' => true]);
    $type = ItRequestType::where('name', 'Ticketing')->firstOrFail();

    $page = Livewire::test(ErpRequestPage::class)
        ->set('erpModuleId', (string) $module->id)
        ->set('requestTypeId', (string) $type->id)
        ->set('keterangan', 'Sales report tidak dapat dibuka.')
        ->call('submit')
        ->assertHasNoErrors();

    $request = ErpRepairRequest::firstOrFail();

    expect($request->ticket_number)->toMatch('/^IT-\d{6}$/');
    $page->assertSet('requestCode', $request->ticket_number)->assertSee($request->ticket_number);
});

it('generates a random PR- code for a purchase request and shows it on the success card', function () {
    $page = Livewire::test(PurchaseRequestPage::class)
        ->set('itemName', 'Laptop ASUS VivoBook')
        ->set('quantity', 1)
        ->set('purchaseReason', 'Laptop lama rusak.')
        ->set('purchaseType', 'new')
        ->call('submit')
        ->assertHasNoErrors();

    $request = PurchaseRequest::firstOrFail();

    expect($request->code)->toMatch('/^PR-\d{6}$/');
    $page->assertSet('requestCode', $request->code)->assertSee($request->code);
});

it('generates a random SR- code for a service request and shows it on the success card', function () {
    $page = Livewire::test(TechnicianRequestPage::class)
        ->set('scheduledDate', now()->addDay()->toDateString())
        ->set('requestorNotes', 'AC bocor di ruang meeting.')
        ->call('submit')
        ->assertHasNoErrors();

    $request = ServiceRequest::firstOrFail();

    expect($request->code)->toMatch('/^SR-\d{6}$/');
    $page->assertSet('requestCode', $request->code)->assertSee($request->code);
});
