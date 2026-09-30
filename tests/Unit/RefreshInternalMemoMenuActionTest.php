<?php

use App\Actions\Rnd\InternalMemo\RefreshInternalMemoMenuAction;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Services\EsbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'memo-user', 'password' => 'memo-secret']);
    $this->mock(EsbService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('findActiveProductDetail')->zeroOrMoreTimes()->andReturnNull();
    });
    $memo = RndInternalMemo::factory()->create();
    $this->menu = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id, 'esb_bom_id' => 501]);
});

it('preserves Minimum Order for an item that keeps the same Product Detail ID after a refresh', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/501' => Http::sequence()
            ->push(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
                'bomID' => 501,
                'bomDetails' => [
                    ['productDetailID' => 15002, 'productCode' => 'RAW-FLOUR', 'productName' => 'Tepung', 'categoryName' => 'Bahan Baku Makanan', 'qty' => 250.0, 'uomName' => 'GR'],
                ],
            ])])
            ->push(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
                'bomID' => 501,
                'bomDetails' => [
                    // Same identity (productDetailID 15002), quantity changed by ESB.
                    ['productDetailID' => 15002, 'productCode' => 'RAW-FLOUR', 'productName' => 'Tepung', 'categoryName' => 'Bahan Baku Makanan', 'qty' => 300.0, 'uomName' => 'GR'],
                ],
            ])]),
    ]);

    app(RefreshInternalMemoMenuAction::class)->execute($this->menu);
    $this->menu->materials()->sole()->update(['minimum_order' => 500]);

    Cache::flush();
    app(RefreshInternalMemoMenuAction::class)->execute($this->menu);

    $material = $this->menu->materials()->sole();
    expect((float) $material->quantity_per_menu)->toBe(300.0)
        ->and((float) $material->minimum_order)->toBe(500.0);
});

it('drops Minimum Order for an item that no longer exists after a refresh', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/501' => Http::sequence()
            ->push(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
                'bomID' => 501,
                'bomDetails' => [
                    ['productDetailID' => 15002, 'productCode' => 'RAW-FLOUR', 'productName' => 'Tepung', 'categoryName' => 'Bahan Baku Makanan', 'qty' => 250.0, 'uomName' => 'GR'],
                ],
            ])])
            ->push(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
                'bomID' => 501,
                'bomDetails' => [
                    ['productDetailID' => 99999, 'productCode' => 'RAW-SUGAR', 'productName' => 'Gula', 'categoryName' => 'Bahan Baku Makanan', 'qty' => 10.0, 'uomName' => 'GR'],
                ],
            ])]),
    ]);

    app(RefreshInternalMemoMenuAction::class)->execute($this->menu);
    $this->menu->materials()->sole()->update(['minimum_order' => 500]);

    Cache::flush();
    app(RefreshInternalMemoMenuAction::class)->execute($this->menu);

    $material = $this->menu->materials()->sole();
    expect($material->product_code)->toBe('RAW-SUGAR')
        ->and($material->minimum_order)->toBeNull();
});

it('marks the Menu Failed and keeps its last snapshot when the refresh throws', function () {
    // Http::fake() merges stubs for the same URL across calls rather than replacing them, so a
    // single sequence stubs both resolve() calls' responses to /product/bom/501.
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/501' => Http::sequence()
            ->push(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
                'bomID' => 501,
                'bomDetails' => [
                    ['productDetailID' => 15002, 'productCode' => 'RAW-FLOUR', 'productName' => 'Tepung', 'categoryName' => 'Bahan Baku Makanan', 'qty' => 250.0, 'uomName' => 'GR'],
                ],
            ])])
            ->push(['status' => 'fail', 'errors' => [['message' => 'BOM tidak ditemukan']]], 404),
    ]);
    app(RefreshInternalMemoMenuAction::class)->execute($this->menu);

    Cache::flush();

    expect(fn () => app(RefreshInternalMemoMenuAction::class)->execute($this->menu))->toThrow(RuntimeException::class);

    $this->menu->refresh();
    expect($this->menu->sync_status->value)->toBe('failed')
        ->and($this->menu->sync_error)->not->toBeNull()
        ->and($this->menu->materials()->sole()->product_code)->toBe('RAW-FLOUR');
});

it('refuses a concurrent refresh of the same Menu', function () {
    $lock = Cache::lock("rnd-internal-memo.menu-refresh.{$this->menu->id}", 30);
    $lock->get();

    try {
        expect(fn () => app(RefreshInternalMemoMenuAction::class)->execute($this->menu))
            ->toThrow(RuntimeException::class);
    } finally {
        $lock->release();
    }
});
