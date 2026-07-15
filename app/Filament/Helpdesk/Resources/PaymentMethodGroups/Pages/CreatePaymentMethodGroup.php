<?php

namespace App\Filament\Helpdesk\Resources\PaymentMethodGroups\Pages;

use App\Filament\Helpdesk\Resources\PaymentMethodGroups\PaymentMethodGroupResource;
use App\Models\EsbPaymentMethodCache;
use App\Models\PaymentMethodGroup;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentMethodGroup extends CreateRecord
{
    protected static string $resource = PaymentMethodGroupResource::class;

    protected function afterCreate(): void
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
