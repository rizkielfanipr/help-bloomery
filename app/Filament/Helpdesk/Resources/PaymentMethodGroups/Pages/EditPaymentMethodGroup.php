<?php

namespace App\Filament\Helpdesk\Resources\PaymentMethodGroups\Pages;

use App\Filament\Helpdesk\Resources\PaymentMethodGroups\PaymentMethodGroupResource;
use App\Models\EsbPaymentMethodCache;
use App\Models\PaymentMethodGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPaymentMethodGroup extends EditRecord
{
    protected static string $resource = PaymentMethodGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $selectedIds = $this->record->items->pluck('esb_payment_method_id')->all();

        EsbPaymentMethodCache::query()
            ->join('branches', 'esb_payment_method_cache.branch_code', '=', 'branches.esb_branch_code')
            ->whereIn('esb_payment_method_cache.esb_payment_method_id', $selectedIds)
            ->select(['esb_payment_method_cache.esb_payment_method_id', 'branches.esb_comcode'])
            ->get()
            ->groupBy('esb_comcode')
            ->each(function ($methods, $comcode) use (&$data) {
                $data["payment_ids_{$comcode}"] = $methods
                    ->pluck('esb_payment_method_id')
                    ->unique()
                    ->map(fn ($id) => (string) $id)
                    ->values()
                    ->all();
            });

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncItems($this->record);
    }

    private function syncItems(PaymentMethodGroup $group): void
    {
        $state = $this->form->getState();

        $selectedIds = collect($state)
            ->filter(fn ($v, $k) => str_starts_with($k, 'payment_ids_') && is_array($v))
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $nameMap = EsbPaymentMethodCache::whereIn('esb_payment_method_id', $selectedIds)
            ->get()
            ->unique('esb_payment_method_id')
            ->pluck('esb_payment_method_name', 'esb_payment_method_id');

        $group->items()->delete();
        foreach ($selectedIds as $id) {
            $group->items()->create([
                'esb_payment_method_id' => $id,
                'esb_payment_method_name' => $nameMap[$id] ?? '',
            ]);
        }
    }
}
