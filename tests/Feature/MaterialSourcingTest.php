<?php

use App\Enums\MaterialSourcingStatus;
use App\Filament\Helpdesk\Resources\MaterialSourcings\Pages\ListMaterialSourcings;
use App\Livewire\MaterialSourcingSupplierCard;
use App\Models\MaterialSourcingApproval;
use App\Models\RndProductEsbMaterial;
use App\Models\RndProject;
use App\Models\RndProjectProduct;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $project = RndProject::create(['name' => 'Project A', 'start_date' => now(), 'end_date' => now()->addMonths(3)]);
    $product = RndProjectProduct::create(['rnd_project_id' => $project->id, 'name' => 'Produk A']);

    $this->material = RndProductEsbMaterial::create([
        'rnd_project_product_id' => $product->id,
        'category_id' => 1,
        'sub_category_id' => 1,
        'uom_id' => 1,
        'uom_name' => 'KG',
        'product_code' => 'MAT-001',
        'product_name' => 'Tepung Terigu',
        'sku' => 'SKU-MAT-001',
        'status' => 'draft',
    ]);

    $this->purchasing = User::factory()->create(['is_active' => true]);
    $this->purchasing->assignRole('PURCHASING_STAFF');

    $this->rnd = User::factory()->create(['is_active' => true]);
    $this->rnd->assignRole('RND_STAFF');

    $this->finance = User::factory()->create(['is_active' => true]);
    $this->finance->assignRole('FINANCE_STAFF');
});

function supplierRow(string $name, float $price): array
{
    return [
        'supplier_name' => $name,
        'brand' => null,
        'price' => $price,
        'moq' => '100 kg',
        'lead_time_days' => 7,
        'contact_name' => 'Budi',
        'contact_phone' => '08123456789',
        'notes' => null,
        'attachment_path' => null,
    ];
}

it('shows the supplier brand and expandable edit button on each supplier card', function () {
    $supplier = $this->material->sourcings()->create([
        ...supplierRow('Supplier A', 10000),
        'brand' => 'Cap Bunga',
    ]);
    $this->actingAs($this->purchasing);

    Livewire::test(MaterialSourcingSupplierCard::class, ['sourcing' => $supplier])
        ->assertSee('Cap Bunga')
        ->assertSee('Edit')
        ->assertDontSee('Simpan')
        ->call('toggleEdit')
        ->assertSee('Simpan');
});

it('lets purchasing edit suppliers from the view supplier modal', function () {
    $supplier = $this->material->sourcings()->create([
        ...supplierRow('Supplier A', 10000),
        'brand' => 'Merk Lama',
    ]);
    $this->material->update([
        'sourcing_status' => MaterialSourcingStatus::Approved,
        'sourcing_selected_id' => $supplier->id,
    ]);
    $this->actingAs($this->purchasing);

    Livewire::test(MaterialSourcingSupplierCard::class, ['sourcing' => $supplier])
        ->call('toggleEdit')
        ->set('brand', 'Merk Baru')
        ->set('price', '12500')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', false);

    $this->material->refresh();

    expect($supplier->fresh()->brand)->toBe('Merk Baru')
        ->and((float) $supplier->fresh()->price)->toBe(12500.0)
        ->and($this->material->sourcing_status)->toBe(MaterialSourcingStatus::PendingRndReview)
        ->and($this->material->sourcing_selected_id)->toBeNull();
});

it('opens the manage sourcing modal with one empty supplier row', function () {
    $this->actingAs($this->purchasing);

    Livewire::test(ListMaterialSourcings::class)
        ->mountTableAction('manage_sourcing', $this->material)
        ->assertTableActionDataSet(fn (array $data): bool => count($data['sourcings'] ?? []) === 1);
});

it('uses the scrollable modal layout for manage and view supplier actions', function () {
    $this->material->sourcings()->create(supplierRow('Supplier A', 10000));
    $this->actingAs($this->purchasing);

    Livewire::test(ListMaterialSourcings::class)
        ->assertTableActionExists('manage_sourcing', function ($action): bool {
            return str_contains((string) ($action->getExtraModalWindowAttributes()['class'] ?? ''), 'material-sourcing-modal');
        }, $this->material)
        ->assertTableActionExists('view_sourcing', function ($action): bool {
            return str_contains((string) ($action->getExtraModalWindowAttributes()['class'] ?? ''), 'material-sourcing-modal');
        }, $this->material);
});

it('uses compact icon buttons for sourcing table actions', function () {
    $this->material->sourcings()->create(supplierRow('Supplier A', 10000));
    $this->actingAs($this->purchasing);

    Livewire::test(ListMaterialSourcings::class)
        ->assertTableActionExists('view_sourcing', fn ($action): bool => $action->isIconButton(), $this->material)
        ->assertTableActionExists('manage_sourcing', fn ($action): bool => $action->isIconButton(), $this->material);
});

