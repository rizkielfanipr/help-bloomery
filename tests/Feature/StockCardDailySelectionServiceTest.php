<?php

use App\Models\Branch;
use App\Models\StockCard;
use App\Models\StockCardEntry;
use App\Services\StockCardDailySelectionService;

function selectionProducts(): array
{
    return collect(range(1, 4))->map(fn (int $number): array => [
        'product_code' => "RAW-{$number}",
        'product_name' => "Raw {$number}",
        'category' => 'Bahan Baku',
        'unit' => 'KG',
    ])->all();
}

function selectionSnapshot(int $count, bool $rotate = true): array
{
    return ['COM01' => ['category_rules' => [[
        'category_name' => 'Bahan Baku',
        'mode' => 'limited',
        'daily_count' => $count,
        'rotate_daily' => $rotate,
    ]]]];
}

it('limits products per category and records a selection summary', function () {
    $branch = Branch::factory()->create();

    $result = app(StockCardDailySelectionService::class)->select(
        $branch,
        '2026-09-23',
        selectionProducts(),
        selectionSnapshot(2),
    );

    expect($result['products'])->toHaveCount(2)
        ->and($result['summary']['bahan baku']['selected'])->toBe(2)
        ->and($result['summary']['bahan baku']['available'])->toBe(4)
        ->and($result['warnings'])->toBe([]);
});

it('rotates toward products that were not selected previously', function () {
    $branch = Branch::factory()->create();
    $first = app(StockCardDailySelectionService::class)->select($branch, '2026-09-22', selectionProducts(), selectionSnapshot(2));
    $card = StockCard::factory()->create(['branch_id' => $branch->id, 'report_date' => '2026-09-22']);
    foreach ($first['products'] as $product) {
        StockCardEntry::factory()->create([
            'stock_card_id' => $card->id,
            'product_code' => $product['product_code'],
            'product_category' => $product['category'],
        ]);
    }

    $second = app(StockCardDailySelectionService::class)->select($branch, '2026-09-23', selectionProducts(), selectionSnapshot(2));

    expect(array_intersect(
        array_column($first['products'], 'product_code'),
        array_column($second['products'], 'product_code'),
    ))->toBe([]);
});

it('keeps a stable subset when rotation is disabled', function () {
    $branch = Branch::factory()->create();
    $service = app(StockCardDailySelectionService::class);

    $first = $service->select($branch, '2026-09-22', selectionProducts(), selectionSnapshot(2, false));
    $second = $service->select($branch, '2026-09-23', selectionProducts(), selectionSnapshot(2, false));

    expect(array_column($second['products'], 'product_code'))
        ->toBe(array_column($first['products'], 'product_code'));
});

it('returns all available products with a warning when the target is larger than the pool', function () {
    $branch = Branch::factory()->create();

    $result = app(StockCardDailySelectionService::class)->select($branch, '2026-09-23', selectionProducts(), selectionSnapshot(10));

    expect($result['products'])->toHaveCount(4)
        ->and($result['warnings'])->toHaveCount(1)
        ->and($result['warnings'][0])->toContain('hanya 4 produk tersedia');
});
