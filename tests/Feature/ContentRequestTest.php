<?php

use App\Enums\ContentRequestStatus;
use App\Filament\Casual\Pages\ContentRequestPage;
use App\Filament\Helpdesk\Resources\ContentRequests\Pages\ViewContentRequest;
use App\Models\Branch;
use App\Models\ContentRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('SUPERADMIN');
});

it('creates a content request from the casual app', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->user);

    Livewire::test(ContentRequestPage::class)
        ->set('judulKonten', 'Video Promo Menu Baru')
        ->set('jenisKonten', 'video')
        ->set('platformTujuan', 'Instagram')
        ->set('tujuanKonten', 'Promo menu baru untuk bulan ini.')
        ->set('linkContohKonten', 'https://instagram.com/reel/example')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $request = ContentRequest::firstOrFail();

    expect($request->judul_konten)->toBe('Video Promo Menu Baru')
        ->and($request->jenis_konten)->toBe('video')
        ->and($request->platform_tujuan)->toBe('Instagram')
        ->and($request->status)->toBe(ContentRequestStatus::Submitted)
        ->and($request->code)->toMatch('/^CR-\d{6}$/')
        ->and($request->statusHistories()->count())->toBe(1);
});

it('shows the generated request code on the success card after submitting', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->user);

    $page = Livewire::test(ContentRequestPage::class)
        ->set('judulKonten', 'Video Promo Menu Baru')
        ->set('jenisKonten', 'video')
        ->set('platformTujuan', 'Instagram')
        ->set('tujuanKonten', 'Promo menu baru untuk bulan ini.')
        ->call('submit')
        ->assertHasNoErrors();

    $request = ContentRequest::firstOrFail();

    $page->assertSet('requestCode', $request->code)
        ->assertSee($request->code);
});

it('renders the content request desktop detail workspace and walks the sequential flow', function () {
    $request = ContentRequest::create([
        'requester_id' => $this->admin->id,
        'branch_id' => $this->branch->id,
        'judul_konten' => 'Foto Produk Katalog',
        'jenis_konten' => 'photo',
        'platform_tujuan' => 'Website',
        'tujuan_konten' => 'Foto produk untuk katalog online.',
        'status' => ContentRequestStatus::Submitted,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($this->admin);

    $page = Livewire::test(ViewContentRequest::class, ['record' => $request->id])
        ->assertSee('Foto Produk Katalog')
        ->assertSee('In Progress');

    foreach ([ContentRequestStatus::InProgress, ContentRequestStatus::Approval, ContentRequestStatus::Completed] as $nextStatus) {
        $page->call('transitionTo', $nextStatus->value)->assertHasNoErrors();
        expect($request->refresh()->status)->toBe($nextStatus);
    }

    expect($request->statusHistories()->count())->toBe(4);
});

it('requires a reason when rejecting a content request at the approval step', function () {
    $request = ContentRequest::create([
        'requester_id' => $this->admin->id,
        'branch_id' => $this->branch->id,
        'judul_konten' => 'Foto Produk Katalog',
        'jenis_konten' => 'photo',
        'platform_tujuan' => 'Website',
        'tujuan_konten' => 'Foto produk untuk katalog online.',
        'status' => ContentRequestStatus::Approval,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($this->admin);

    Livewire::test(ViewContentRequest::class, ['record' => $request->id])
        ->set('adminNotes', '')
        ->call('transitionTo', ContentRequestStatus::Rejected->value)
        ->assertHasErrors(['adminNotes']);

    expect($request->refresh()->status)->toBe(ContentRequestStatus::Approval);
});

it('shows the Permintaan Konten menu in the Helpdesk sidebar under Brand Marketing', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($this->admin);

    $this->get(route('filament.helpdesk.resources.content-requests.index'))
        ->assertOk()
        ->assertSee('Permintaan Konten')
        ->assertSee('openGroups: ["brand-marketing"]', false);
});
