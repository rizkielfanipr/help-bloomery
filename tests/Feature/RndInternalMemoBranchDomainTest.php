<?php

use App\Actions\Rnd\InternalMemo\ResolveMemoBranchMappingsAction;
use App\Models\Branch;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoBranch;
use App\Models\RndInternalMemoMenu;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

/**
 * docs/rnd-internal-memo-multi-branch-prd.md Phase 2 ("Schema dan domain branch").
 */
beforeEach(function () {
    config()->set('esb.tokens.BLSS', 'static-blss-token');
    config()->set('esb.tokens.BLO6', 'static-blo6-token');
});

it('uses one branch mapping column shared with Stock Card', function () {
    expect(Schema::hasColumn('branches', 'stock_card_esb_code_id'))->toBeTrue()
        ->and(Schema::hasColumn('branches', 'internal_memo_esb_code_id'))->toBeFalse();
});

it('uses the explicit Stock Card mapping for Memo Internal, ignoring other active mappings', function () {
    $branch = Branch::factory()->create();
    $other = $branch->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'BLS']);
    $explicit = $branch->esbCodes()->create(['esb_comcode' => 'BLO6', 'esb_branch_code' => 'BL6']);
    $branch->update(['stock_card_esb_code_id' => $explicit->id]);

    $resolution = app(ResolveMemoBranchMappingsAction::class)->resolve($branch->fresh());

    expect($resolution->isResolved())->toBeTrue()
        ->and($resolution->mapping->id)->toBe($explicit->id)
        ->and($resolution->mapping->id)->not->toBe($other->id);
});

it('does not guess from a single active mapping when the Stock Card source is not selected', function () {
    $branch = Branch::factory()->create();
    $branch->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'BLS']);

    $resolution = app(ResolveMemoBranchMappingsAction::class)->resolve($branch->fresh());

    expect($resolution->isResolved())->toBeFalse()
        ->and($resolution->mapping)->toBeNull()
        ->and($resolution->blockedReason)->toContain('Sumber Stock Card');
});

it('blocks a branch with multiple mappings when the Stock Card source is not selected', function () {
    $branch = Branch::factory()->create();
    $branch->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'BLS']);
    $branch->esbCodes()->create(['esb_comcode' => 'BLO6', 'esb_branch_code' => 'BL6']);

    $resolution = app(ResolveMemoBranchMappingsAction::class)->resolve($branch->fresh());

    expect($resolution->isResolved())->toBeFalse()
        ->and($resolution->blockedReason)->not->toBeNull();
});

it('blocks a branch with no Stock Card source', function () {
    $branch = Branch::factory()->create();

    $resolution = app(ResolveMemoBranchMappingsAction::class)->resolve($branch);

    expect($resolution->isResolved())->toBeFalse()
        ->and($resolution->blockedReason)->toContain('Sumber Stock Card');
});

it('ignores an inactive mapping and blocks the branch as if it had none', function () {
    $branch = Branch::factory()->create();
    $mapping = $branch->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'BLS', 'is_active' => false]);
    $branch->update(['stock_card_esb_code_id' => $mapping->id]);

    $resolution = app(ResolveMemoBranchMappingsAction::class)->resolve($branch->fresh());

    expect($resolution->isResolved())->toBeFalse();
});

it('blocks a branch whose single active mapping has a blank Company Code or Branch Code', function () {
    $branchNoCompany = Branch::factory()->create();
    $noCompanyMapping = $branchNoCompany->esbCodes()->create(['esb_comcode' => '', 'esb_branch_code' => 'BLS']);
    $branchNoCompany->update(['stock_card_esb_code_id' => $noCompanyMapping->id]);

    $branchNoCode = Branch::factory()->create();
    $noCodeMapping = $branchNoCode->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => '']);
    $branchNoCode->update(['stock_card_esb_code_id' => $noCodeMapping->id]);

    $resolver = app(ResolveMemoBranchMappingsAction::class);

    expect($resolver->resolve($branchNoCompany->fresh())->isResolved())->toBeFalse()
        ->and($resolver->resolve($branchNoCode->fresh())->isResolved())->toBeFalse();
});

