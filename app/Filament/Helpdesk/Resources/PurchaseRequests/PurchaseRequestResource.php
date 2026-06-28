<?php

namespace App\Filament\Helpdesk\Resources\PurchaseRequests;

use App\Enums\PurchaseRequestStatus;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\PurchaseRequests\Pages\EditPurchaseRequest;
use App\Filament\Helpdesk\Resources\PurchaseRequests\Pages\ListPurchaseRequests;
use App\Filament\Helpdesk\Resources\PurchaseRequests\Schemas\PurchaseRequestForm;
use App\Filament\Helpdesk\Resources\PurchaseRequests\Tables\PurchaseRequestsTable;
use App\Models\PurchaseRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class PurchaseRequestResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'purchase requests';

    protected static ?string $model = PurchaseRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Pengajuan Pembelian';

    protected static ?string $pluralModelLabel = 'Pengajuan Pembelian';

    public static function form(Schema $schema): Schema
    {
        return PurchaseRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseRequests::route('/'),
            'edit' => EditPurchaseRequest::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) PurchaseRequest::where('status', PurchaseRequestStatus::Submitted)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