it('lets purchasing submit multiple supplier options and sends the material for rnd review', function () {
    $this->actingAs($this->purchasing);

    $page = Livewire::test(ListMaterialSourcings::class)
        ->mountTableAction('manage_sourcing', $this->material);
    $data = $page->get('mountedActions.0.data');
    $firstRowKey = array_key_first($data['sourcings']);
    $data['sourcings'][$firstRowKey] = supplierRow('Supplier A', 10000);
    $data['sourcings'][(string) Str::uuid()] = supplierRow('Supplier B', 9500);

    $page->set('mountedActions.0.data', $data)
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $this->material->refresh();

    expect($this->material->sourcing_status)->toBe(MaterialSourcingStatus::PendingRndReview)
        ->and($this->material->sourcings)->toHaveCount(2)
        ->and($this->material->sourcings->pluck('supplier_name')->sort()->values()->all())
        ->toBe(['Supplier A', 'Supplier B']);
});

it('lets rnd select the winning supplier and approve, moving the material to finance review', function () {
    $this->material->sourcings()->createMany([
        supplierRow('Supplier A', 10000),
        supplierRow('Supplier B', 9500),
    ]);
    $this->material->update(['sourcing_status' => MaterialSourcingStatus::PendingRndReview]);
    $winner = $this->material->sourcings()->where('supplier_name', 'Supplier B')->sole();

    $this->actingAs($this->rnd);

    Livewire::test(ListMaterialSourcings::class)
        ->callTableAction('approve_rnd', $this->material, data: [
            'sourcing_selected_id' => $winner->id,
            'rnd_note' => 'Harga paling kompetitif',
        ])
        ->assertHasNoTableActionErrors();

    $this->material->refresh();

    expect($this->material->sourcing_status)->toBe(MaterialSourcingStatus::PendingFinanceReview)
        ->and($this->material->sourcing_selected_id)->toBe($winner->id)
        ->and($this->material->rnd_reviewed_by)->toBe($this->rnd->id)
        ->and(MaterialSourcingApproval::where('stage', 'rnd')->where('action', 'approved')->exists())->toBeTrue();
});

it('lets finance approve the rnd-selected supplier, completing the sourcing', function () {
    $this->material->sourcings()->createMany([supplierRow('Supplier A', 10000)]);
    $winner = $this->material->sourcings()->sole();
    $this->material->update([
        'sourcing_status' => MaterialSourcingStatus::PendingFinanceReview,
        'sourcing_selected_id' => $winner->id,
    ]);

    $this->actingAs($this->finance);

    Livewire::test(ListMaterialSourcings::class)
        ->callTableAction('approve_finance', $this->material, data: ['finance_note' => 'Sesuai budget'])
        ->assertHasNoTableActionErrors();

    $this->material->refresh();

    expect($this->material->sourcing_status)->toBe(MaterialSourcingStatus::Approved)
        ->and($this->material->finance_reviewed_by)->toBe($this->finance->id)
        ->and(MaterialSourcingApproval::where('stage', 'finance')->where('action', 'approved')->exists())->toBeTrue();
});

it('bounces the material back to rnd review when finance rejects', function () {
    $this->material->sourcings()->createMany([supplierRow('Supplier A', 10000)]);
    $winner = $this->material->sourcings()->sole();
    $this->material->update([
        'sourcing_status' => MaterialSourcingStatus::PendingFinanceReview,
        'sourcing_selected_id' => $winner->id,
    ]);

    $this->actingAs($this->finance);

    Livewire::test(ListMaterialSourcings::class)
        ->callTableAction('reject_finance', $this->material, data: ['finance_note' => 'Harga terlalu tinggi'])
        ->assertHasNoTableActionErrors();

    $this->material->refresh();

    expect($this->material->sourcing_status)->toBe(MaterialSourcingStatus::PendingRndReview)
        ->and(MaterialSourcingApproval::where('stage', 'finance')->where('action', 'rejected')->exists())->toBeTrue();
});

it('lets rnd reject a submission back to purchasing, who can resubmit', function () {
    // Start from a material with no prior suppliers so the "Kelola Sourcing"
    // modal's pre-fill (existing suppliers) is empty — this keeps the test
    // independent of Filament's Repeater pre-fill/merge behavior and focuses
    // purely on the reject -> resubmit status transition.
    $this->material->update(['sourcing_status' => MaterialSourcingStatus::PendingRndReview]);

    $this->actingAs($this->rnd);

    Livewire::test(ListMaterialSourcings::class)
        ->callTableAction('reject_rnd', $this->material, data: ['rnd_note' => 'Perlu supplier lain'])
        ->assertHasNoTableActionErrors();

    expect($this->material->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::Rejected);

    $this->actingAs($this->purchasing);

    $page = Livewire::test(ListMaterialSourcings::class)
        ->mountTableAction('manage_sourcing', $this->material);
    $data = $page->get('mountedActions.0.data');
    $firstRowKey = array_key_first($data['sourcings']);
    $data['sourcings'][$firstRowKey] = supplierRow('Supplier C', 8000);

    $page->set('mountedActions.0.data', $data)
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $this->material->refresh();

    expect($this->material->sourcing_status)->toBe(MaterialSourcingStatus::PendingRndReview)
        ->and($this->material->sourcings)->toHaveCount(1)
        ->and($this->material->sourcings->first()->supplier_name)->toBe('Supplier C');
});