it('blocks a branch whose Company Code has no static Master Menu token configured', function () {
    $branch = Branch::factory()->create();
    $mapping = $branch->esbCodes()->create(['esb_comcode' => 'BLO18', 'esb_branch_code' => 'B18']);
    $branch->update(['stock_card_esb_code_id' => $mapping->id]);
    config()->set('esb.tokens.BLO18', '');

    $resolution = app(ResolveMemoBranchMappingsAction::class)->resolve($branch->fresh());

    expect($resolution->isResolved())->toBeFalse()
        ->and($resolution->blockedReason)->toContain('BLO18');
});

it('resolves many branches independently, so one blocked branch does not affect another', function () {
    $good = Branch::factory()->create();
    $mapping = $good->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'BLS']);
    $good->update(['stock_card_esb_code_id' => $mapping->id]);

    $bad = Branch::factory()->create();

    $results = app(ResolveMemoBranchMappingsAction::class)->resolveMany([$good->fresh(), $bad->fresh()]);

    expect($results[$good->id]->isResolved())->toBeTrue()
        ->and($results[$bad->id]->isResolved())->toBeFalse();
});

it('stores one RndInternalMemoBranch snapshot row per branch, surviving later Master Branch mapping changes', function () {
    $memo = RndInternalMemo::factory()->create();
    $branch = Branch::factory()->create();
    $mapping = $branch->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'BLS', 'esb_branch_id' => 6]);

    $memoBranch = RndInternalMemoBranch::factory()->create([
        'rnd_internal_memo_id' => $memo->id,
        'branch_id' => $branch->id,
        'branch_esb_code_id' => $mapping->id,
        'branch_name_snapshot' => $branch->name,
        'company_code_snapshot' => 'BLSS',
        'branch_code_snapshot' => 'BLS',
        'esb_branch_id_snapshot' => 6,
    ]);

    // Master Branch mapping changes after the fact...
    $mapping->update(['esb_branch_code' => 'CHANGED', 'esb_comcode' => 'BLO6']);

    // ...but the Memo's own snapshot must not change (docs §8 "Snapshot Memo tidak berubah otomatis").
    expect($memoBranch->fresh()->company_code_snapshot)->toBe('BLSS')
        ->and($memoBranch->fresh()->branch_code_snapshot)->toBe('BLS');
});

it('rejects two RndInternalMemoBranch rows for the same Memo and ESB mapping', function () {
    $memo = RndInternalMemo::factory()->create();
    $branch = Branch::factory()->create();
    $mapping = $branch->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'BLS']);
    RndInternalMemoBranch::factory()->create(['rnd_internal_memo_id' => $memo->id, 'branch_esb_code_id' => $mapping->id, 'branch_id' => $mapping->branch_id]);

    expect(fn () => RndInternalMemoBranch::factory()->create([
        'rnd_internal_memo_id' => $memo->id,
        'branch_esb_code_id' => $mapping->id,
        'branch_id' => $mapping->branch_id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('allows one Memo to have more than one branch, and one Menu to be linked to more than one of them', function () {
    $memo = RndInternalMemo::factory()->create();
    $branchA = RndInternalMemoBranch::factory()->create(['rnd_internal_memo_id' => $memo->id]);
    $branchB = RndInternalMemoBranch::factory()->create(['rnd_internal_memo_id' => $memo->id]);
    $menu = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id]);

    $menu->branches()->attach([$branchA->id, $branchB->id]);

    expect($memo->branches)->toHaveCount(2)
        ->and($menu->fresh()->branches)->toHaveCount(2)
        ->and($branchA->fresh()->menus()->count())->toBe(1);
});

it('keeps company_code on a Menu row (merge identity), independent of its parent Memo column', function () {
    $menu = RndInternalMemoMenu::factory()->create(['company_code' => 'BLO6']);

    expect($menu->company_code)->toBe('BLO6');
});
