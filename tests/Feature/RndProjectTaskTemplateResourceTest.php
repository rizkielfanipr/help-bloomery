<?php

use App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\Pages\CreateRndProjectTaskTemplate;
use App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\Pages\EditRndProjectTaskTemplate;
use App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\Pages\ListRndProjectTaskTemplates;
use App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\RndProjectTaskTemplateResource;
use App\Models\Branch;
use App\Models\RndProjectTaskTemplate;
use App\Models\RndProjectTaskTemplateApplication;
use App\Models\RndProjectTaskTemplateCheckpoint;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->givePermissionTo(['access backoffice', 'manage rnd project task templates']);

    $this->checkpointRow = fn (array $overrides = []): array => array_merge([
        'title' => 'Trial Resep', 'task_type' => 'trial', 'priority' => 'medium', 'description' => null,
    ], $overrides);
});

it('lists templates for viewers and hides them from users without template permissions', function () {
    RndProjectTaskTemplate::factory()->create(['name' => 'Checklist Launching']);

    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo(['access backoffice', 'view rnd project task templates']);
    $this->actingAs($viewer);

    Livewire::test(ListRndProjectTaskTemplates::class)->assertSee('Checklist Launching');
    expect(RndProjectTaskTemplateResource::canCreate())->toBeFalse();

    $outsider = User::factory()->create(['is_active' => true]);
    $outsider->givePermissionTo(['access backoffice', 'create rnd project tasks']);
    $this->actingAs($outsider);

    expect(RndProjectTaskTemplateResource::canViewAny())->toBeFalse();
});

