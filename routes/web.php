<?php

use App\Filament\Helpdesk\Pages\EditBomRecipePage;
use App\Filament\Helpdesk\Pages\ViewBomPage;
use App\Filament\Helpdesk\Resources\Projects\ProjectResource;
use App\Http\Controllers\AssetIssueReportController;
use App\Http\Controllers\AssetScanController;
use App\Http\Controllers\Casual\BriefingScorePdfController;
use App\Http\Controllers\Helpdesk\AssetLabelController;
use App\Http\Controllers\Helpdesk\BriefingExportController;
use App\Http\Controllers\Helpdesk\BriefingScoreExportController;
use App\Http\Controllers\Helpdesk\CasualClockRecordExportController;
use App\Http\Controllers\Helpdesk\CasualStaffExportController;
use App\Http\Controllers\Helpdesk\DriverMealAllowanceExportController;
use App\Http\Controllers\Helpdesk\LocationLabelPdfController;
use App\Http\Controllers\Helpdesk\ProductLabelPdfController;
use App\Http\Controllers\Helpdesk\RndBomInstructionImageController;
use App\Http\Controllers\Helpdesk\RndProductBomPdfController;
use App\Http\Controllers\Helpdesk\RndProductEsbMaterialExportController;
use App\Http\Controllers\Helpdesk\RndProjectBomPdfController;
use App\Http\Controllers\Helpdesk\ShelfLifeExportController;
use App\Models\Asset;
use App\Models\RndProjectBom;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

Route::get('/employee-login', fn () => redirect()->to(Filament::getPanel('casual')->getLoginUrl()))->name('login');
Route::get('/assets/scan/{token}', AssetScanController::class)->name('assets.scan');
Route::get('/assets/scan/{token}/login', function (string $token) {
    Asset::query()->where('qr_token', $token)->firstOrFail();
    session(['url.intended' => route('assets.scan', $token)]);

    return redirect()->to(Filament::getPanel('casual')->getLoginUrl());
})->name('assets.login');

