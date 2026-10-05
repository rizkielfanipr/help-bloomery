<?php

use App\Filament\Helpdesk\Resources\QualityControlAudits\Pages\ViewQualityControlAudit;
use App\Filament\Helpdesk\Resources\QualityControlAudits\QualityControlAuditResource;
use App\Models\Branch;
use App\Models\QualityControlAudit;
use App\Models\QualityControlAuditItem;
use App\Models\QualityControlChecklistItem;
use App\Models\User;
use Database\Seeders\QualityControlChecklistSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('seeds the complete quality control checklist with 180 scored points', function () {
    $this->seed(QualityControlChecklistSeeder::class);
    $this->seed(QualityControlChecklistSeeder::class);

    expect(QualityControlChecklistItem::count())->toBe(36)
        ->and(QualityControlChecklistItem::sum('points'))->toBe(180)
        ->and(QualityControlChecklistItem::where('is_critical', true)->count())->toBe(4);
});

it('calculates audit score and traffic light rating from audit answers', function () {
    $audit = QualityControlAudit::factory()->create([
        'branch_id' => Branch::factory(),
        'auditor_id' => User::factory(),
    ]);

    QualityControlAuditItem::factory()->create([
        'quality_control_audit_id' => $audit->id,
        'maximum_points' => 85,
        'result' => 'scored',
        'earned_points' => 85,
    ]);
    QualityControlAuditItem::factory()->create([
        'quality_control_audit_id' => $audit->id,
        'maximum_points' => 15,
        'result' => 'scored',
        'earned_points' => 0,
    ]);

    $audit->refresh();

    expect($audit->earned_points)->toBe(85)
        ->and($audit->maximum_points)->toBe(100)
        ->and($audit->score)->toBe(85.0)
        ->and($audit->rating)->toBe('green');
});

it('excludes unanswered points from the score denominator', function () {
    $audit = QualityControlAudit::factory()->create();

    QualityControlAuditItem::factory()->create([
        'quality_control_audit_id' => $audit->id,
        'maximum_points' => 10,
        'result' => 'scored',
        'earned_points' => 10,
    ]);
    QualityControlAuditItem::factory()->create([
        'quality_control_audit_id' => $audit->id,
        'maximum_points' => 100,
        'result' => null,
    ]);

    $audit->refresh();

    expect($audit->score)->toBe(100.0)
        ->and($audit->earned_points)->toBe(10)
        ->and($audit->maximum_points)->toBe(10);
});

it('allows supervisors to view and edit audits from the helpdesk back office without creating them', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->seed(RolesAndPermissionsSeeder::class);

    $supervisor = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $supervisor->assignRole('QUALITY_CONTROL_SUPERVISOR');
    $this->actingAs($supervisor);

    $audit = QualityControlAudit::factory()->create([
        'branch_id' => Branch::factory(),
    ]);

    expect(Route::has('filament.helpdesk.resources.quality-control-audits.create'))->toBeFalse()
        ->and(Route::has('filament.helpdesk.resources.quality-control-audits.edit'))->toBeFalse()
        ->and(QualityControlAuditResource::canEdit($audit))->toBeTrue();

    $this->get(route('filament.helpdesk.resources.quality-control-audits.index'))
        ->assertOk()
        ->assertDontSee('Create');

    $this->get(route('filament.helpdesk.resources.quality-control-audits.view', ['record' => $audit->getRouteKey()]))
        ->assertOk();
});

it('shows audit point details matching the user form inside collapsible panels', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('b2');
    Storage::disk('b2')->buildTemporaryUrlsUsing(
        fn (string $path): string => 'https://files.example.test/'.$path,
    );

    $supervisor = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $supervisor->assignRole('QUALITY_CONTROL_SUPERVISOR');
    $this->actingAs($supervisor);

    $audit = QualityControlAudit::factory()->create([
        'branch_id' => Branch::factory()->state(['name' => 'Bloomery Pabelan']),
        'auditor_id' => $supervisor->id,
        'top_findings' => 'Suhu display perlu diperbaiki',
    ]);

    QualityControlAuditItem::factory()->create([
        'quality_control_audit_id' => $audit->id,
        'section_code' => 'A',
        'section_name' => 'Hygiene & Food Safety',
        'question' => 'Suhu display sesuai standar?',
        'check_procedure' => 'Ukur menggunakan termometer terkalibrasi',
        'notes' => 'Suhu terlalu tinggi',
        'corrective_action' => 'Atur ulang suhu display',
        'result' => 'scored',
        'earned_points' => 0,
        'maximum_points' => 5,
        'evidence_photos' => ['quality-control/audit-photo.jpg'],
    ]);

    $this->get(route('filament.helpdesk.resources.quality-control-audits.view', ['record' => $audit->id]))
        ->assertOk()
        ->assertSee('Detail Audit Quality Control')
        ->assertSee('Bloomery Pabelan')
        ->assertSee('Hasil Penilaian')
        ->assertSee('Suhu display perlu diperbaiki')
        ->assertSee('Hygiene &amp; Food Safety', false)
        ->assertSee('Suhu display sesuai standar?')
        ->assertSee('Suhu terlalu tinggi')
        ->assertSee('Ukur menggunakan termometer terkalibrasi')
        ->assertSee('Poin')
        ->assertSee('Catatan')
        ->assertSee('Foto Bukti')
        ->assertDontSee('Atur ulang suhu display')
        ->assertDontSee('Skor Maksimal')
        ->assertDontSee('Status Tindakan')
        ->assertDontSee('Tindakan Korektif')
        ->assertDontSee('PIC Tindakan')
        ->assertDontSee('Tenggat Tindakan')
        ->assertDontSee('Foto Bukti Tindakan')
        ->assertSeeHtml('alt="Foto Bukti 1"')
        ->assertSeeHtml('src="https://files.example.test/quality-control/audit-photo.jpg"')
        ->assertSeeHtml('<details class="group bg-white dark:bg-gray-900"')
        ->assertSeeHtml('<summary class="flex cursor-pointer');
});

