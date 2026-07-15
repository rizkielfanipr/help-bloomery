<?php

namespace App\Filament\Helpdesk\Resources\PaymentMethodGroups\Schemas;

use App\Models\EsbPaymentMethodCache;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentMethodGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        $byComcode = EsbPaymentMethodCache::query()
            ->join('branches', 'esb_payment_method_cache.branch_code', '=', 'branches.esb_branch_code')
            ->select([
                'esb_payment_method_cache.esb_payment_method_id',
                'esb_payment_method_cache.esb_payment_method_name',
                'esb_payment_method_cache.esb_payment_method_type_name',
                'branches.esb_comcode',
            ])
            ->orderBy('branches.esb_comcode')
            ->orderBy('esb_payment_method_cache.esb_payment_method_type_name')
            ->orderBy('esb_payment_method_cache.esb_payment_method_name')
            ->get()
            ->groupBy('esb_comcode');

        $comcodeComponents = $byComcode->map(function ($methods, $comcode) {
            $options = $methods
                ->unique('esb_payment_method_id')
                ->mapWithKeys(fn ($item) => [
                    (string) $item->esb_payment_method_id => $item->esb_payment_method_name
                        .($item->esb_payment_method_type_name ? " ({$item->esb_payment_method_type_name})" : ''),
                ])
                ->all();

            return Section::make($comcode)
                ->collapsible()
                ->collapsed(false)
                ->schema([
                    CheckboxList::make("payment_ids_{$comcode}")
                        ->label('')
                        ->options($options)
                        ->bulkToggleable()
                        ->columns(3)
                        ->gridDirection('row')
                        ->dehydrated(false),
                ]);
        })->values()->all();

        $paymentSection = Section::make('ESB Payment Methods')
            ->description($byComcode->isEmpty()
                ? 'No data yet. Use the "Sync ESB" button on the list page first.'
                : null)
            ->schema($byComcode->isEmpty()
                ? []
                : $comcodeComponents);

        return $schema
            ->components([
                Section::make('Group Info')
                    ->schema([
                        TextInput::make('name')
                            ->label('Group Name')
                            ->placeholder('e.g. QRIS, Cash, Bank Transfer')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('sort_order')
                            ->label('Display Order')
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->minValue(0),
                    ])
                    ->columns(2),

                $paymentSection,
            ]);
    }
}