it('lets purchasing add another supplier after a submission is pending review', function () {
    $this->material->sourcings()->createMany([supplierRow('Supplier A', 10000)]);
    $this->material->update(['sourcing_status' => MaterialSourcingStatus::PendingRndReview]);

    $this->actingAs($this->purchasing);

    $page = Livewire::test(ListMaterialSourcings::class)
        ->assertTableActionVisible('manage_sourcing', $this->material)
        ->mountTableAction('manage_sourcing', $this->material);
    $data = $page->get('mountedActions.0.data');
    $firstRowKey = array_key_first($data['sourcings']);
    $data['sourcings'][$firstRowKey] = supplierRow('Supplier B', 9000);

    $page->set('mountedActions.0.data', $data)
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($this->material->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::PendingRndReview)
        ->and($this->material->sourcings()->pluck('supplier_name')->sort()->values()->all())
        ->toBe(['Supplier A', 'Supplier B']);
});

it('reopens rnd review and clears the previous selection when adding a supplier after approval', function () {
    $existingSupplier = $this->material->sourcings()->create(supplierRow('Supplier A', 10000));
    $this->material->update([
        'sourcing_status' => MaterialSourcingStatus::Approved,
        'sourcing_selected_id' => $existingSupplier->id,
        'rnd_reviewed_by' => $this->rnd->id,
        'rnd_reviewed_at' => now(),
        'finance_reviewed_by' => $this->finance->id,
        'finance_reviewed_at' => now(),
    ]);

    $this->actingAs($this->purchasing);

    $page = Livewire::test(ListMaterialSourcings::class)
        ->mountTableAction('manage_sourcing', $this->material);
    $data = $page->get('mountedActions.0.data');
    $firstRowKey = array_key_first($data['sourcings']);
    $data['sourcings'][$firstRowKey] = supplierRow('Supplier B', 9000);

    $page->set('mountedActions.0.data', $data)
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $this->material->refresh();

    expect($this->material->sourcing_status)->toBe(MaterialSourcingStatus::PendingRndReview)
        ->and($this->material->sourcing_selected_id)->toBeNull()
        ->and($this->material->rnd_reviewed_by)->toBeNull()
        ->and($this->material->finance_reviewed_by)->toBeNull()
        ->and($this->material->sourcings)->toHaveCount(2);
});

it('blocks a user without the purchasing permission from submitting sourcing', function () {
    $this->actingAs($this->rnd);

    Livewire::test(ListMaterialSourcings::class)
        ->assertTableActionHidden('manage_sourcing', $this->material);
});

it('blocks review actions for users without the matching approval permission', function () {
    $this->material->sourcings()->createMany([supplierRow('Supplier A', 10000)]);
    $this->material->update(['sourcing_status' => MaterialSourcingStatus::PendingRndReview]);

    $this->actingAs($this->finance);

    Livewire::test(ListMaterialSourcings::class)
        ->assertTableActionHidden('approve_rnd', $this->material)
        ->assertTableActionHidden('reject_rnd', $this->material);
});

it('keeps the submitted supplier data visible after the sourcing moves past purchasing', function () {
    $this->material->sourcings()->createMany([
        supplierRow('Supplier A', 10000),
        supplierRow('Supplier B', 9500),
    ]);
    $winner = $this->material->sourcings()->where('supplier_name', 'Supplier B')->sole();
    $this->material->update([
        'sourcing_status' => MaterialSourcingStatus::Approved,
        'sourcing_selected_id' => $winner->id,
    ]);

    // Everyone can see the submitted data, while only Purchasing can add
    // another supplier and reopen the review flow.
    foreach ([$this->purchasing, $this->rnd, $this->finance] as $user) {
        $this->actingAs($user);

        $page = Livewire::test(ListMaterialSourcings::class)
            ->assertTableActionVisible('view_sourcing', $this->material);

        if ($user->is($this->purchasing)) {
            $page->assertTableActionVisible('manage_sourcing', $this->material);
        } else {
            $page->assertTableActionHidden('manage_sourcing', $this->material);
        }
    }
});

it('renders the sourcing bahan menu link in the custom helpdesk sidebar for purchasing staff', function () {
    $this->actingAs($this->purchasing);

    $response = $this->get(route('filament.helpdesk.resources.material-sourcings.index'));

    $response->assertOk()
        ->assertSee('Sourcing Bahan')
        ->assertSee(route('filament.helpdesk.resources.material-sourcings.index'), false);
});
