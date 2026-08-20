<?php

use App\Filament\Casual\Pages\QualityControlAuditDetail;
use App\Filament\Casual\Pages\QualityControlAuditHistory;
use App\Filament\Casual\Pages\QualityControlAudits;
use App\Models\Branch;
use App\Models\QualityControlAudit;
use App\Models\User;
use Database\Seeders\QualityControlChecklistSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(QualityControlChecklistSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));

    $this->auditor = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $this->auditor->assignRole('QUALITY_CONTROL');
    $this->actingAs($this->auditor);
});

it('starts an audit from the employee app and snapshots every active checklist point', function () {
    $branch = Branch::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create(['is_active' => true]);
    $supervisor->assignRole('SUPERVISOR_STORE');

    Livewire::test(QualityControlAudits::class)
        ->call('openStartAuditModal')
        ->set('startAuditData.branch_id', $branch->id)
        ->set('startAuditData.audit_date', '2026-08-19')
        ->set('startAuditData.store_leader_present', true)
        ->set('startAuditData.store_leader_name', $supervisor->name)
        ->call('submitStartAudit');

    $audit = QualityControlAudit::query()->sole();

    expect($audit->auditor_id)->toBe($this->auditor->id)
        ->and($audit->branch_id)->toBe($branch->id)
        ->and($audit->status)->toBe('draft')
        ->and($audit->store_leader_present)->toBeTrue()
        ->and($audit->store_leader_name)->toBe($supervisor->name)
        ->and($audit->items)->toHaveCount(36)
        ->and($audit->items->sum('maximum_points'))->toBe(180);
});

it('hides and clears the store leader field when store leader is not present', function () {
    $branch = Branch::factory()->create(['is_active' => true]);

    Livewire::test(QualityControlAudits::class)
        ->call('openStartAuditModal')
        ->set('startAuditData.branch_id', $branch->id)
        ->set('startAuditData.audit_date', '2026-08-19')
        ->call('submitStartAudit');

    $audit = QualityControlAudit::query()->sole();

    expect($audit->store_leader_present)->toBeFalse()
        ->and($audit->store_leader_name)->toBeNull();
});

it('blocks starting an audit for a user without the app permission', function () {
    $otherUser = User::factory()->create(['is_active' => true]);
    $this->actingAs($otherUser);

    $this->get(route('filament.casual.pages.quality-control-audits'))
        ->assertForbidden();
});

it('fills an audit item from the app and recalculates the audit score', function () {
    $branch = Branch::factory()->create(['is_active' => true]);

    Livewire::test(QualityControlAudits::class)
        ->call('openStartAuditModal')
        ->set('startAuditData.branch_id', $branch->id)
        ->set('startAuditData.audit_date', '2026-08-19')
        ->call('submitStartAudit');

    $audit = QualityControlAudit::query()->sole();
    $item = $audit->items()->first();

    Livewire::test(QualityControlAuditDetail::class, ['record' => $audit->id])
        ->call('openItemModal', $item->id)
        ->set('itemData.result', 'pass')
        ->call('saveItem');

    $item->refresh();
    $audit->refresh();

    expect($item->result)->toBe('pass')
        ->and($item->earned_points)->toBe($item->maximum_points)
        ->and($audit->earned_points)->toBe($item->maximum_points);
});

it('captures a photo via the camera and attaches it to the item regardless of result', function () {
    Storage::fake('b2');
    $branch = Branch::factory()->create(['is_active' => true]);

    Livewire::test(QualityControlAudits::class)
        ->call('openStartAuditModal')
        ->set('startAuditData.branch_id', $branch->id)
        ->set('startAuditData.audit_date', '2026-08-19')
        ->call('submitStartAudit');

    $audit = QualityControlAudit::query()->sole();
    $item = $audit->items()->first();

    $tinyJpegBase64 = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/2wBDAQMDAwQDBAgEBAgQCwkLEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBD/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAj/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

    $page = Livewire::test(QualityControlAuditDetail::class, ['record' => $audit->id])
        ->call('openItemModal', $item->id)
        ->set('itemData.result', 'pass')
        ->call('storeCameraPhoto', $tinyJpegBase64);

    $paths = $page->get('photoPaths');

    expect($paths)->toHaveCount(1);
    Storage::disk('b2')->assertExists($paths[0]);

    $page->call('saveItem');

    expect($item->refresh()->evidence_photos)->toBe($paths);
});