it('creates a template with ordered checkpoints', function () {
    $undoRepeaterFake = Repeater::fake();
    $this->actingAs($this->manager);

    Livewire::test(CreateRndProjectTaskTemplate::class)
        ->fillForm([
            'name' => 'Checklist Launching',
            'is_active' => true,
            'checkpoints' => [
                ($this->checkpointRow)(),
                ($this->checkpointRow)(['title' => 'Launching', 'task_type' => 'launching']),
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $template = RndProjectTaskTemplate::query()->sole();

    expect($template->created_by)->toBe($this->manager->id)
        ->and($template->checkpoints->pluck('title')->all())->toBe(['Trial Resep', 'Launching'])
        ->and($template->checkpoints->pluck('sort_order')->all())->toBe([1, 2]);

    $undoRepeaterFake();
});

it('validates checkpoint count and required fields', function () {
    $undoRepeaterFake = Repeater::fake();
    $this->actingAs($this->manager);

    Livewire::test(CreateRndProjectTaskTemplate::class)
        ->fillForm(['name' => 'Kosong', 'checkpoints' => []])
        ->call('create')
        ->assertHasFormErrors(['checkpoints']);

    Livewire::test(CreateRndProjectTaskTemplate::class)
        ->fillForm(['name' => 'Terlalu Banyak', 'checkpoints' => array_fill(0, RndProjectTaskTemplate::MAX_CHECKPOINTS + 1, ($this->checkpointRow)())])
        ->call('create')
        ->assertHasFormErrors(['checkpoints']);

    Livewire::test(CreateRndProjectTaskTemplate::class)
        ->fillForm(['name' => 'Tanpa Nama Task', 'checkpoints' => [($this->checkpointRow)(['title' => ''])]])
        ->call('create')
        ->assertHasFormErrors(['checkpoints.0.title']);

    expect(RndProjectTaskTemplate::query()->count())->toBe(0);

    $undoRepeaterFake();
});

it('edits checkpoints and their order', function () {
    $undoRepeaterFake = Repeater::fake();
    $this->actingAs($this->manager);
    $template = RndProjectTaskTemplate::factory()->create();
    RndProjectTaskTemplateCheckpoint::factory()->for($template, 'template')->create(['title' => 'A', 'sort_order' => 1]);

    Livewire::test(EditRndProjectTaskTemplate::class, ['record' => $template->getRouteKey()])
        ->fillForm(['checkpoints' => [
            ($this->checkpointRow)(['title' => 'B']),
            ($this->checkpointRow)(['title' => 'A']),
        ]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($template->fresh()->checkpoints->pluck('title')->all())->toBe(['B', 'A']);

    $undoRepeaterFake();
});

it('toggles the active state and only lets unused templates be deleted', function () {
    $this->actingAs($this->manager);
    $used = RndProjectTaskTemplate::factory()->create();
    RndProjectTaskTemplateApplication::factory()->create(['rnd_project_task_template_id' => $used->id]);
    $unused = RndProjectTaskTemplate::factory()->create();

    Livewire::test(ListRndProjectTaskTemplates::class)
        ->callAction(TestAction::make('toggleActive')->table($used))
        ->assertActionHidden(TestAction::make(DeleteAction::getDefaultName())->table($used))
        ->assertActionVisible(TestAction::make(DeleteAction::getDefaultName())->table($unused));

    expect($used->fresh()->is_active)->toBeFalse();
});

it('renders the Template Checkpoint link in the custom helpdesk sidebar only for template viewers', function () {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo(['access backoffice', 'view rnd projects', 'view rnd project task templates']);
    $this->actingAs($viewer);

    $this->get(RndProjectTaskTemplateResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee('Research & Development')
        ->assertSee('Template Checkpoint')
        ->assertSee(RndProjectTaskTemplateResource::getUrl('index'), false);

    $projectOnly = User::factory()->create(['is_active' => true]);
    $projectOnly->givePermissionTo(['access backoffice', 'view rnd projects']);
    $this->actingAs($projectOnly);

    $this->get(route('filament.helpdesk.resources.rnd-projects.index'))
        ->assertSuccessful()
        ->assertDontSee(RndProjectTaskTemplateResource::getUrl('index'), false);
});

it('rejects managing actions for template viewers', function () {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo(['access backoffice', 'view rnd project task templates']);
    $this->actingAs($viewer);
    $template = RndProjectTaskTemplate::factory()->create();

    Livewire::test(ListRndProjectTaskTemplates::class)
        ->assertActionHidden(TestAction::make('toggleActive')->table($template));

    Livewire::test(EditRndProjectTaskTemplate::class, ['record' => $template->getRouteKey()])
        ->assertForbidden();
});

it('no longer asks for day offsets on the template form', function () {
    $this->actingAs($this->manager);

    Livewire::test(CreateRndProjectTaskTemplate::class)
        ->assertDontSee('Offset Assign')
        ->assertDontSee('Offset Deadline');
});

it('saves branch and PIC per checkpoint from the template form and loads them back for editing', function () {
    $undoRepeaterFake = Repeater::fake();
    $this->actingAs($this->manager);
    $kitchen = Branch::factory()->create(['is_active' => true]);
    $outlet = Branch::factory()->create(['is_active' => true]);
    $kitchenPic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $kitchenPic->syncBranchAccess([$kitchen->id], $kitchen->id);
    $kitchenPic->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));
    $outletPic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $outletPic->syncBranchAccess([$outlet->id], $outlet->id);
    $outletPic->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));

    Livewire::test(CreateRndProjectTaskTemplate::class)
        ->fillForm([
            'name' => 'Checklist Dengan PIC',
            'checkpoints' => [
                ($this->checkpointRow)(['branch_pics' => [
                    ['branch_id' => $kitchen->id, 'user_ids' => [$kitchenPic->id]],
                    ['branch_id' => $outlet->id, 'user_ids' => [$outletPic->id]],
                ]]),
                ($this->checkpointRow)(['title' => 'Tanpa Branch', 'branch_pics' => []]),
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $template = RndProjectTaskTemplate::query()->sole();
    $checkpoints = $template->checkpoints()->with(['branches', 'picUsers'])->get();

    expect($checkpoints[0]->branchPicRows())->toEqualCanonicalizing([
        ['branch_id' => (string) $kitchen->id, 'user_ids' => [(string) $kitchenPic->id]],
        ['branch_id' => (string) $outlet->id, 'user_ids' => [(string) $outletPic->id]],
    ])
        ->and($checkpoints[1]->branchPicRows())->toBe([]);

    Livewire::test(EditRndProjectTaskTemplate::class, ['record' => $template->getRouteKey()])
        ->assertSchemaStateSet(function (array $state) use ($kitchen): void {
            expect(collect($state['checkpoints'])->first()['branch_pics'])->toHaveCount(2)
                ->and(collect(collect($state['checkpoints'])->first()['branch_pics'])->pluck('branch_id')->map(fn ($id) => (int) $id)->all())->toContain($kitchen->id);
        });

    $undoRepeaterFake();
});

it('rejects a PIC who cannot access the chosen branch on the template form', function () {
    $undoRepeaterFake = Repeater::fake();
    $this->actingAs($this->manager);
    $kitchen = Branch::factory()->create(['is_active' => true]);
    $outsider = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);

    Livewire::test(CreateRndProjectTaskTemplate::class)
        ->fillForm([
            'name' => 'PIC Salah',
            'checkpoints' => [($this->checkpointRow)(['branch_pics' => [['branch_id' => $kitchen->id, 'user_ids' => [$outsider->id]]]])],
        ])
        ->call('create')
        ->assertHasFormErrors(['checkpoints.0.branch_pics.0.user_ids']);

    expect(RndProjectTaskTemplate::query()->count())->toBe(0);

    $undoRepeaterFake();
});
