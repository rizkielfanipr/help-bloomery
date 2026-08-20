<?php

namespace App\Filament\Helpdesk\Resources\QualityControlAudits;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\QualityControlAudits\Pages\ListQualityControlAudits;
use App\Filament\Helpdesk\Resources\QualityControlAudits\Pages\ViewQualityControlAudit;
use App\Filament\Helpdesk\Resources\QualityControlAudits\RelationManagers\ItemsRelationManager;
use App\Filament\Helpdesk\Resources\QualityControlAudits\Schemas\QualityControlAuditForm;
use App\Filament\Helpdesk\Resources\QualityControlAudits\Tables\QualityControlAuditsTable;
use App\Models\QualityControlAudit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QualityControlAuditResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'quality control audits';

    protected static ?string $model = QualityControlAudit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Quality Control';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Audit Quality Control';

    protected static ?string $pluralModelLabel = 'Audit Quality Control';

    public static function form(Schema $schema): Schema
    {
        return QualityControlAuditForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QualityControlAuditsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['branch', 'auditor']);
        $user = auth()->user();

        if ($user && ! $user->canAccessAllBranches()) {
            $query->whereIn('branch_id', $user->accessibleBranchIds());
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQualityControlAudits::route('/'),
            'view' => ViewQualityControlAudit::route('/{record}'),
        ];
    }
}
