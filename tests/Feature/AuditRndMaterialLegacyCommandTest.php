<?php

use App\Enums\MaterialSourcingStatus;
use App\Models\RndMaterialPricingRequest;
use App\Models\RndMaterialReview;
use App\Models\RndProductEsbMaterial;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

function legacyMaterialReport(array $options = []): array
{
    Artisan::call('rnd:audit-material-legacy', array_merge(['--json' => true], $options));

    return json_decode(Artisan::output(), true);
}

it('classifies legacy materials without modifying data or calling ESB', function () {
    Http::fake();
    $mapped = RndProductEsbMaterial::factory()->legacy()->create(['status' => 'synced', 'esb_product_id' => 8100]);
    $mapped->units()->update(['esb_product_detail_id' => 9100]);
    $wip = RndProductEsbMaterial::factory()->legacy()->wip()->create(['status' => 'synced', 'esb_product_id' => 8200]);
    $wip->units()->update(['esb_product_detail_id' => 9200]);
    $incomplete = RndProductEsbMaterial::factory()->legacy()->create(['status' => 'synced', 'esb_product_id' => 8300]);
    $unknown = RndProductEsbMaterial::factory()->legacy()->create(['status' => 'unknown']);
    $draft = RndProductEsbMaterial::factory()->legacy()->create(['status' => 'draft']);
    $sourced = RndProductEsbMaterial::factory()->legacy()->create(['status' => 'failed', 'sourcing_status' => MaterialSourcingStatus::Approved]);
    RndProductEsbMaterial::factory()->create();
    $before = RndProductEsbMaterial::query()->orderBy('id')->get()->toArray();

    $report = legacyMaterialReport();

    expect($report['total'])->toBe(6)
        ->and($report['categories']['synced_mapping_complete'])->toBe([$mapped->id, $wip->id])
        ->and($report['categories']['synced_wip_pricing_candidate'])->toBe([$wip->id])
        ->and($report['categories']['synced_mapping_incomplete'])->toBe([$incomplete->id])
        ->and($report['categories']['needs_reconciliation'])->toBe([$unknown->id])
        ->and($report['categories']['never_sent'])->toBe([$draft->id])
        ->and($report['categories']['failed'])->toBe([$sourced->id])
        ->and($report['categories']['sourcing_approved_without_evidence'])->toBe([$sourced->id]);

    expect(RndProductEsbMaterial::query()->orderBy('id')->get()->toArray())->toBe($before)
        ->and(RndMaterialReview::query()->count())->toBe(0)
        ->and(RndMaterialPricingRequest::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('limits the legacy report to the requested IDs', function () {
    $first = RndProductEsbMaterial::factory()->legacy()->create(['status' => 'draft']);
    $second = RndProductEsbMaterial::factory()->legacy()->create(['status' => 'draft']);
    RndProductEsbMaterial::factory()->legacy()->create(['status' => 'draft']);

    expect(legacyMaterialReport(['--id' => [$second->id]])['categories']['never_sent'])->toBe([$second->id])
        ->and(legacyMaterialReport(['--from-id' => $first->id, '--to-id' => $second->id])['total'])->toBe(2);
});
