<?php

namespace App\Filament\Helpdesk\Resources\PaymentMethodGroups;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\PaymentMethodGroups\Pages\CreatePaymentMethodGroup;
use App\Filament\Helpdesk\Resources\PaymentMethodGroups\Pages\EditPaymentMethodGroup;
use App\Filament\Helpdesk\Resources\PaymentMethodGroups\Pages\ListPaymentMethodGroups;
use App\Filament\Helpdesk\Resources\PaymentMethodGroups\Schemas\PaymentMethodGroupForm;
use App\Filament\Helpdesk\Resources\PaymentMethodGroups\Tables\PaymentMethodGroupsTable;
use App\Models\PaymentMethodGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PaymentMethodGroupResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'payment method groups';

    protected static ?string $model = PaymentMethodGroup::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Payment Grouping';

    protected static ?string $pluralModelLabel = 'Payment Grouping';

    public static function form(Schema $schema): Schema
    {
        return PaymentMethodGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentMethodGroupsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentMethodGroups::route('/'),
            'create' => CreatePaymentMethodGroup::route('/create'),
            'edit' => EditPaymentMethodGroup::route('/{record}/edit'),
        ];
    }
}
