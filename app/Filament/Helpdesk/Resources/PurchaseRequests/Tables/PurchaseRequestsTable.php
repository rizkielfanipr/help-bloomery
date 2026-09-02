<?php

namespace App\Filament\Helpdesk\Resources\PurchaseRequests\Tables;

use App\Actions\ExportPurchaseRequestsXlsxAction;
use App\Enums\PurchaseRequestStatus;
use App\Enums\PurchaseType;
use App\Filament\Helpdesk\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\PurchaseRequest;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_code')
                    ->label('KODE')
                    ->getStateUsing(fn (PurchaseRequest $record): ?string => $record->purchase_request_number
                        ?: $record->code)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy('purchase_request_number', $direction)
                        ->orderBy('code', $direction))
                    ->placeholder('-')
                    ->copyable()
                    ->weight('semibold'),

                TextColumn::make('created_at')
                    ->label('TANGGAL')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('CABANG')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('item_name')
                    ->label('NAMA BARANG')
                    ->sortable()
                    ->wrap(),

                TextColumn::make('purchase_reason')
                    ->label('ALASAN PEMBELIAN')
                    ->limit(55)
                    ->tooltip(fn (PurchaseRequest $record): string => $record->purchase_reason)
                    ->wrap(),

                TextColumn::make('quantity')
                    ->label('QTY')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('purchase_type')
                    ->label('JENIS PEMBELIAN')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (PurchaseType $state): string => $state->getLabel())
                    ->color(fn (PurchaseType $state): string|array|null => $state->getColor()),

                TextColumn::make('status')
                    ->label('STATUS')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (PurchaseRequestStatus $state): string => $state->getLabel())
                    ->color(fn (PurchaseRequestStatus $state): string|array|null => $state->getColor()),
            ])
            ->filters([
                Filter::make('request_code')
                    ->form([TextInput::make('value')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['value'] ?? null), function (Builder $query) use ($data): Builder {
                            $search = trim((string) $data['value']);

                            return $query->where(function (Builder $query) use ($search): void {
                                $query
                                    ->where('purchase_request_number', 'like', "%{$search}%")
                                    ->orWhere('code', 'like', "%{$search}%");
                            });
                        })),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),

                Filter::make('item_name')
                    ->form([TextInput::make('value')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query
                            ->where('item_name', 'like', '%'.trim((string) $data['value']).'%'))),

                Filter::make('purchase_reason')
                    ->form([TextInput::make('value')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query
                            ->where('purchase_reason', 'like', '%'.trim((string) $data['value']).'%'))),

                Filter::make('quantity')
                    ->form([TextInput::make('value')->numeric()->minValue(1)])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query
                            ->where('quantity', (int) $data['value']))),

                SelectFilter::make('status')
                    ->label('STATUS')
                    ->options(collect(PurchaseRequestStatus::cases())->mapWithKeys(
                        fn (PurchaseRequestStatus $s) => [$s->value => $s->getLabel()]
                    ))
                    ->multiple(),

                SelectFilter::make('branch_id')
                    ->label('CABANG')
                    ->options(Branch::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('purchase_type')
                    ->label('JENIS PEMBELIAN')
                    ->options(collect(PurchaseType::cases())->mapWithKeys(
                        fn (PurchaseType $t) => [$t->value => $t->getLabel()]
                    )),

            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->tooltip('Lihat Detail'),

                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Hapus'),

            ])
            ->recordClasses(fn (PurchaseRequest $record): string => 'purchase-status-'.$record->status->value)
            ->toolbarActions([
                self::toggleFormAction(),

                Action::make('export_purchase_requests')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Export Excel berdasarkan rentang tanggal')
                    ->modalHeading('Export Permintaan Pembelian')
                    ->modalDescription('Kosongkan rentang tanggal untuk mengekspor seluruh data.')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('Tanggal Mulai')
                            ->native(false),
                        DatePicker::make('date_until')
                            ->label('Tanggal Akhir')
                            ->native(false)
                            ->afterOrEqual('date_from'),
                    ])
                    ->modalSubmitActionLabel('Download Excel')
                    ->action(fn (array $data): BinaryFileResponse => app(ExportPurchaseRequestsXlsxAction::class)->execute(
                        PurchaseRequestResource::getEloquentQuery(),
                        $data['date_from'] ?? null,
                        $data['date_until'] ?? null,
                    )),

                self::bulkTransitionAction(PurchaseRequestStatus::Approved, 'heroicon-o-check', 'success'),
                self::bulkTransitionAction(PurchaseRequestStatus::Rejected, 'heroicon-o-x-mark', 'danger'),
                self::bulkTransitionAction(PurchaseRequestStatus::Purchased, 'heroicon-o-shopping-bag', 'info'),
                self::bulkTransitionAction(PurchaseRequestStatus::Delivered, 'heroicon-o-truck', 'primary'),
                self::bulkTransitionAction(PurchaseRequestStatus::Completed, 'heroicon-o-check-badge', 'success'),

                DeleteBulkAction::make()
                    ->iconButton()
                    ->tooltip('Hapus data terpilih')
                    ->color('danger'),
            ])
            ->defaultSort('item_name', 'asc')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50, 100]);
    }

    private static function toggleFormAction(): Action
    {
        $isOpen = AppSetting::get('purchase_request_open', 'true') === 'true';

        return Action::make('toggle_form')
            ->label($isOpen ? 'Tutup Form' : 'Buka Form')
            ->icon($isOpen ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
            ->color($isOpen ? 'danger' : 'success')
            ->iconButton()
            ->tooltip($isOpen ? 'Tutup form pengajuan' : 'Buka form pengajuan')
            ->requiresConfirmation()
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalHeading($isOpen ? 'Tutup Form Pengajuan?' : 'Buka Form Pengajuan?')
            ->modalDescription($isOpen
                ? 'Pengguna tidak akan bisa mengajukan pembelian baru. Pastikan alasan penutupan sudah diisi.'
                : 'Pengguna kembali bisa mengajukan pembelian baru.')
            ->form($isOpen ? [
                Textarea::make('close_reason')
                    ->label('Alasan Penutupan')
                    ->placeholder('Contoh: Anggaran bulan ini sudah penuh')
                    ->required()
                    ->rows(3),
            ] : [])
            ->action(function (array $data) use ($isOpen): void {
                if ($isOpen) {
                    AppSetting::set('purchase_request_open', 'false');
                    AppSetting::set('purchase_request_close_reason', $data['close_reason']);
                } else {
                    AppSetting::set('purchase_request_open', 'true');
                    AppSetting::set('purchase_request_close_reason', null);
                }

                Notification::make()
                    ->title($isOpen ? 'Form pengajuan ditutup' : 'Form pengajuan dibuka')
                    ->success()
                    ->send();
            });
    }

    private static function commonStatus(Collection $records): ?PurchaseRequestStatus
    {
        if ($records->isEmpty()) {
            return null;
        }

        $statuses = $records
            ->map(fn (PurchaseRequest $record): PurchaseRequestStatus => $record->status)
            ->unique(fn (PurchaseRequestStatus $status): string => $status->value);

        return $statuses->count() === 1 ? $statuses->first() : null;
    }

    private static function bulkTransitionAction(PurchaseRequestStatus $target, string $icon, string $color): BulkAction
    {
        $source = match ($target) {
            PurchaseRequestStatus::Approved, PurchaseRequestStatus::Rejected => PurchaseRequestStatus::Submitted,
            PurchaseRequestStatus::Purchased => PurchaseRequestStatus::Approved,
            PurchaseRequestStatus::Delivered => PurchaseRequestStatus::Purchased,
            PurchaseRequestStatus::Completed => PurchaseRequestStatus::Delivered,
            PurchaseRequestStatus::Submitted => null,
        };

        $action = BulkAction::make('set_'.$target->value)
            ->label($target->getLabel())
            ->icon($icon)
            ->color($color)
            ->visible(fn (Collection $records): bool => $source !== null && self::commonStatus($records) === $source);

        if ($target === PurchaseRequestStatus::Rejected) {
            $action->form([
                Textarea::make('admin_notes')
                    ->label('Rejection Reason')
                    ->required()
                    ->rows(3),
            ]);
        } else {
            $action
                ->requiresConfirmation()
                ->modalHeading('Ubah status menjadi '.$target->getLabel().'?');
        }

        return $action
            ->action(function (Collection $records, array $data) use ($target): void {
                $currentStatus = self::commonStatus($records);

                if ($currentStatus === null || ! $currentStatus->canTransitionTo($target)) {
                    throw ValidationException::withMessages([
                        'status' => 'Selected requests must have the same status and follow the status sequence.',
                    ]);
                }

                if ($target === PurchaseRequestStatus::Rejected && blank(trim((string) ($data['admin_notes'] ?? '')))) {
                    throw ValidationException::withMessages([
                        'admin_notes' => 'Rejection reason is required.',
                    ]);
                }

                $records->each(fn (PurchaseRequest $record) => $record->update([
                    'status' => $target->value,
                    'admin_notes' => $target === PurchaseRequestStatus::Rejected
                        ? trim((string) $data['admin_notes'])
                        : null,
                    'processed_by' => auth()->id(),
                ]));
            })
            ->deselectRecordsAfterCompletion();
    }
}