it('blocks submitting an audit while points remain unanswered', function () {
    $branch = Branch::factory()->create(['is_active' => true]);

    Livewire::test(QualityControlAudits::class)
        ->call('openStartAuditModal')
        ->set('startAuditData.branch_id', $branch->id)
        ->set('startAuditData.audit_date', '2026-08-19')
        ->call('submitStartAudit');

    $audit = QualityControlAudit::query()->sole();

    Livewire::test(QualityControlAuditDetail::class, ['record' => $audit->id])
        ->call('submitAudit')
        ->assertNotified();

    expect($audit->refresh()->status)->toBe('draft');
});

it('submits an audit once every point has been answered', function () {
    $branch = Branch::factory()->create(['is_active' => true]);

    Livewire::test(QualityControlAudits::class)
        ->call('openStartAuditModal')
        ->set('startAuditData.branch_id', $branch->id)
        ->set('startAuditData.audit_date', '2026-08-19')
        ->call('submitStartAudit');

    $audit = QualityControlAudit::query()->sole();
    foreach ($audit->items as $item) {
        $item->update(['result' => 'pass']);
    }

    Livewire::test(QualityControlAuditDetail::class, ['record' => $audit->id])
        ->call('submitAudit');

    $audit->refresh();

    expect($audit->status)->toBe('submitted')
        ->and($audit->submitted_at)->not->toBeNull()
        ->and($audit->score)->toBe(100.0);
});

it('prevents a quality control user from viewing another auditor\'s audit', function () {
    $branch = Branch::factory()->create(['is_active' => true]);
    $otherAuditor = User::factory()->create(['is_active' => true]);
    $otherAuditor->assignRole('QUALITY_CONTROL');

    $audit = QualityControlAudit::factory()->create([
        'branch_id' => $branch->id,
        'auditor_id' => $otherAuditor->id,
    ]);

    $this->get(route('filament.casual.pages.quality-control-audit-detail', ['record' => $audit->id]))
        ->assertNotFound();
});

it('only lists draft audits on the main audit page', function () {
    $branch = Branch::factory()->create(['is_active' => true]);

    $draft = QualityControlAudit::factory()->create([
        'branch_id' => $branch->id,
        'auditor_id' => $this->auditor->id,
        'status' => 'draft',
    ]);
    $submitted = QualityControlAudit::factory()->create([
        'branch_id' => $branch->id,
        'auditor_id' => $this->auditor->id,
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    $ids = Livewire::test(QualityControlAudits::class)
        ->get('draftAudits')
        ->pluck('id');

    expect($ids)->toContain($draft->id)
        ->not->toContain($submitted->id);
});

it('lists only submitted audits on the history page', function () {
    $branch = Branch::factory()->create(['is_active' => true]);

    $draft = QualityControlAudit::factory()->create([
        'branch_id' => $branch->id,
        'auditor_id' => $this->auditor->id,
        'status' => 'draft',
    ]);
    $submitted = QualityControlAudit::factory()->create([
        'branch_id' => $branch->id,
        'auditor_id' => $this->auditor->id,
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    $ids = Livewire::test(QualityControlAuditHistory::class)
        ->get('audits')
        ->pluck('id');

    expect($ids)->toContain($submitted->id)
        ->not->toContain($draft->id);
});

it('blocks the history page for a user without the app permission', function () {
    $otherUser = User::factory()->create(['is_active' => true]);
    $this->actingAs($otherUser);

    $this->get(route('filament.casual.pages.quality-control-audit-history'))
        ->assertForbidden();
});
