<?php

namespace App\Filament\Helpdesk\Resources\MaterialSourcings;

use App\Enums\MaterialSourcingStatus;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\MaterialSourcings\Pages\ListMaterialSourcings;
use App\Models\RndProductEsbMaterial;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
            ->columns([
                TextColumn::make('product.project.name')
                    ->label('Project')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable(),

                TextColumn::make('product_name')
                    ->label('Bahan')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('product_code')
                    ->label('Kode')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('sourcing_status')
                    ->label('Status Sourcing')
                    ->badge(),

                TextColumn::make('sourcings_count')
                    ->label('Supplier'),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                Action::make('view_sourcing')
                    ->label('Lihat Supplier')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->button()
                    ->outlined()
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->extraModalWindowAttributes(['class' => 'material-sourcing-modal'])
                    ->modalHeading(fn (RndProductEsbMaterial $record): string => 'Detail Supplier — '.$record->product_name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->visible(fn (RndProductEsbMaterial $record): bool => $record->sourcings_count > 0)
                    ->modalContent(fn (RndProductEsbMaterial $record) => view('filament.helpdesk.material-sourcings.view-sourcing', [
                        'record' => $record->load(['sourcings', 'selectedSourcing', 'rndReviewer', 'financeReviewer']),
                    ])),

                Action::make('manage_sourcing')
                    ->label('Kelola Sourcing')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->button()
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->extraModalWindowAttributes(['class' => 'material-sourcing-modal'])
                    ->modalHeading(fn (RndProductEsbMaterial $record): string => 'Kelola Supplier — '.$record->product_name)
                    ->modalSubmitActionLabel('Kirim ke RnD')
                    ->visible(fn (RndProductEsbMaterial $record): bool => auth()->user()?->can('submit material sourcing')
                        && in_array($record->sourcing_status, [MaterialSourcingStatus::NotStarted, MaterialSourcingStatus::Rejected], true))
                    ->fillForm(function (RndProductEsbMaterial $record): array {
                        $sourcings = $record->sourcings->map(fn ($s): array => [
                            'supplier_name' => $s->supplier_name,
                            'price' => $s->price,
                            'moq' => $s->moq,
                            'lead_time_days' => $s->lead_time_days,
                            'contact_name' => $s->contact_name,
                            'contact_phone' => $s->contact_phone,
                            'notes' => $s->notes,
                            'attachment_path' => $s->attachment_path,
                        ])->all();

                        return ['sourcings' => $sourcings ?: [[]]];
                    })
                    ->form([
                        Repeater::make('sourcings')
                            ->label('Daftar Supplier')
                            ->schema([
                                TextInput::make('supplier_name')->label('Nama Supplier')->required(),
                                TextInput::make('price')->label('Harga')->numeric()->required(),
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
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Tambah Supplier')
                            ->columnSpanFull(),
                    ])
                    ->action(function (RndProductEsbMaterial $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $record->sourcings()->delete();

                            foreach ($data['sourcings'] as $row) {
                                $record->sourcings()->create([
                                    ...$row,
                                    'submitted_by' => auth()->id(),
                                ]);
                            }

                            $record->update(['sourcing_status' => MaterialSourcingStatus::PendingRndReview]);
                        });

                        Notification::make()->title('Sourcing dikirim untuk review RnD')->success()->send();
                    }),

                Action::make('approve_rnd')
                    ->label('Setujui (RnD)')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->button()
                    ->modalHeading('Setujui Supplier Pilihan RnD')
                    ->modalSubmitActionLabel('Setujui & Kirim ke Finance')
                    ->visible(fn (RndProductEsbMaterial $record): bool => auth()->user()?->can('review material sourcing as rnd')
                        && $record->sourcing_status === MaterialSourcingStatus::PendingRndReview)
                    ->form([
                        Radio::make('sourcing_selected_id')
                            ->label('Pilih Supplier Terbaik')
                            ->options(fn (RndProductEsbMaterial $record): array => $record->sourcings
                                ->mapWithKeys(fn ($s): array => [
                                    $s->id => "{$s->supplier_name} — Rp".number_format((float) $s->price, 0, ',', '.')
                                        .($s->moq ? " (MOQ: {$s->moq})" : '')
                                        .($s->lead_time_days ? ", Lead Time: {$s->lead_time_days} hari" : ''),
                                ])
                                ->all())
                            ->required(),
                        Textarea::make('rnd_note')->label('Catatan'),
                    ])
                    ->action(function (RndProductEsbMaterial $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $record->update([
                                'sourcing_selected_id' => $data['sourcing_selected_id'],
                                'rnd_reviewed_by' => auth()->id(),
                                'rnd_reviewed_at' => now(),
                                'rnd_note' => $data['rnd_note'] ?? null,
                                'sourcing_status' => MaterialSourcingStatus::PendingFinanceReview,
                            ]);

                            $record->sourcingApprovals()->create([
                                'stage' => 'rnd',
                                'action' => 'approved',
                                'actor_id' => auth()->id(),
                                'notes' => $data['rnd_note'] ?? null,
                            ]);
                        });

                        Notification::make()->title('Sourcing disetujui, menunggu review Finance')->success()->send();
                    }),

                Action::make('reject_rnd')
                    ->label('Tolak (RnD)')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->button()
                    ->outlined()
                    ->modalHeading('Tolak Pengajuan Supplier')
                    ->modalSubmitActionLabel('Tolak & Kembalikan')
                    ->visible(fn (RndProductEsbMaterial $record): bool => auth()->user()?->can('review material sourcing as rnd')
                        && $record->sourcing_status === MaterialSourcingStatus::PendingRndReview)
                    ->form([
                        Textarea::make('rnd_note')->label('Alasan Penolakan')->required()->minLength(5),
                    ])
                    ->action(function (RndProductEsbMaterial $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $record->update([
                                'rnd_reviewed_by' => auth()->id(),
                                'rnd_reviewed_at' => now(),
                                'rnd_note' => $data['rnd_note'],
                                'sourcing_status' => MaterialSourcingStatus::Rejected,
                            ]);

                            $record->sourcingApprovals()->create([
                                'stage' => 'rnd',
                                'action' => 'rejected',
                                'actor_id' => auth()->id(),
                                'notes' => $data['rnd_note'],
                            ]);
                        });

                        Notification::make()->title('Sourcing ditolak, dikembalikan ke Purchasing')->warning()->send();
                    }),

                Action::make('approve_finance')
                    ->label('Setujui (Finance)')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->button()
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
                        DB::transaction(function () use ($record, $data): void {
                            $record->update([
                                'finance_reviewed_by' => auth()->id(),
                                'finance_reviewed_at' => now(),
                                'finance_note' => $data['finance_note'] ?? null,
                                'sourcing_status' => MaterialSourcingStatus::Approved,
                            ]);

                            $record->sourcingApprovals()->create([
                                'stage' => 'finance',
                                'action' => 'approved',
                                'actor_id' => auth()->id(),
                                'notes' => $data['finance_note'] ?? null,
                            ]);
                        });

                        Notification::make()->title('Sourcing disetujui Finance')->success()->send();
                    }),

                Action::make('reject_finance')
                    ->label('Tolak (Finance)')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->button()
                    ->outlined()
                    ->modalHeading('Kembalikan Pengajuan Supplier')
                    ->modalSubmitActionLabel('Tolak & Kembalikan ke RnD')
                    ->visible(fn (RndProductEsbMaterial $record): bool => auth()->user()?->can('review material sourcing as finance')
                        && $record->sourcing_status === MaterialSourcingStatus::PendingFinanceReview)
                    ->form([
                        Textarea::make('finance_note')->label('Alasan Penolakan')->required()->minLength(5),
                    ])
                    ->action(function (RndProductEsbMaterial $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $record->update([
                                'finance_reviewed_by' => auth()->id(),
                                'finance_reviewed_at' => now(),
                                'finance_note' => $data['finance_note'],
                                'sourcing_status' => MaterialSourcingStatus::PendingRndReview,
                            ]);

                            $record->sourcingApprovals()->create([
                                'stage' => 'finance',
                                'action' => 'rejected',
                                'actor_id' => auth()->id(),
                                'notes' => $data['finance_note'],
                            ]);
                        });

                        Notification::make()->title('Sourcing dikembalikan ke RnD')->warning()->send();
                    }),
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
            'index' => ListMaterialSourcings::route('/'),
        ];
    }
}
