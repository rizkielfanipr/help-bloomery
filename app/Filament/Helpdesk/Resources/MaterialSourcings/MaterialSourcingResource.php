<?php

namespace App\Filament\Helpdesk\Resources\MaterialSourcings;

use App\Actions\Rnd\Material\AddMaterialSupplierCandidatesAction;
use App\Actions\Rnd\Material\ReviewLegacyMaterialSourcingFinanceAction;
use App\Enums\MaterialSourcingStatus;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\MaterialSourcings\Pages\ListMaterialSourcings;
use App\Models\RndProductEsbMaterial;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class MaterialSourcingResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'material sourcings';

    protected static ?string $model = RndProductEsbMaterial::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'Sourcing Bahan';

    protected static ?string $modelLabel = 'Sourcing Bahan';

    protected static ?string $pluralModelLabel = 'Sourcing Bahan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => self::scopeSourcingQueue($query))
            ->columns(self::queueColumns())
            ->filters(self::queueFilters(), layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->defaultSort('updated_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50, 100])
            ->recordActions([
                MaterialSourcingActions::viewSourcing(),

                Action::make('manage_sourcing')
                    ->label(fn (RndProductEsbMaterial $record): string => $record->sourcings_count > 0 ? 'Tambah Supplier' : 'Kelola Sourcing')
                    ->icon('heroicon-o-plus-circle')
                    ->tooltip(fn (RndProductEsbMaterial $record): string => $record->sourcings_count > 0 ? 'Tambah Supplier' : 'Kelola Sourcing')
                    ->color('primary')
                    ->iconButton()
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->extraModalWindowAttributes(['class' => 'material-sourcing-modal'])
                    ->modalHeading(fn (RndProductEsbMaterial $record): string => ($record->sourcings_count > 0 ? 'Tambah Supplier — ' : 'Kelola Supplier — ').$record->product_name)
                    ->modalSubmitActionLabel('Kirim ke RnD')
                    ->visible(fn (RndProductEsbMaterial $record): bool => (auth()->user()?->can('submit material sourcing') ?? false)
                        && (! $record->isInWorkflow() || in_array($record->sourcing_status, [MaterialSourcingStatus::WaitingPurchasing, MaterialSourcingStatus::SourcingInProgress, MaterialSourcingStatus::ResampleRequested], true)))
                    ->fillForm(fn (): array => ['sourcings' => [[]]])
                    ->form([
                        Repeater::make('sourcings')
                            ->label('Daftar Supplier')
                            ->schema(self::supplierFormFields())
                            ->columns(2)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Tambah Supplier')
                            ->columnSpanFull(),
                    ])
                    ->action(function (RndProductEsbMaterial $record, array $data): void {
                        try {
                            app(AddMaterialSupplierCandidatesAction::class)->execute($record, auth()->user(), array_values($data['sourcings']));
                        } catch (ValidationException $exception) {
                            Notification::make()->title('Supplier tidak dapat ditambahkan')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();

                            return;
                        }

                        Notification::make()->title($record->isInWorkflow() ? 'Kandidat supplier disimpan' : 'Sourcing dikirim untuk review RnD')->success()->send();
                    }),

                // R&D receipt, evaluation, and legacy supplier approval live in Review Sourcing (R&D menu).
                MaterialSourcingActions::handOverSample(),
                MaterialSourcingActions::sampleHistory(),

                Action::make('approve_finance')
                    ->label('Setujui (Finance)')
                    ->icon('heroicon-o-check-circle')
                    ->tooltip('Setujui (Finance)')
                    ->color('success')
                    ->iconButton()
                    ->modalHeading('Persetujuan Supplier oleh Finance')
                    ->modalSubmitActionLabel('Setujui Supplier')
                    ->visible(fn (RndProductEsbMaterial $record): bool => auth()->user()?->can('review material sourcing as finance')
                        && $record->sourcing_status === MaterialSourcingStatus::PendingFinanceReview)
                    ->modalDescription(fn (RndProductEsbMaterial $record): string => $record->selectedSourcing
                        ? "Supplier terpilih: {$record->selectedSourcing->supplier_name} — Rp".number_format((float) $record->selectedSourcing->price, 0, ',', '.')
                        : '')
                    ->form([
                        Textarea::make('finance_note')->label('Catatan'),
                    ])
                    ->action(function (RndProductEsbMaterial $record, array $data): void {
                        try {
                            app(ReviewLegacyMaterialSourcingFinanceAction::class)->execute($record, auth()->user(), 'approve', $data['finance_note'] ?? null);
                        } catch (ValidationException $exception) {
                            Notification::make()->title('Sourcing tidak dapat diproses')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();

                            return;
                        }

                        Notification::make()->title('Sourcing disetujui Finance')->success()->send();
                    }),

                Action::make('reject_finance')
                    ->label('Tolak (Finance)')
                    ->icon('heroicon-o-x-circle')
                    ->tooltip('Tolak (Finance)')
                    ->color('danger')
                    ->iconButton()
                    ->modalHeading('Kembalikan Pengajuan Supplier')
                    ->modalSubmitActionLabel('Tolak & Kembalikan ke RnD')
                    ->visible(fn (RndProductEsbMaterial $record): bool => auth()->user()?->can('review material sourcing as finance')
                        && $record->sourcing_status === MaterialSourcingStatus::PendingFinanceReview)
                    ->form([
                        Textarea::make('finance_note')->label('Alasan Penolakan')->required()->minLength(5),
                    ])
                    ->action(function (RndProductEsbMaterial $record, array $data): void {
                        try {
                            app(ReviewLegacyMaterialSourcingFinanceAction::class)->execute($record, auth()->user(), 'reject', $data['finance_note']);
                        } catch (ValidationException $exception) {
                            Notification::make()->title('Sourcing tidak dapat diproses')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();

                            return;
                        }

                        Notification::make()->title('Sourcing dikembalikan ke RnD')->warning()->send();
                    }),
            ]);
    }

    /**
     * Rows that belong to the sourcing queues: no plain WIP (it never needs sourcing, §10.2) and
     * Master Bahan workflow rows only once the verified ESB sync created their Sourcing Request
     * (docs/material-sourcing-lifecycle-prd.md §15.2).
     */
    public static function scopeSourcingQueue(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('category_name')
                ->orWhere('category_name', '!=', 'Barang WIP')
                ->orWhere('sourcing_origin', RndProductEsbMaterial::SOURCING_ORIGIN_STANDALONE);
        })->where(fn (Builder $query) => $query
            ->whereNull('review_status')
            ->orWhereNotNull('sourcing_requested_at'));
    }

    /**
     * Columns shared with Review Sourcing; their names drive the inline header filters.
     *
     * @return list<TextColumn>
     */
    public static function queueColumns(): array
    {
        return [
            TextColumn::make('product.project.name')
                ->label('PROJECT')
                ->default('Mandiri')
                ->searchable()
                ->sortable(),

            TextColumn::make('product.name')
                ->label('PRODUK')
                ->default('Tanpa Project')
                ->searchable()
                ->sortable(),

            TextColumn::make('product_name')
                ->label('BAHAN')
                ->searchable()
                ->wrap(),

            TextColumn::make('product_code')
                ->label('KODE')
                ->state(fn (RndProductEsbMaterial $record): string => $record->isStandalone()
                    ? ($record->esb_product_code ?: $record->product_code)
                    : $record->product_code)
                ->badge()
                ->color('gray'),

            TextColumn::make('sourcing_status')
                ->label('STATUS SOURCING')
                ->badge()
                ->sortable(),

            TextColumn::make('sourcings_count')
                ->label('SUPPLIER'),

            TextColumn::make('updated_at')
                ->label('DIPERBARUI')
                ->date('d M Y')
                ->sortable(),
        ];
    }

    /**
     * Filters shared with Review Sourcing, rendered inline in the column headers.
     *
     * @return list<Filter|SelectFilter>
     */
    public static function queueFilters(): array
    {
        return [
            Filter::make('project_name')
                ->label('PROJECT')
                ->form([TextInput::make('value')->label('Project')])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when(filled($data['value'] ?? null), function (Builder $query) use ($data): Builder {
                        $term = trim((string) $data['value']);

                        return $query->where(fn (Builder $query) => $query
                            ->whereHas('product.project', fn (Builder $query) => $query->where('name', 'like', '%'.$term.'%'))
                            ->when(str_contains('mandiri', mb_strtolower($term)), fn (Builder $query) => $query
                                ->orWhere('sourcing_origin', RndProductEsbMaterial::SOURCING_ORIGIN_STANDALONE)));
                    })),

            Filter::make('product_name_filter')
                ->label('PRODUK')
                ->form([TextInput::make('value')->label('Produk')])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query
                        ->whereHas('product', fn (Builder $query) => $query
                            ->where('name', 'like', '%'.trim((string) $data['value']).'%')))),

            Filter::make('material_name')
                ->label('BAHAN')
                ->form([TextInput::make('value')->label('Bahan')])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query
                        ->where('product_name', 'like', '%'.trim((string) $data['value']).'%'))),

            Filter::make('product_code_filter')
                ->label('KODE')
                ->form([TextInput::make('value')->label('Kode')])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query
                        ->where(fn (Builder $query) => $query
                            ->where('product_code', 'like', '%'.trim((string) $data['value']).'%')
                            ->orWhere('esb_product_code', 'like', '%'.trim((string) $data['value']).'%')))),

            SelectFilter::make('sourcing_status')
                ->label('STATUS SOURCING')
                ->options(MaterialSourcingStatus::class),

            Filter::make('updated_at')
                ->label('DIPERBARUI')
                ->form([
                    DatePicker::make('from')->label('Dari Tanggal'),
                    DatePicker::make('until')->label('Sampai Tanggal'),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('updated_at', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('updated_at', '<=', $date))),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['product.project'])
            ->withCount('sourcings');
    }

    /** @return array<int, Component> */
    public static function supplierFormFields(): array
    {
        return [
            TextInput::make('supplier_name')->label('Nama Supplier')->required()->maxLength(255),
            TextInput::make('brand')->label('Merk')->maxLength(255),
            TextInput::make('price')->label('Harga')->numeric()->required()->gt(0),
            TextInput::make('moq')->label('MOQ')->placeholder('mis. 100 kg'),
            TextInput::make('lead_time_days')->label('Lead Time (hari)')->numeric()->integer(),
            TextInput::make('contact_name')->label('Nama Kontak'),
            TextInput::make('contact_phone')->label('Telepon Kontak'),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
            FileUpload::make('attachment_path')
                ->label('Lampiran Penawaran')
                ->disk('b2')
                ->directory('material-sourcing')
                ->columnSpanFull(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaterialSourcings::route('/'),
        ];
    }
}
