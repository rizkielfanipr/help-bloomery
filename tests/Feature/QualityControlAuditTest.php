<?php

use App\Models\Branch;
use App\Models\QualityControlAudit;
use App\Models\QualityControlAuditItem;
use App\Models\QualityControlChecklistItem;
use App\Models\User;
use Database\Seeders\QualityControlChecklistSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

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
        'result' => 'pass',
    ]);
    QualityControlAuditItem::factory()->create([
        'quality_control_audit_id' => $audit->id,
        'maximum_points' => 15,
        'result' => 'fail',
    ]);

    $audit->refresh();

    expect($audit->earned_points)->toBe(85)
        ->and($audit->maximum_points)->toBe(100)
        ->and($audit->score)->toBe(85.0)
        ->and($audit->rating)->toBe('green');
});

it('excludes not applicable and unanswered points from the score denominator', function () {
    $audit = QualityControlAudit::factory()->create();

    QualityControlAuditItem::factory()->create([
        'quality_control_audit_id' => $audit->id,
        'maximum_points' => 10,
        'result' => 'pass',
    ]);
    QualityControlAuditItem::factory()->create([
        'quality_control_audit_id' => $audit->id,
        'maximum_points' => 90,
        'result' => 'not_applicable',
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

it('only allows viewing audits from the helpdesk back office, not creating or editing', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->seed(RolesAndPermissionsSeeder::class);

    $supervisor = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $supervisor->assignRole('QUALITY_CONTROL_SUPERVISOR');
    $this->actingAs($supervisor);

    $audit = QualityControlAudit::factory()->create([
        'branch_id' => Branch::factory(),
    ]);

    expect(Route::has('filament.helpdesk.resources.quality-control-audits.create'))->toBeFalse()
        ->and(Route::has('filament.helpdesk.resources.quality-control-audits.edit'))->toBeFalse();

    $this->get(route('filament.helpdesk.resources.quality-control-audits.index'))
        ->assertOk()
        ->assertDontSee('Create');

    $this->get(route('filament.helpdesk.resources.quality-control-audits.view', ['record' => $audit->getRouteKey()]))
        ->assertOk();
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
