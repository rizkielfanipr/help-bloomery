<?php

use App\Filament\Helpdesk\Pages\EditBomRecipePage;
use App\Filament\Helpdesk\Pages\ViewBomPage;
use App\Filament\Helpdesk\Resources\Projects\ProjectResource;
use App\Http\Controllers\Casual\BriefingScorePdfController;
use App\Http\Controllers\Helpdesk\BriefingExportController;
use App\Http\Controllers\Helpdesk\BriefingScoreExportController;
use App\Http\Controllers\Helpdesk\CasualClockRecordExportController;
use App\Http\Controllers\Helpdesk\CasualStaffExportController;
use App\Http\Controllers\Helpdesk\RndProductBomPdfController;
use App\Http\Controllers\Helpdesk\RndProductEsbMaterialExportController;
use App\Models\RndProjectBom;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function (): void {
    Route::get('/helpdesk/exports/casual-clock-records', CasualClockRecordExportController::class)
        ->name('helpdesk.exports.casual-clock-records');

    Route::get('/helpdesk/exports/casual-staff', CasualStaffExportController::class)
        ->name('helpdesk.exports.casual-staff');

    Route::get('/helpdesk/exports/briefing', BriefingExportController::class)
        ->name('helpdesk.exports.briefing');

    Route::get('/helpdesk/exports/briefing-scores', BriefingScoreExportController::class)
        ->name('helpdesk.exports.briefing-scores');

    Route::get('/casual/exports/briefing-score-pdf', BriefingScorePdfController::class)
        ->name('casual.exports.briefing-score-pdf');

    Route::get('/rnd-projects/{project}/products/{product}/bom/export-pdf', RndProductBomPdfController::class)
        ->name('helpdesk.rnd-products.bom-pdf');

    Route::get('/rnd-projects/{project}/products/{product}/materials/export', RndProductEsbMaterialExportController::class)
        ->name('helpdesk.rnd-products.esb-materials-export');

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
