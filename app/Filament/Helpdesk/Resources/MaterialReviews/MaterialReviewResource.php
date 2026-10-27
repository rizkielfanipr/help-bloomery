<?php

namespace App\Filament\Helpdesk\Resources\MaterialReviews;

use App\Enums\MaterialEsbSyncStatus;
use App\Enums\MaterialReviewStatus;
use App\Enums\MonitoringTransactionStatus;
use App\Filament\Helpdesk\Resources\MaterialReviews\Pages\ListMaterialReviews;
use App\Filament\Helpdesk\Resources\MaterialReviews\Pages\ViewMaterialReview;
use App\Models\RndProductEsbMaterial;
use App\Services\Rnd\Material\MaterialClassification;
use App\Services\TableFilterOptions;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * IT → Review Data inbox (docs/products/rnd/material-sourcing-lifecycle/prd.md §22.2). Lists only
 * workflow materials; the default view shows submissions waiting for IT and ESB results that
 * need reconciliation. Payload/history is loaded on the detail page only.
 */
class MaterialReviewResource extends Resource
{
    protected static ?string $model = RndProductEsbMaterial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Information Technology';

    protected static ?string $navigationLabel = 'Review Data';

    protected static ?string $modelLabel = 'Review Data';

    protected static ?string $pluralModelLabel = 'Review Data';

    protected static ?string $slug = 'material-reviews';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('review rnd materials as it') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof RndProductEsbMaterial && $record->isInWorkflow() && static::canViewAny();
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('review_status');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select(['id', 'product_code', 'product_name', 'category_name', 'material_section', 'review_status', 'esb_sync_status', 'status', 'submission_version', 'created_by', 'updated_at'])
                ->with([
                    'creator:id,name',
                    // latestOfMany() self-joins the reviews table, so the selected columns must be
                    // table-qualified; MySQL rejects the bare names as ambiguous.
                    'latestReview' => fn ($query) => $query->select(collect(['id', 'rnd_product_esb_material_id', 'submission_version', 'submitted_by', 'submitted_at', 'claimed_by'])
                        ->map(fn (string $column): string => 'rnd_material_reviews.'.$column)->all()),
                    'latestReview.submitter:id,name',
                    'latestReview.claimer:id,name',
                ]))
            ->columns([
                TextColumn::make('product_code')->label('Kode')->searchable()->fontFamily('mono')->weight('bold'),
                TextColumn::make('product_name')->label('Nama')->searchable()->wrap()
                    ->description(fn (RndProductEsbMaterial $record): string => MaterialClassification::label($record)),
                TextColumn::make('review_status')->label('Review IT')->badge(),
                TextColumn::make('esb_sync_status')->label('ESB')->badge()->placeholder('-'),
                TextColumn::make('latestReview.submitter.name')->label('Pengaju')->placeholder('-')
                    ->description(fn (RndProductEsbMaterial $record): ?string => $record->latestReview?->claimer ? 'Direview: '.$record->latestReview->claimer->name : null),
                TextColumn::make('latestReview.submitted_at')->label('Diajukan')->dateTime('d M Y H:i', 'Asia/Jakarta')->placeholder('-'),
                TextColumn::make('submission_version')->label('Versi')->alignCenter(),
            ])
            ->filters([
                Filter::make('search')->label('Kode / Nama')
                    ->schema([TextInput::make('value')->label('Kode / Nama')])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query
                        ->where(fn (Builder $query) => $query
                            ->where('product_code', 'like', '%'.trim((string) $data['value']).'%')
                            ->orWhere('product_name', 'like', '%'.trim((string) $data['value']).'%')))),
                SelectFilter::make('review_status')->label('Status Review')->options(MaterialReviewStatus::options()),
                SelectFilter::make('esb_sync_status')->label('Status ESB')->options(MaterialEsbSyncStatus::options()),
                Filter::make('section')->label('Jenis')
                    ->schema([Select::make('value')->label('Jenis')->options(MaterialClassification::options())])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'wip' => $query->where(fn (Builder $query) => $query->where('category_name', 'like', '%wip%')->orWhere('product_code', 'like', 'BW%')),
                        'marketing' => $query->where(fn (Builder $query) => $query->where('material_section', 'marketing')->orWhere('category_name', 'like', '%marketing%')),
                        'packaging' => $query->where(fn (Builder $query) => $query->where('category_name', 'like', '%packaging%')->orWhere('category_name', 'like', '%kemasan%')->orWhere('product_code', 'like', 'PKG%')->orWhere('product_code', 'like', 'PAM%')),
                        'raw' => $query->where('category_name', 'not like', '%wip%')->where('product_code', 'not like', 'BW%')->where(fn (Builder $query) => $query->whereNull('material_section')->orWhere('material_section', '!=', 'marketing')),
                        default => $query,
                    }),
                SelectFilter::make('submitter')->label('Pengaju')
                    ->options(fn (): array => app(TableFilterOptions::class)->materialReviewSubmitters())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('reviews', fn (Builder $reviews) => $reviews->where('submitted_by', $data['value']))
                        : $query),
                Filter::make('submitted_at')->label('Tanggal Pengajuan')
                    ->schema([DatePicker::make('from')->label('Dari Tanggal'), DatePicker::make('until')->label('Sampai Tanggal')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereHas('reviews', fn (Builder $reviews) => $reviews->where('submitted_at', '>=', $date.' 00:00:00')))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereHas('reviews', fn (Builder $reviews) => $reviews->where('submitted_at', '<=', $date.' 23:59:59')))),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->recordActions([ViewAction::make()->label('Review')])
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Tidak ada Master Bahan yang menunggu IT')
            ->emptyStateDescription('Pengajuan R&D baru dan hasil ESB yang perlu rekonsiliasi akan muncul di sini.');
    }

    /**
     * Rows IT has to act on: submissions waiting for or under review, ESB pushes that failed or
     * need reconciliation, and `syncing` claims that never finished (finished transaction or stale).
     */
    public static function scopeNeedsItAction(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereIn('review_status', [MaterialReviewStatus::WaitingItReview->value, MaterialReviewStatus::UnderItReview->value])
            ->orWhereIn('esb_sync_status', [MaterialEsbSyncStatus::NeedsReconciliation->value, MaterialEsbSyncStatus::Failed->value])
            ->orWhere(fn (Builder $query) => $query
                ->where('esb_sync_status', MaterialEsbSyncStatus::Syncing->value)
                ->where(fn (Builder $query) => $query
                    ->where('esb_sync_claimed_at', '<=', now()->subMinutes(RndProductEsbMaterial::ESB_CLAIM_STALE_MINUTES))
                    ->orWhere(fn (Builder $query) => $query
                        ->where('esb_sync_claimed_at', '<=', now()->subMinutes(RndProductEsbMaterial::ESB_CLAIM_FINISHED_GRACE_MINUTES))
                        ->whereExists(fn ($transactions) => $transactions->selectRaw('1')->from('monitoring_transactions')
                            ->whereNull('monitoring_transactions.parent_id')
                            ->where('monitoring_transactions.local_model_type', (new RndProductEsbMaterial)->getMorphClass())
                            ->whereColumn('monitoring_transactions.local_model_id', 'rnd_product_esb_materials.id')
                            ->whereColumn('monitoring_transactions.created_at', '>=', 'rnd_product_esb_materials.esb_sync_claimed_at')
                            ->whereNotIn('monitoring_transactions.status', [MonitoringTransactionStatus::Pending->value, MonitoringTransactionStatus::Processing->value]))))));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaterialReviews::route('/'),
            'view' => ViewMaterialReview::route('/{record}'),
        ];
    }
}
