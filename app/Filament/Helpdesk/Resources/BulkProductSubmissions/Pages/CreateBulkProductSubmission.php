<?php

namespace App\Filament\Helpdesk\Resources\BulkProductSubmissions\Pages;

use App\Actions\SubmitBulkProductAction;
use App\Filament\Helpdesk\Resources\BulkProductSubmissions\BulkProductSubmissionResource;
use App\Models\BulkProductSubmission;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateBulkProductSubmission extends CreateRecord
{
    protected static string $resource = BulkProductSubmissionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $targets = array_values(array_intersect(BulkProductSubmission::COMCODES, $data['target_comcodes'] ?? []));
        if ($targets === [] || count($targets) !== count($data['target_comcodes'] ?? [])) {
            throw ValidationException::withMessages(['target_comcodes' => 'Pilih minimal satu comcode yang didukung.']);
        }

        $details = $data['payload']['productDetails'] ?? [];
        $baseUnits = collect($details)->where('isBase', true);
        $stockUnits = collect($details)->where('isStock', true);
        if ($baseUnits->count() !== 1) {
            throw ValidationException::withMessages(['payload.productDetails' => 'Harus ada tepat satu base unit.']);
        }
        if ($stockUnits->count() !== 1) {
            throw ValidationException::withMessages(['payload.productDetails' => 'Harus ada tepat satu stock unit.']);
        }
        if ((float) ($baseUnits->first()['qty'] ?? 0) !== 1.0) {
            throw ValidationException::withMessages(['payload.productDetails' => 'Qty base unit harus 1.']);
        }

        $duplicateUomIds = collect($details)
            ->pluck('uomID')
            ->filter(fn (mixed $uomId): bool => filled($uomId))
            ->map(fn (mixed $uomId): int => (int) $uomId)
            ->duplicatesStrict();
        if ($duplicateUomIds->isNotEmpty()) {
            throw ValidationException::withMessages(['payload.productDetails' => 'Unit tidak boleh dipilih lebih dari satu kali.']);
        }

        $duplicateSkus = collect($details)
            ->pluck('sku')
            ->map(fn (mixed $sku): string => mb_strtoupper(trim((string) $sku)))
            ->filter()
            ->duplicatesStrict();
        if ($duplicateSkus->isNotEmpty()) {
            throw ValidationException::withMessages(['payload.productDetails' => 'SKU unit tidak boleh sama.']);
        }

        if (($data['operation'] ?? null) === 'update') {
            foreach ($targets as $comcode) {
                if ((int) data_get($data, "remote_product_ids.{$comcode}", 0) < 1) {
                    throw ValidationException::withMessages(["remote_product_ids.{$comcode}" => "Product ID {$comcode} wajib diisi."]);
                }
                foreach ($details as $index => $detail) {
                    if ((int) data_get($detail, "productDetailIDs.{$comcode}", 0) < 1) {
                        throw ValidationException::withMessages(["payload.productDetails.{$index}.productDetailIDs.{$comcode}" => "Detail ID {$comcode} wajib diisi."]);
                    }
                }
            }
        }

        $data['target_comcodes'] = $targets;
        $data['product_code'] = $data['payload']['productCode'] ?? null;
        $data['product_name'] = $data['payload']['productName'];
        $data['status'] = 'pending';
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        app(SubmitBulkProductAction::class)->execute($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('view', ['record' => $this->record]);
    }
}
