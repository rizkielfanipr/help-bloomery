<?php

use App\Filament\Casual\Pages\ContentRequestPage;
use App\Filament\Casual\Pages\DesignRequestPage;
use App\Filament\Casual\Pages\ErpRequestPage;
use App\Filament\Casual\Pages\PurchaseRequestPage;
use App\Filament\Casual\Pages\TechnicianRequestPage;
use App\Filament\Helpdesk\Pages\WhatsappSettingsPage;
use App\Models\Branch;
use App\Models\DesignCategory;
use App\Models\ErpModule;
use App\Models\ItRequestType;
use App\Models\User;
use App\Models\WhatsappNotificationSetting;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->pic = User::factory()->create(['phone' => '081234567890', 'is_active' => true]);
    $this->user = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
});

it('does not show the WhatsApp CTA when the module is disabled', function () {
    $category = DesignCategory::create(['name' => 'Social Media', 'sort_order' => 1, 'is_active' => true]);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->user);

    $page = Livewire::test(DesignRequestPage::class)
        ->set('judulPermintaan', 'Banner Promo Agustus')
        ->set('designCategoryId', (string) $category->id)
        ->set('ringkasanBrief', 'Banner untuk promo bulan Agustus.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true)
        ->assertSet('whatsappUrl', null);

    expect($page->get('whatsappUrl'))->toBeNull();
});

it('shows the WhatsApp CTA for a design request when enabled with a PIC', function () {
    $category = DesignCategory::create(['name' => 'Social Media', 'sort_order' => 1, 'is_active' => true]);
    WhatsappNotificationSetting::forModule('design_request')->update([
        'is_enabled' => true,
        'pic_user_id' => $this->pic->id,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->user);

    $page = Livewire::test(DesignRequestPage::class)
        ->set('judulPermintaan', 'Banner Promo Agustus')
        ->set('designCategoryId', (string) $category->id)
        ->set('ringkasanBrief', 'Banner untuk promo bulan Agustus.')
        ->call('submit')
        ->assertHasNoErrors();

    $whatsappUrl = urldecode($page->get('whatsappUrl'));

    expect($whatsappUrl)->toStartWith('https://wa.me/6281234567890?text=')
        ->and($whatsappUrl)->toContain('Banner Promo Agustus')
        ->and($whatsappUrl)->toContain('Social Media');
});

it('shows the WhatsApp CTA for a content request when enabled with a PIC', function () {
    WhatsappNotificationSetting::forModule('content_request')->update([
        'is_enabled' => true,
        'pic_user_id' => $this->pic->id,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->user);

    $page = Livewire::test(ContentRequestPage::class)
        ->set('judulKonten', 'Video Promo Menu Baru')
        ->set('jenisKonten', 'video')
        ->set('platformTujuan', 'Instagram')
        ->set('tujuanKonten', 'Promo menu baru untuk bulan ini.')
        ->call('submit')
        ->assertHasNoErrors();

    $whatsappUrl = urldecode($page->get('whatsappUrl'));

    expect($whatsappUrl)->toStartWith('https://wa.me/6281234567890?text=')
        ->and($whatsappUrl)->toContain('Video Promo Menu Baru')
        ->and($whatsappUrl)->toContain('Instagram');
});

it('shows the WhatsApp CTA for an ERP request when enabled with a PIC', function () {
    $module = ErpModule::create(['name' => 'Sales', 'sort_order' => 1, 'is_active' => true]);
    $type = ItRequestType::where('name', 'Ticketing')->firstOrFail();
    WhatsappNotificationSetting::forModule('erp_request')->update([
        'is_enabled' => true,
        'pic_user_id' => $this->pic->id,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->user);

    $page = Livewire::test(ErpRequestPage::class)
        ->set('erpModuleId', (string) $module->id)
        ->set('requestTypeId', (string) $type->id)
        ->set('keterangan', 'Sales report tidak dapat dibuka.')
        ->call('submit')
        ->assertHasNoErrors();

    $whatsappUrl = urldecode($page->get('whatsappUrl'));

    expect($whatsappUrl)->toStartWith('https://wa.me/6281234567890?text=')
        ->and($whatsappUrl)->toContain('Sales report tidak dapat dibuka.')
        ->and($whatsappUrl)->toContain('Sales');
});

it('shows the WhatsApp CTA for a purchase request when enabled with a PIC', function () {
    WhatsappNotificationSetting::forModule('purchase_request')->update([
        'is_enabled' => true,
        'pic_user_id' => $this->pic->id,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->user);

    $page = Livewire::test(PurchaseRequestPage::class)
        ->set('itemName', 'Laptop ASUS VivoBook')
        ->set('quantity', 1)
        ->set('purchaseReason', 'Laptop lama rusak.')
        ->set('purchaseType', 'new')
        ->call('submit')
        ->assertHasNoErrors();

    $whatsappUrl = urldecode($page->get('whatsappUrl'));

    expect($whatsappUrl)->toStartWith('https://wa.me/6281234567890?text=')
        ->and($whatsappUrl)->toContain('Laptop ASUS VivoBook');
});

it('shows the WhatsApp CTA for a service request when enabled with a PIC, without auto-redirecting', function () {
    WhatsappNotificationSetting::forModule('service_request')->update([
        'is_enabled' => true,
        'pic_user_id' => $this->pic->id,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->user);

    $page = Livewire::test(TechnicianRequestPage::class)
        ->set('scheduledDate', now()->addDay()->toDateString())
        ->set('requestorNotes', 'AC bocor di ruang meeting.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true)
        ->assertNoRedirect();

    $whatsappUrl = urldecode($page->get('whatsappUrl'));

    expect($whatsappUrl)->toStartWith('https://wa.me/6281234567890?text=')
        ->and($whatsappUrl)->toContain('AC bocor di ruang meeting.');
});

it('lets an admin configure all WhatsApp modules from the Master CMS page', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($admin);

    Livewire::test(WhatsappSettingsPage::class)
        ->fillForm([
            'settings' => [
                'design_request' => [
                    'is_enabled' => true,
                    'pic_user_id' => $this->pic->id,
                    'message_template' => 'Design baru: {judul} dari {cabang}. {link}',
                ],
                'erp_request' => [
                    'is_enabled' => false,
                    'pic_user_id' => $this->pic->id,
                    'message_template' => 'ERP baru: {keterangan}. {link}',
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $designSetting = WhatsappNotificationSetting::forModule('design_request')->fresh();
    $erpSetting = WhatsappNotificationSetting::forModule('erp_request')->fresh();

    expect($designSetting->is_enabled)->toBeTrue()
        ->and($designSetting->pic_user_id)->toBe($this->pic->id)
        ->and($designSetting->message_template)->toBe('Design baru: {judul} dari {cabang}. {link}')
        ->and($erpSetting->is_enabled)->toBeFalse();
});

it('shows the WhatsApp menu under Master in the Helpdesk sidebar for users with the permission', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($admin);

    $this->get(route('filament.helpdesk.pages.whatsapp-settings'))
        ->assertOk()
        ->assertSee('WhatsApp')
        ->assertSee(route('filament.helpdesk.pages.whatsapp-settings'), false)
        ->assertSee('openGroups: ["master"]', false);
});
