<?php

namespace App\Filament\Helpdesk\Resources\PurchaseRequests\Tables;

use App\Enums\PurchaseRequestStatus;
use App\Enums\PurchaseType;
use App\Models\Branch;
use App\Models\PurchaseRequest;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Pemohon')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('division')
                    ->label('Divisi')
                    ->searchable(),

                TextColumn::make('item_name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignCenter(),

                TextColumn::make('purchase_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (PurchaseType $state): string => $state->getLabel())
                    ->color(fn (PurchaseType $state): string|array|null => $state->getColor()),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (PurchaseRequestStatus $state): string => $state->getLabel())
                    ->color(fn (PurchaseRequestStatus $state): string|array|null => $state->getColor()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(PurchaseRequestStatus::cases())->mapWithKeys(
                        fn (PurchaseRequestStatus $s) => [$s->value => $s->getLabel()]
                    )),

                SelectFilter::make('branch_id')
                    ->label('Cabang')
                    ->options(Branch::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('purchase_type')
                    ->label('Jenis')
                    ->options(collect(PurchaseType::cases())->mapWithKeys(
                        fn (PurchaseType $t) => [$t->value => $t->getLabel()]
                    )),

                Filter::make('created_at')
                    ->label('Tanggal')
                    ->form([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn (Builder $q) => $q->whereDate('created_at', '>=', $data['from']))
                        ->when($data['until'], fn (Builder $q) => $q->whereDate('created_at', '<=', $data['until']))
                    )
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'Dari: '.Carbon::parse($data['from'])->isoFormat('D MMM Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Sampai: '.Carbon::parse($data['until'])->isoFormat('D MMM Y');
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                Action::make('advance')
                    ->label('Proses')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->iconButton()
                    ->tooltip('Proses ke status berikutnya')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (PurchaseRequest $record): string => 'Proses ke "'.$record->status->nextStatus()?->getLabel().'"?')
                    ->modalDescription(fn (PurchaseRequest $record): string => 'Status saat ini: '.$record->status->getLabel())
                    ->action(function (PurchaseRequest $record): void {
                        $next = $record->status->nextStatus();
                        if ($next !== null) {
                            $record->update([
                                'status' => $next->value,
                                'processed_by' => auth()->id(),
                            ]);
                        }
                    })
                    ->hidden(fn (PurchaseRequest $record): bool => $record->status->nextStatus() === null),

                Action::make('add_notes')
                    ->label('Catatan')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->iconButton()
                    ->tooltip('Tambah catatan admin')
                    ->color('warning')
                    ->form([
                        Textarea::make('admin_notes')
                            ->label('Catatan Admin')
                            ->default(fn (PurchaseRequest $record): ?string => $record->admin_notes)
                            ->rows(3),
                    ])
                    ->action(function (array $data, PurchaseRequest $record): void {
                        $record->update(['admin_notes' => $data['admin_notes'] ?: null]);
                    }),

                EditAction::make()
                    ->iconButton()
                    ->tooltip('Lihat Detail'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
