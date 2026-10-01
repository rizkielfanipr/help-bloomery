<?php

use App\Actions\Rnd\InternalMemo\ResolveMemoBranchMappingsAction;
use App\Models\Branch;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoBranch;
use App\Models\RndInternalMemoMenu;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * docs/rnd-internal-memo-multi-branch-prd.md Phase 2 ("Schema dan domain branch").
 */
beforeEach(function () {
    config()->set('esb.tokens.BLSS', 'static-blss-token');
    config()->set('esb.tokens.BLO6', 'static-blo6-token');
});

it('resolves the explicit internal_memo_esb_code_id mapping first, ignoring other active mappings on the same branch', function () {
    $branch = Branch::factory()->create();
    $other = $branch->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'BLS']);
    $explicit = $branch->esbCodes()->create(['esb_comcode' => 'BLO6', 'esb_branch_code' => 'BL6']);
    $branch->update(['internal_memo_esb_code_id' => $explicit->id]);

    $resolution = app(ResolveMemoBranchMappingsAction::class)->resolve($branch->fresh());

    expect($resolution->isResolved())->toBeTrue()
        ->and($resolution->mapping->id)->toBe($explicit->id)
        ->and($resolution->mapping->id)->not->toBe($other->id);
});

it('falls back to the single active mapping when no explicit mapping is set', function () {
    $branch = Branch::factory()->create();
    $mapping = $branch->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'BLS']);

    $resolution = app(ResolveMemoBranchMappingsAction::class)->resolve($branch->fresh());

    expect($resolution->isResolved())->toBeTrue()
        ->and($resolution->mapping->id)->toBe($mapping->id);
});

it('blocks a branch with two or more active mappings and no explicit pick, without guessing the first one', function () {
    $branch = Branch::factory()->create();
    $branch->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'BLS']);
    $branch->esbCodes()->create(['esb_comcode' => 'BLO6', 'esb_branch_code' => 'BL6']);

    $resolution = app(ResolveMemoBranchMappingsAction::class)->resolve($branch->fresh());

    expect($resolution->isResolved())->toBeFalse()
        ->and($resolution->blockedReason)->not->toBeNull();
});

it('blocks a branch with no active ESB mapping at all', function () {
    $branch = Branch::factory()->create();

    $resolution = app(ResolveMemoBranchMappingsAction::class)->resolve($branch);

    expect($resolution->isResolved())->toBeFalse()
        ->and($resolution->blockedReason)->toContain('belum mempunyai mapping');
});

it('ignores an inactive mapping and blocks the branch as if it had none', function () {
    $branch = Branch::factory()->create();
    $branch->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'BLS', 'is_active' => false]);

    $resolution = app(ResolveMemoBranchMappingsAction::class)->resolve($branch->fresh());

    expect($resolution->isResolved())->toBeFalse();
});

it('blocks a branch whose single active mapping has a blank Company Code or Branch Code', function () {
    $branchNoCompany = Branch::factory()->create();
    $branchNoCompany->esbCodes()->create(['esb_comcode' => '', 'esb_branch_code' => 'BLS']);

    $branchNoCode = Branch::factory()->create();
    $branchNoCode->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => '']);

    $resolver = app(ResolveMemoBranchMappingsAction::class);

    expect($resolver->resolve($branchNoCompany->fresh())->isResolved())->toBeFalse()
        ->and($resolver->resolve($branchNoCode->fresh())->isResolved())->toBeFalse();
});

it('blocks a branch whose Company Code has no static Master Menu token configured', function () {
    $branch = Branch::factory()->create();
    $branch->esbCodes()->create(['esb_comcode' => 'BLO18', 'esb_branch_code' => 'B18']);
    config()->set('esb.tokens.BLO18', '');

    $resolution = app(ResolveMemoBranchMappingsAction::class)->resolve($branch->fresh());

    expect($resolution->isResolved())->toBeFalse()
        ->and($resolution->blockedReason)->toContain('BLO18');
});

it('resolves many branches independently, so one blocked branch does not affect another', function () {
    $good = Branch::factory()->create();
    $good->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'BLS']);

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
