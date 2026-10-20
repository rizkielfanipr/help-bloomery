<?php

namespace App\Services\Monitoring;

use App\Filament\Helpdesk\Pages\EditBomAdjustmentPage;
use App\Filament\Helpdesk\Pages\ViewProjectProductPage;
use App\Filament\Helpdesk\Resources\BulkProductSubmissions\BulkProductSubmissionResource;
use App\Filament\Helpdesk\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Helpdesk\Resources\QualityControlItemJournals\QualityControlItemJournalResource;
use App\Models\BulkProductSubmission;
use App\Models\GoodsReceipt;
use App\Models\MonitoringTransaction;
use App\Models\QualityControlItemJournal;
use App\Models\RndBomChangeLog;
use App\Models\RndProductEsbMaterial;
use App\Models\RndProjectBom;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Resolves the authoritative domain record of a monitoring transaction to a label and, when the
 * current user may open it, a link. Only allow-listed model types are resolved.
 */
class MonitoringLocalRecordLinker
{
    /** @var list<class-string<Model>> */
    private const ALLOWED = [
        BulkProductSubmission::class,
        GoodsReceipt::class,
        QualityControlItemJournal::class,
        RndBomChangeLog::class,
        RndProductEsbMaterial::class,
        RndProjectBom::class,
    ];

    /** @return array{label: string, url: ?string, missing: bool}|null */
    public function resolve(MonitoringTransaction $transaction): ?array
    {
        $type = $transaction->local_model_type;
        if (! $type || ! $transaction->local_model_id || ! in_array($type, self::ALLOWED, true)) {
            return null;
        }

        $record = $type::query()->find($transaction->local_model_id);
        if (! $record) {
            return ['label' => class_basename($type).' #'.$transaction->local_model_id, 'url' => null, 'missing' => true];
        }

        try {
            return match (true) {
                $record instanceof GoodsReceipt => ['label' => 'Penerimaan PO '.$record->reference_number, 'url' => GoodsReceiptResource::canView($record) ? GoodsReceiptResource::getUrl('view', ['record' => $record]) : null, 'missing' => false],
                $record instanceof QualityControlItemJournal => ['label' => 'Item Journal '.($record->item_journal_number ?: '#'.$record->id), 'url' => QualityControlItemJournalResource::canView($record) ? QualityControlItemJournalResource::getUrl('view', ['record' => $record]) : null, 'missing' => false],
                $record instanceof BulkProductSubmission => ['label' => 'Bulk Product '.($record->product_name ?: '#'.$record->id), 'url' => BulkProductSubmissionResource::canView($record) ? BulkProductSubmissionResource::getUrl('view', ['record' => $record]) : null, 'missing' => false],
                $record instanceof RndBomChangeLog => ['label' => 'Change Log BOM '.($record->bom_code ?: '#'.$record->esb_bom_id), 'url' => EditBomAdjustmentPage::canAccess() ? EditBomAdjustmentPage::getUrl(['bomId' => $record->esb_bom_id]) : null, 'missing' => false],
                $record instanceof RndProductEsbMaterial => ['label' => 'Bahan R&D '.$record->product_name, 'url' => auth()->user()?->can('view rnd projects') && $record->product ? ViewProjectProductPage::getUrl(['project' => $record->product->rnd_project_id, 'product' => $record->rnd_project_product_id]) : null, 'missing' => false],
                $record instanceof RndProjectBom => ['label' => 'BOM Project '.($record->bom_code ?: '#'.$record->esb_bom_id), 'url' => null, 'missing' => false],
                default => null,
            };
        } catch (Throwable) {
            return ['label' => class_basename($type).' #'.$transaction->local_model_id, 'url' => null, 'missing' => false];
        }
    }
}
