<?php

namespace App\Filament\Helpdesk\Resources\ReviewSourcings;

use App\Enums\MaterialSourcingStatus;
use App\Filament\Helpdesk\Resources\MaterialSourcings\MaterialSourcingActions;
use App\Filament\Helpdesk\Resources\MaterialSourcings\MaterialSourcingResource;
use App\Filament\Helpdesk\Resources\ReviewSourcings\Pages\ListReviewSourcings;
use App\Models\RndProductEsbMaterial;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * R&D work queue for sourcing results (Purchasing works in Sourcing Bahan): confirm sample receipt,
 * evaluate the sample — which selects the supplier that goes to the Finance Pricelist — reopen
 * that approval, and approve or reject the supplier of legacy/standalone sourcing.
 */
class ReviewSourcingResource extends Resource
{
    /**
     * Statuses waiting for an R&D decision.
     *
     * @var list<MaterialSourcingStatus>
     */
    public const NEEDS_ACTION_STATUSES = [
        MaterialSourcingStatus::SampleHandedOver,
        MaterialSourcingStatus::SampleReceived,
        MaterialSourcingStatus::UnderRndReview,
        MaterialSourcingStatus::PendingRndReview,
    ];

    /** @var list<string> */
    private const PERMISSIONS = ['receive material samples', 'review material samples as rnd', 'reopen material sample approval', 'review material sourcing as rnd'];

    protected static ?string $model = RndProductEsbMaterial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Research & Development';

    protected static ?string $navigationLabel = 'Review Sourcing';

    protected static ?string $modelLabel = 'Review Sourcing';

    protected static ?string $pluralModelLabel = 'Review Sourcing';

    protected static ?string $slug = 'review-sourcing';

    public static function canViewAny(): bool
    {
        return auth()->user()?->canAny(self::PERMISSIONS) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => self::scopeReviewQueue($query))
            ->columns(MaterialSourcingResource::queueColumns())
            ->filters(MaterialSourcingResource::queueFilters(), layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->defaultSort('updated_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50, 100])
            ->recordActions([
                MaterialSourcingActions::viewSourcing(),
                MaterialSourcingActions::receiveSample(),
                MaterialSourcingActions::evaluateSample(),
                MaterialSourcingActions::reopenSampleApproval(),
                MaterialSourcingActions::reopenRejectedSourcing(),
                MaterialSourcingActions::approveRnd(),
                MaterialSourcingActions::rejectRnd(),
                MaterialSourcingActions::sampleHistory(),
            ])
            ->emptyStateHeading('Tidak ada sourcing yang menunggu R&D')
            ->emptyStateDescription('Sampel yang diserahkan Purchasing dan supplier yang perlu dipilih akan muncul di sini.');
    }

    /**
     * Sourcing that reached R&D: Purchasing proposed suppliers (legacy) or handed over a sample.
     */
    public static function scopeReviewQueue(Builder $query): Builder
    {
        return MaterialSourcingResource::scopeSourcingQueue($query)
            ->whereNotIn('sourcing_status', [
                MaterialSourcingStatus::NotStarted->value,
                MaterialSourcingStatus::NotRequired->value,
                MaterialSourcingStatus::WaitingPurchasing->value,
                MaterialSourcingStatus::SourcingInProgress->value,
                MaterialSourcingStatus::SamplePrepared->value,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['product.project'])
            ->withCount('sourcings');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviewSourcings::route('/'),
        ];
    }
}