Route::middleware(['auth'])->group(function (): void {
    Route::post('/assets/scan/{token}/report', AssetIssueReportController::class)->name('assets.report');
    Route::get('/helpdesk/exports/casual-clock-records', CasualClockRecordExportController::class)
        ->name('helpdesk.exports.casual-clock-records');

    Route::get('/helpdesk/exports/casual-staff', CasualStaffExportController::class)
        ->name('helpdesk.exports.casual-staff');

    Route::get('/helpdesk/exports/driver-meal-allowance/{period}', DriverMealAllowanceExportController::class)
        ->name('helpdesk.exports.driver-meal-allowance');

    Route::get('/helpdesk/exports/briefing', BriefingExportController::class)
        ->name('helpdesk.exports.briefing');

    Route::get('/helpdesk/exports/briefing-scores', BriefingScoreExportController::class)
        ->name('helpdesk.exports.briefing-scores');

    Route::get('/helpdesk/exports/shelf-life', ShelfLifeExportController::class)
        ->name('helpdesk.exports.shelf-life');

    Route::get('/casual/exports/briefing-score-pdf', BriefingScorePdfController::class)
        ->name('casual.exports.briefing-score-pdf');

    Route::get('/helpdesk/locations/{location}/label-pdf', [LocationLabelPdfController::class, 'single'])
        ->name('helpdesk.locations.label-pdf');

    Route::get('/helpdesk/locations/labels-pdf', [LocationLabelPdfController::class, 'bulk'])
        ->name('helpdesk.locations.labels-pdf');

    Route::get('/helpdesk/assets/{asset}/label-pdf', [AssetLabelController::class, 'single'])->name('helpdesk.assets.label-pdf');
    Route::get('/helpdesk/assets/labels-pdf', [AssetLabelController::class, 'bulk'])->name('helpdesk.assets.labels-pdf');
    Route::get('/helpdesk/assets/{asset}/qr', [AssetLabelController::class, 'qr'])->name('helpdesk.assets.qr');

    Route::get('/helpdesk/products/{code}/label-pdf', [ProductLabelPdfController::class, 'single'])
        ->name('helpdesk.products.label-pdf');

    Route::get('/helpdesk/products/labels-pdf', [ProductLabelPdfController::class, 'bulk'])
        ->name('helpdesk.products.labels-pdf');

    Route::get('/rnd-projects/{project}/products/{product}/bom/export-pdf', RndProductBomPdfController::class)
        ->name('helpdesk.rnd-products.bom-pdf');

    Route::get('/rnd-projects/{project}/bom/export-pdf', RndProjectBomPdfController::class)
        ->name('helpdesk.rnd-projects.bom-pdf');

    Route::get('/rnd-projects/{project}/products/{product}/materials/export', RndProductEsbMaterialExportController::class)
        ->name('helpdesk.rnd-products.esb-materials-export');

    Route::post('/rnd-projects/{project}/products/{product}/boms/{bom}/instruction-images', [RndBomInstructionImageController::class, 'store'])
        ->name('helpdesk.rnd-products.bom-instruction-images.store');

    Route::get('/rnd-bom-instruction-images/{path}', [RndBomInstructionImageController::class, 'show'])
        ->where('path', '.*')
        ->name('helpdesk.rnd-products.bom-instruction-images.show');

    Route::get('/bill-of-material/create', function () {
        abort_unless(auth()->user()?->can('create bill of materials'), 403);

        return redirect()->to(ProjectResource::getUrl());
    })
        ->name('legacy.bill-of-material.create');

    Route::get('/bill-of-material/{bom}/view', function (int $bom) {
        abort_unless(auth()->user()?->can('view bill of materials'), 403);
        $projectBom = RndProjectBom::query()->with('products:id')->where('esb_bom_id', $bom)->first();
        $product = $projectBom?->products->first();

        return redirect()->to($projectBom && $product
            ? ViewBomPage::getUrl(['project' => $projectBom->rnd_project_id, 'product' => $product->id, 'bom' => $bom])
            : ProjectResource::getUrl());
    })->name('legacy.bill-of-material.view');

    Route::get('/bill-of-material/{bom}/edit', function (int $bom) {
        abort_unless(auth()->user()?->can('edit bill of materials'), 403);
        $projectBom = RndProjectBom::query()->with('products:id')->where('esb_bom_id', $bom)->first();
        $product = $projectBom?->products->first();

        return redirect()->to($projectBom && $product
            ? EditBomRecipePage::getUrl(['project' => $projectBom->rnd_project_id, 'product' => $product->id, 'bom' => $bom])
            : ProjectResource::getUrl());
    })->name('legacy.bill-of-material.edit');

    Route::get('/rnd-projects/{project}/bom/create', function (int $project) {
        abort_unless(auth()->user()?->can('create bill of materials'), 403);

        return redirect()->to(ProjectResource::getUrl('view', ['record' => $project]));
    })->name('legacy.rnd-projects.bom.create');

    Route::get('/rnd-projects/{project}/bom/{bom}/{action}', function (int $project, int $bom, string $action) {
        abort_unless(in_array($action, ['view', 'edit'], true), 404);
        abort_unless(auth()->user()?->can($action.' bill of materials'), 403);
        $projectBom = RndProjectBom::query()
            ->with('products:id')
            ->where('rnd_project_id', $project)
            ->where('esb_bom_id', $bom)
            ->first();
        $product = $projectBom?->products->first();

        if (! $product) {
            return redirect()->to(ProjectResource::getUrl('view', ['record' => $project]));
        }

        return redirect()->to($action === 'edit'
            ? EditBomRecipePage::getUrl(['project' => $project, 'product' => $product->id, 'bom' => $bom])
            : ViewBomPage::getUrl(['project' => $project, 'product' => $product->id, 'bom' => $bom]));
    })->name('legacy.rnd-projects.bom.action');
});

// Standalone UI mockups
Route::get('/ui/helpdesk', fn () => view('ui.helpdesk-dashboard'))->name('ui.helpdesk-dashboard');

require __DIR__.'/auth.php';
require __DIR__.'/driver.php';
