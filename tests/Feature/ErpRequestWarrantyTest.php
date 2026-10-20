<?php

use App\Actions\ClaimErpRequestWarrantyAction;
use App\Actions\UpdateErpRequestStatusAction;
use App\Enums\ItRequestStatus;
use App\Filament\Casual\Pages\ErpRequestHistoryPage;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\ViewErpRepairRequest;
use App\Models\ErpRepairRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('moves completed IT work into a three-business-day Warranty period', function () {
    $this->travelTo('2026-10-16 10:00:00');
    $actor = User::factory()->create();
    $request = ErpRepairRequest::factory()->create(['status' => ItRequestStatus::Progress, 'submitted_at' => '2026-10-16 08:00:00']);

    app(UpdateErpRequestStatusAction::class)->execute($request, ItRequestStatus::Warranty, 'Pekerjaan selesai.', $actor);

    expect($request->refresh()->status)->toBe(ItRequestStatus::Warranty)
        ->and($request->warranty_started_at->format('Y-m-d H:i:s'))->toBe('2026-10-16 10:00:00')
        ->and($request->warranty_expires_at->format('Y-m-d H:i:s'))->toBe('2026-10-21 10:00:00')
        ->and($request->resolution_business_seconds)->toBe(7200);
});

it('lets only the requester claim an active Warranty and starts a fresh SLA cycle', function () {
    $this->travelTo('2026-10-19 09:00:00');
    $requester = User::factory()->create();
    $request = ErpRepairRequest::factory()->create([
        'requester_id' => $requester->id, 'status' => ItRequestStatus::Warranty,
        'submitted_at' => '2026-10-16 08:00:00', 'first_responded_at' => '2026-10-16 09:00:00',
        'response_business_seconds' => 3600, 'resolved_at' => '2026-10-16 10:00:00',
        'resolution_business_seconds' => 7200, 'warranty_started_at' => '2026-10-16 10:00:00',
        'warranty_expires_at' => '2026-10-21 10:00:00',
    ]);

    expect(fn () => app(ClaimErpRequestWarrantyAction::class)->execute($request, User::factory()->create(), 'Masih error.'))
        ->toThrow(AuthorizationException::class);

    app(ClaimErpRequestWarrantyAction::class)->execute($request, $requester, 'Masih error saat simpan data.');

    expect($request->refresh()->status)->toBe(ItRequestStatus::Submitted)
        ->and($request->submitted_at->format('Y-m-d H:i:s'))->toBe('2026-10-19 09:00:00')
        ->and($request->first_responded_at)->toBeNull()
        ->and($request->resolved_at)->toBeNull()
        ->and($request->warranty_expires_at)->toBeNull()
        ->and($request->activities()->latest()->first()->action)->toBe('warranty_claimed');
});

it('rejects blank or expired Warranty claims', function () {
    $requester = User::factory()->create();
    $request = ErpRepairRequest::factory()->create(['requester_id' => $requester->id, 'status' => ItRequestStatus::Warranty, 'warranty_expires_at' => now()->addDay()]);

    expect(fn () => app(ClaimErpRequestWarrantyAction::class)->execute($request, $requester, ' '))
        ->toThrow(ValidationException::class, 'Alasan klaim Warranty wajib diisi.');

    $request->update(['warranty_expires_at' => now()->subMinute()]);
    expect(fn () => app(ClaimErpRequestWarrantyAction::class)->execute($request, $requester, 'Masih error.'))
        ->toThrow(ValidationException::class, 'Masa Warranty tiket sudah berakhir.');
});

it('auto-completes only expired Warranty tickets and records the event', function () {
    User::factory()->create();
    $expired = ErpRepairRequest::factory()->create(['status' => ItRequestStatus::Warranty, 'warranty_expires_at' => now()->subMinute()]);
    $active = ErpRepairRequest::factory()->create(['status' => ItRequestStatus::Warranty, 'warranty_expires_at' => now()->addDay()]);

    $this->artisan('erp-requests:complete-warranty')->expectsOutput('Completed 1 IT request(s) after Warranty expired.')->assertSuccessful();

    expect($expired->refresh()->status)->toBe(ItRequestStatus::Completed)
        ->and($expired->activities()->latest()->first()->action)->toBe('warranty_auto_completed')
        ->and($active->refresh()->status)->toBe(ItRequestStatus::Warranty);
});

it('shows warranty complaints and activity logs to the requester and back office', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $requester = User::factory()->create(['is_active' => true]);
    $requester->assignRole('STORE_STAFF');
    $request = ErpRepairRequest::factory()->create([
        'requester_id' => $requester->id,
        'status' => ItRequestStatus::Warranty,
        'warranty_expires_at' => now()->addDay(),
    ]);

    app(ClaimErpRequestWarrantyAction::class)->execute(
        $request,
        $requester,
        'Error yang sama muncul kembali setelah perbaikan.',
    );

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($requester);
    Livewire::test(ErpRequestHistoryPage::class)
        ->call('toggleItem', $request->id)
        ->assertSee('Keluhan Setelah Warranty')
        ->assertSee('Error yang sama muncul kembali setelah perbaikan.')
        ->assertSee('Log Aktivitas');

    $admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $admin->assignRole('SUPERADMIN');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($admin);
    Livewire::test(ViewErpRepairRequest::class, ['record' => $request->id])
        ->assertSee('Riwayat Permintaan')
        ->assertSee('Keluhan Setelah Warranty')
        ->assertSee('Error yang sama muncul kembali setelah perbaikan.')
        ->assertSee('Log Aktivitas');
});
