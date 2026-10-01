<?php

use App\Actions\Rnd\InternalMemo\CreateInternalMemoAction;
use App\Actions\Rnd\InternalMemo\ResolveMemoBranchMappingsAction;
use App\Actions\Rnd\InternalMemo\UpdateInternalMemoBranchesAction;
use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ListRndInternalMemos;
use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ViewRndInternalMemo;
use App\Models\Branch;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * docs/rnd-internal-memo-multi-branch-prd.md Phase 3 ("Form create/edit dan Policy").
 */
beforeEach(function () {
    Queue::fake();
    config()->set('esb.tokens.BLSS', 'static-blss-token');
    config()->set('esb.tokens.BLO6', 'static-blo6-token');
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $this->branchA = Branch::factory()->create(['name' => 'Bloomery Pabelan']);
    $this->branchA->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'BLS']);
    $this->branchB = Branch::factory()->create(['name' => 'Bloomery Takeaway']);
    $this->branchB->esbCodes()->create(['esb_comcode' => 'BLO6', 'esb_branch_code' => 'BL6']);

    $this->operator = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $this->operator->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo', 'create rnd internal memo', 'update rnd internal memo']);
    $this->actingAs($this->operator);
});

function validMemoFormState(): array
{
    return [
        'memoNumber' => 'MB-001',
        'memoTitle' => 'Rilis Menu Multi Branch',
        'periodMonth' => '2026-09',
    ];
}

it('creates one Memo spanning two branches from two different Company Codes', function () {
    Livewire::test(ListRndInternalMemos::class)
        ->set(validMemoFormState())
        ->set('branchIds', [$this->branchA->id, $this->branchB->id])
        ->call('createMemo')
        ->assertHasNoErrors();

    $memo = RndInternalMemo::sole();
    expect($memo->company_code)->toBe('BLSS') // first-selected branch's resolved company
        ->and($memo->branches)->toHaveCount(2);

    $snapshots = $memo->branches->keyBy('branch_id');
    expect($snapshots[$this->branchA->id]->company_code_snapshot)->toBe('BLSS')
        ->and($snapshots[$this->branchB->id]->company_code_snapshot)->toBe('BLO6');
});

it('rejects a branch the user cannot access, even if the request bypasses the form\'s own option list', function () {
    $limitedUser = User::factory()->create(['is_active' => true, 'branch_id' => $this->branchA->id]);
    $limitedUser->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo', 'create rnd internal memo', 'update rnd internal memo']);
    $this->actingAs($limitedUser);

    Livewire::test(ListRndInternalMemos::class)
        ->set(validMemoFormState())
        ->set('branchIds', [$this->branchA->id, $this->branchB->id]) // branchB not in limitedUser's access
        ->call('createMemo')
        ->assertHasErrors(['branchIds']);

    expect(RndInternalMemo::query()->count())->toBe(0);
});

it('rejects a branch with an ambiguous (2+ active, no explicit) ESB mapping, with a specific reason', function () {
    $ambiguous = Branch::factory()->create();
    $ambiguous->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'BLS']);
    $ambiguous->esbCodes()->create(['esb_comcode' => 'BLO6', 'esb_branch_code' => 'BL6']);

    Livewire::test(ListRndInternalMemos::class)
        ->set(validMemoFormState())
        ->set('branchIds', [$ambiguous->id])
        ->call('createMemo')
        ->assertHasErrors(['branchIds']);

    expect(RndInternalMemo::query()->count())->toBe(0);
});

it('rejects a branch whose Company Code has no static Master Menu token configured', function () {
    $noToken = Branch::factory()->create();
    $noToken->esbCodes()->create(['esb_comcode' => 'BLO18', 'esb_branch_code' => 'B18']);
    config()->set('esb.tokens.BLO18', '');

    Livewire::test(ListRndInternalMemos::class)
        ->set(validMemoFormState())
        ->set('branchIds', [$noToken->id])
        ->call('createMemo')
        ->assertHasErrors(['branchIds']);
});

it('requires at least one Branch Tujuan', function () {
    Livewire::test(ListRndInternalMemos::class)
        ->set(validMemoFormState())
        ->set('branchIds', [])
        ->call('createMemo')
        ->assertHasErrors(['branchIds']);
});

it('allows two memos for the same period when their resolved Company Codes differ', function () {
    Livewire::test(ListRndInternalMemos::class)
        ->set(validMemoFormState())
        ->set('memoNumber', 'MB-001')
        ->set('branchIds', [$this->branchA->id])
        ->call('createMemo')
        ->assertHasNoErrors();

    Livewire::test(ListRndInternalMemos::class)
        ->set(validMemoFormState())
        ->set('memoNumber', 'MB-002')
        ->set('branchIds', [$this->branchB->id])
        ->call('createMemo')
        ->assertHasNoErrors();

    expect(RndInternalMemo::query()->count())->toBe(2);
});

it('still rejects a duplicate period for the same resolved Company Code', function () {
    Livewire::test(ListRndInternalMemos::class)
        ->set(validMemoFormState())
        ->set('memoNumber', 'MB-001')
        ->set('branchIds', [$this->branchA->id])
        ->call('createMemo')
        ->assertHasNoErrors();

    Livewire::test(ListRndInternalMemos::class)
        ->set(validMemoFormState())
        ->set('memoNumber', 'MB-002')
        ->set('branchIds', [$this->branchA->id])
        ->call('createMemo')
        ->assertHasErrors(['periodMonth']);
});

