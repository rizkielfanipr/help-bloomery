<?php

use App\Filament\Employee\Modules\Receiving\Pages\GoodsReceiptPage;
use App\Filament\Employee\Pages\LauncherPage;
use App\Filament\Helpdesk\Modules\Inventory\Resources\StockCards\StockCardResource;
use App\Filament\Helpdesk\Modules\Rnd\Resources\Projects\ProjectResource;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

it('discovers module resources and pages exactly once alongside shared pages', function () {
    $helpdesk = Filament::getPanel('helpdesk');
    $employee = Filament::getPanel('employee');
    expect(array_count_values($helpdesk->getResources())[ProjectResource::class])->toBe(1)
        ->and(array_count_values($helpdesk->getResources())[StockCardResource::class])->toBe(1)
        ->and(array_count_values($employee->getPages())[GoodsReceiptPage::class])->toBe(1)
        ->and(array_count_values($employee->getPages())[LauncherPage::class])->toBe(1);
});

it('keeps custom sidebar named links registered after template moves', function () {
    foreach ([resource_path('views/vendor/filament-panels/components/layout/index.blade.php'), resource_path('views/components/layouts/helpdesk-app.blade.php')] as $path) {
        preg_match_all('/\$r\([\'"](filament\.[^\'"]+)[\'"]\)/', file_get_contents($path), $matches);
        expect($matches[1])->not->toBeEmpty();
        foreach ($matches[1] as $name) {
            expect(Route::has($name))->toBeTrue("Sidebar route missing: {$name}");
        }
    }
});