it('edits a quality control audit point from its detail panel', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->seed(RolesAndPermissionsSeeder::class);

    $supervisor = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $supervisor->assignRole('QUALITY_CONTROL_SUPERVISOR');
    $this->actingAs($supervisor);

    $audit = QualityControlAudit::factory()->create(['branch_id' => Branch::factory()]);
    $item = QualityControlAuditItem::factory()->create([
        'quality_control_audit_id' => $audit->id,
        'maximum_points' => 10,
        'result' => 'scored',
        'earned_points' => 4,
        'notes' => 'Catatan lama',
        'corrective_action' => 'Data tindakan yang tidak diedit',
    ]);

    Livewire::test(ViewQualityControlAudit::class, ['record' => $audit->id])
        ->assertSee('Edit Poin')
        ->callAction('editItem', [
            'earned_points' => 8,
            'notes' => 'Catatan hasil revisi',
            'evidence_photos' => [],
        ], ['item' => $item->id])
        ->assertHasNoActionErrors();

    $item->refresh();
    $audit->refresh();

    expect($item->earned_points)->toBe(8)
        ->and($item->notes)->toBe('Catatan hasil revisi')
        ->and($item->evidence_photos)->toBe([])
        ->and($item->result)->toBe('scored')
        ->and($item->corrective_action)->toBe('Data tindakan yang tidak diedit')
        ->and($audit->score)->toBe(80.0);
});

it('rejects an audit point score above its maximum value', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->seed(RolesAndPermissionsSeeder::class);

    $supervisor = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $supervisor->assignRole('QUALITY_CONTROL_SUPERVISOR');
    $this->actingAs($supervisor);

    $audit = QualityControlAudit::factory()->create(['branch_id' => Branch::factory()]);
    $item = QualityControlAuditItem::factory()->create([
        'quality_control_audit_id' => $audit->id,
        'maximum_points' => 10,
        'result' => 'scored',
        'earned_points' => 4,
    ]);

    Livewire::test(ViewQualityControlAudit::class, ['record' => $audit->id])
        ->callAction('editItem', [
            'earned_points' => 11,
        ], ['item' => $item->id])
        ->assertHasActionErrors(['earned_points' => ['max']]);

    expect($item->fresh()->earned_points)->toBe(4);
});

it('builds distinct section filter options without conflicting with the default sort_order ordering', function () {
    $audit = QualityControlAudit::factory()->create();

    QualityControlAuditItem::factory()->create([
        'quality_control_audit_id' => $audit->id,
        'section_code' => 'A',
        'section_name' => 'Kebersihan',
        'sort_order' => 2,
    ]);
    QualityControlAuditItem::factory()->create([
        'quality_control_audit_id' => $audit->id,
        'section_code' => 'A',
        'section_name' => 'Kebersihan',
        'sort_order' => 1,
    ]);
    QualityControlAuditItem::factory()->create([
        'quality_control_audit_id' => $audit->id,
        'section_code' => 'B',
        'section_name' => 'Pelayanan',
        'sort_order' => 3,
    ]);

    $options = $audit->items()->reorder('section_code')->distinct()->pluck('section_name', 'section_code')->all();

    expect($options)->toBe([
        'A' => 'Kebersihan',
        'B' => 'Pelayanan',
    ]);
});

it('renders the quality control links in the custom helpdesk sidebar', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);

    $response = $this->get(route('filament.helpdesk.resources.quality-control-audits.index'));

    $response->assertOk()
        ->assertSee('Quality Control')
        ->assertSee(route('filament.helpdesk.resources.quality-control-audits.index'), false)
        ->assertSee(route('filament.helpdesk.resources.quality-control-checklist-items.index'), false);
});