it('shows only accessible branches to a regular user, and every branch to an access_all_branches user', function () {
    $limitedUser = User::factory()->create(['is_active' => true, 'branch_id' => $this->branchA->id]);
    $limitedUser->givePermissionTo(['view any rnd internal memo', 'create rnd internal memo']);
    $this->actingAs($limitedUser);

    $options = Livewire::test(ListRndInternalMemos::class)->instance()->branchOptions(app(ResolveMemoBranchMappingsAction::class));
    $branchIds = collect($options)->map(fn (array $o) => $o['branch']->id);

    expect($branchIds)->toContain($this->branchA->id)
        ->and($branchIds)->not->toContain($this->branchB->id);

    $this->actingAs($this->operator); // access_all_branches = true
    $allOptions = Livewire::test(ListRndInternalMemos::class)->instance()->branchOptions(app(ResolveMemoBranchMappingsAction::class));
    $allBranchIds = collect($allOptions)->map(fn (array $o) => $o['branch']->id);

    expect($allBranchIds)->toContain($this->branchA->id)
        ->and($allBranchIds)->toContain($this->branchB->id);
});

it('marks an unresolvable branch option as blocked with its specific reason', function () {
    $noMapping = Branch::factory()->create();

    $options = Livewire::test(ListRndInternalMemos::class)->instance()->branchOptions(app(ResolveMemoBranchMappingsAction::class));
    $match = collect($options)->first(fn (array $o) => $o['branch']->id === $noMapping->id);

    expect($match['resolution']->isResolved())->toBeFalse()
        ->and($match['resolution']->blockedReason)->not->toBeNull();
});

it('lets a user view a multi-branch Memo if they can access at least one of its branches, not necessarily all', function () {
    $memo = app(CreateInternalMemoAction::class)->execute([
        'memo_number' => 'MB-010', 'title' => 'Rilis', 'period_month' => '2026-09-01', 'memo_date' => now()->toDateString(),
        'recipient' => '', 'sender' => '', 'subject' => 'Rilis', 'notes' => null,
        'branch_ids' => [$this->branchA->id, $this->branchB->id],
    ], $this->operator);

    $partialUser = User::factory()->create(['is_active' => true, 'branch_id' => $this->branchA->id]);
    $partialUser->givePermissionTo('view rnd internal memo');

    $outsider = User::factory()->create(['is_active' => true, 'branch_id' => Branch::factory()->create()->id]);
    $outsider->givePermissionTo('view rnd internal memo');

    expect($partialUser->can('view', $memo))->toBeTrue()
        ->and($outsider->can('view', $memo))->toBeFalse();
});

it('lets any permitted user view a legacy Memo that has no branches yet ("Perlu Menentukan Branch")', function () {
    $legacyMemo = RndInternalMemo::factory()->create(['period_month' => '2027-01-01']);
    $anyUser = User::factory()->create(['is_active' => true]);
    $anyUser->givePermissionTo('view rnd internal memo');

    expect($anyUser->can('view', $legacyMemo))->toBeTrue();
});

it('shows the Branch Tujuan summary on the detail page, and a "Perlu Menentukan Branch" notice for a legacy Memo', function () {
    $memo = app(CreateInternalMemoAction::class)->execute([
        'memo_number' => 'MB-020', 'title' => 'Rilis', 'period_month' => '2026-09-01', 'memo_date' => now()->toDateString(),
        'recipient' => '', 'sender' => '', 'subject' => 'Rilis', 'notes' => null,
        'branch_ids' => [$this->branchA->id],
    ], $this->operator);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->assertSee('Branch Tujuan')
        ->assertSee('Bloomery Pabelan')
        ->assertSee('BLSS');

    $legacyMemo = RndInternalMemo::factory()->create(['period_month' => '2027-01-01']);
    Livewire::test(ViewRndInternalMemo::class, ['record' => $legacyMemo->id])
        ->assertSee('Perlu Menentukan Branch');
});

it('prevents removing a Branch that would orphan an attached Menu', function () {
    $memo = app(CreateInternalMemoAction::class)->execute([
        'memo_number' => 'MB-030', 'title' => 'Rilis', 'period_month' => '2026-11-01', 'memo_date' => now()->toDateString(),
        'recipient' => '', 'sender' => '', 'subject' => 'Rilis', 'notes' => null,
        'branch_ids' => [$this->branchA->id, $this->branchB->id],
    ], $this->operator);
    $menu = RndInternalMemoMenu::factory()->create([
        'rnd_internal_memo_id' => $memo->id,
        'company_code' => 'BLO6',
    ]);
    $menu->branches()->attach($memo->branches()->where('branch_id', $this->branchB->id)->value('id'));

    expect(fn () => app(UpdateInternalMemoBranchesAction::class)->execute(
        $memo,
        [$this->branchA->id],
        $this->operator,
    ))->toThrow(ValidationException::class);

    expect($memo->branches()->count())->toBe(2);
});

it('allows removing a Branch when every attached Menu remains available on another selected Branch', function () {
    $branchC = Branch::factory()->create(['name' => 'Bloomery Pakuwon']);
    $branchC->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'BLP']);
    $memo = app(CreateInternalMemoAction::class)->execute([
        'memo_number' => 'MB-031', 'title' => 'Rilis', 'period_month' => '2026-12-01', 'memo_date' => now()->toDateString(),
        'recipient' => '', 'sender' => '', 'subject' => 'Rilis', 'notes' => null,
        'branch_ids' => [$this->branchA->id, $branchC->id],
    ], $this->operator);
    $menu = RndInternalMemoMenu::factory()->create([
        'rnd_internal_memo_id' => $memo->id,
        'company_code' => 'BLSS',
    ]);
    $menu->branches()->attach($memo->branches()->pluck('id'));

    app(UpdateInternalMemoBranchesAction::class)->execute($memo, [$this->branchA->id], $this->operator);

    expect($memo->branches()->count())->toBe(1)
        ->and($menu->branches()->count())->toBe(1)
        ->and($menu->branches()->sole()->branch_id)->toBe($this->branchA->id);
});
