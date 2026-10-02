<?php

namespace App\Filament\Helpdesk\Resources\StoreSalesOrders\Pages;

use App\Actions\StoreSalesOrder\RefreshStoreSalesOrderSnapshotAction;
use App\Actions\StoreSalesOrder\UpdateStoreSalesOrderAction;
use App\Actions\StoreSalesOrder\UpdateStoreSalesOrderStatusAction;
use App\Enums\StoreSalesOrderEventType;
use App\Enums\StoreSalesOrderProductType;
use App\Enums\StoreSalesOrderStatus;
use App\Filament\Helpdesk\Resources\StoreSalesOrders\StoreSalesOrderResource;
use App\Models\StoreSalesOrder;
use App\Models\StoreSalesOrderActivity;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class ViewStoreSalesOrder extends ViewRecord
{
    protected static string $resource = StoreSalesOrderResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->load(['branch', 'submitter', 'updater', 'items', 'activities.creator']);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->product_sales_number;
    }

    /**
     * Form schema state can hand conditional closures (and the action's own $data payload) either
     * the raw scalar or an already-cast enum instance, depending on how Filament's Select resolves
     * state for a field whose options() came from a backed enum class — normalizing here keeps
     * every `$get(...) === Enum::Case->value` comparison correct regardless of which shape it gets.
     */
    private static function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** @return list<array{name: string, url: string}> */
    public function getAttachmentLinks(): array
    {
        return collect($this->record->attachment_paths ?? [])
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'url' => route('helpdesk.store-sales-orders.attachments.show', ['path' => $path]),
            ])
            ->all();
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sales Order ESB')->schema([
                Grid::make(2)->schema([
                    TextEntry::make('product_sales_number')->label('Nomor Sales Order'),
                    TextEntry::make('branch_name_snapshot')->label('Branch ESB'),
                ]),
                Grid::make(2)->schema([
                    TextEntry::make('product_sales_date')->label('Tanggal Sales Order')->dateTime('d M Y')->placeholder('-'),
                    TextEntry::make('required_date')->label('Required Date')->dateTime('d M Y')->placeholder('-'),
                ]),
                Grid::make(2)->schema([
                    TextEntry::make('customer_name_snapshot')->label('Customer')->placeholder('-'),
                    TextEntry::make('customer_id_snapshot')->label('Customer ID')->placeholder('-'),
                ]),
                TextEntry::make('customer_address_snapshot')->label('Alamat')->placeholder('-')->columnSpanFull(),
                Grid::make(2)->schema([
                    TextEntry::make('product_sales_total')->label('Total')
                        ->formatStateUsing(fn (StoreSalesOrder $record): string => $record->product_sales_total !== null
                            ? trim(($record->currency_sign ?? '').' '.number_format((float) $record->product_sales_total, 0, ',', '.'))
                            : '-'),
                    TextEntry::make('esb_status_name')->label('Status ESB')->placeholder('-'),
                ]),
                TextEntry::make('last_verified_at')->label('Terakhir Diverifikasi')->dateTime('d M Y H:i'),
            ]),

            Section::make('Informasi Operasional')->schema([
                Grid::make(2)->schema([
                    TextEntry::make('phone_number')->label('Nomor HP')->placeholder('-'),
                    TextEntry::make('ordered_by')->label('Order By')->placeholder('-'),
                ]),
                Grid::make(2)->schema([
                    TextEntry::make('event_type')->label('Jenis Acara')->placeholder('-')
                        ->formatStateUsing(fn (?StoreSalesOrderEventType $state, StoreSalesOrder $record) => $state === StoreSalesOrderEventType::Other
                            ? ($record->event_type_other ?: $state->getLabel())
                            : $state?->getLabel()),
                    TextEntry::make('delivery_time')->label('Jam Pengiriman')->placeholder('-'),
                ]),
                TextEntry::make('preparation_notes')->label('Catatan Persiapan')->placeholder('-')->columnSpanFull(),
            ]),

            Section::make('Kebutuhan Produk')->schema([
                RepeatableEntry::make('items')
                    ->label('')
                    ->schema([
                        TextEntry::make('product_type')->label('Produk')
                            ->formatStateUsing(fn (StoreSalesOrderProductType $state) => $state->getLabel()),
                        TextEntry::make('custom_detail')->label('Detail Custom')->placeholder('-'),
                        TextEntry::make('quantity')->label('Jumlah'),
                        TextEntry::make('notes')->label('Catatan')->placeholder('-'),
                    ])
                    ->columns(4),
            ])->visible(fn (StoreSalesOrder $record): bool => $record->items->isNotEmpty()),

            Section::make('Attachment')
                ->schema([
                    RepeatableEntry::make('attachmentLinks')
                        ->label('')
                        ->state(fn (): array => $this->getAttachmentLinks())
                        ->schema([
                            TextEntry::make('name')->label('')->url(fn (array $state): ?string => $state['url'] ?? null, shouldOpenInNewTab: true),
                        ]),
                ])
                ->visible(fn (StoreSalesOrder $record): bool => filled($record->attachment_paths)),

            Section::make('Activity Timeline')->schema([
                RepeatableEntry::make('activities')
                    ->label('')
                    ->schema([
                        TextEntry::make('description')
                            ->label('')
                            ->state(fn (StoreSalesOrderActivity $record): string => $this->describeActivity($record)),
                        TextEntry::make('created_at')->label('')->dateTime('d M Y H:i')
                            ->formatStateUsing(fn ($state, StoreSalesOrderActivity $record): string => ($record->creator?->name ?? 'Sistem').' · '.$state->format('d M Y H:i')),
                    ])
                    ->contained(false),
            ])->visible(fn (StoreSalesOrder $record): bool => $record->activities->isNotEmpty()),
        ]);
    }

    private function describeActivity(StoreSalesOrderActivity $activity): string
    {
        return match ($activity->activity_type) {
            StoreSalesOrderActivity::TYPE_CREATED => 'Store Sales Order dibuat.',
            StoreSalesOrderActivity::TYPE_STATUS_CHANGED => sprintf(
                'Status diubah dari %s ke %s.%s',
                $activity->previous_status ? StoreSalesOrderStatus::from($activity->previous_status)->getLabel() : '-',
                $activity->new_status ? StoreSalesOrderStatus::from($activity->new_status)->getLabel() : '-',
                $activity->notes ? ' Alasan: '.$activity->notes : '',
            ),
            StoreSalesOrderActivity::TYPE_INFO_UPDATED => 'Informasi operasional diperbarui.',
            StoreSalesOrderActivity::TYPE_SNAPSHOT_REFRESHED => 'Snapshot ESB diperbarui.',
            default => $activity->activity_type,
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit_info')
                ->label('Edit Informasi')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->visible(fn (StoreSalesOrder $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->fillForm(fn (StoreSalesOrder $record): array => [
                    'phone_number' => $record->phone_number,
                    'ordered_by' => $record->ordered_by,
                    'event_type' => $record->event_type?->value,
                    'event_type_other' => $record->event_type_other,
                    'delivery_time' => $record->delivery_time,
                    'preparation_notes' => $record->preparation_notes,
                    'items' => $record->items->map(fn ($item) => [
                        'product_type' => $item->product_type->value,
                        'custom_detail' => $item->custom_detail,
                        'quantity' => $item->quantity,
                        'notes' => $item->notes,
                    ])->all(),
                ])
                ->schema([
                    TextInput::make('phone_number')->label('Nomor HP')->maxLength(50),
                    TextInput::make('ordered_by')->label('Order By')->maxLength(150),
                    Select::make('event_type')->label('Jenis Acara')->options(StoreSalesOrderEventType::class)->native(false)->live(),
                    TextInput::make('event_type_other')->label('Keterangan Acara Lainnya')->maxLength(150)
                        ->visible(fn (Get $get): bool => self::enumValue($get('event_type')) === StoreSalesOrderEventType::Other->value),
                    TextInput::make('delivery_time')->label('Jam Pengiriman')->type('time'),
                    Textarea::make('preparation_notes')->label('Catatan Persiapan')->rows(3)->maxLength(2000),
                    Repeater::make('items')
                        ->label('Kebutuhan Produk')
                        ->schema([
                            Select::make('product_type')->label('Pilihan Produk')->options(StoreSalesOrderProductType::class)->required()->native(false)->live(),
                            // Enforcement lives in UpdateStoreSalesOrderAction (not a field-level
                            // ->required() closure here): a Livewire-validation failure on a field
                            // whose required-ness depends on sibling state leaves the action mount
                            // "stuck" open for any next action call in the same test/request, which
                            // broke a sequential status-transition flow during this feature's own
                            // tests — the Action's ValidationException is caught and surfaced the
                            // same way as every other business-rule failure here.
                            TextInput::make('custom_detail')->label('Detail Custom')->maxLength(500)
                                ->visible(fn (Get $get): bool => self::enumValue($get('product_type')) === StoreSalesOrderProductType::Custom->value),
                            TextInput::make('quantity')->label('Jumlah')->numeric()->required()->minValue(0.01),
                            TextInput::make('notes')->label('Catatan')->maxLength(500),
                        ])
                        ->minItems(1)
                        ->required()
                        ->columns(2),
                ])
                ->action(function (array $data, StoreSalesOrder $record): void {
                    // `event_type` has no model cast (only `items.*.product_type` and
                    // `operational_status` do), so an already-cast enum instance from this Select
                    // must be unwrapped before it reaches the Action — otherwise it would be
                    // written to an uncast string column as a PHP object instead of its value.
                    $data['event_type'] = self::enumValue($data['event_type'] ?? null);

                    try {
                        app(UpdateStoreSalesOrderAction::class)->execute($record, $data, auth()->user());
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Gagal menyimpan perubahan')
                            ->body(collect($exception->errors())->flatten()->implode(' '))
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->refresh()->load(['items', 'activities.creator']);

                    Notification::make()->title('Informasi berhasil diperbarui')->success()->send();
                }),

            Action::make('change_status')
                ->label('Ubah Status')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('primary')
                ->visible(fn (StoreSalesOrder $record): bool => auth()->user()?->can('updateStatus', $record) ?? false)
                ->fillForm(fn (StoreSalesOrder $record): array => [
                    'operational_status' => $record->operational_status->value,
                    'cancellation_reason' => $record->cancellation_reason,
                ])
                ->schema([
                    Select::make('operational_status')
                        ->label('Status')
                        ->options(StoreSalesOrderStatus::class)
                        ->required()
                        ->native(false)
                        ->live(),
                    // See the matching comment on `custom_detail` above: enforcement lives in
                    // UpdateStoreSalesOrderStatusAction, not a field-level ->required() closure.
                    Textarea::make('cancellation_reason')
                        ->label('Alasan Pembatalan')
                        ->rows(3)
                        ->maxLength(2000)
                        ->visible(fn (Get $get): bool => self::enumValue($get('operational_status')) === StoreSalesOrderStatus::Cancelled->value),
                ])
                ->action(function (array $data, StoreSalesOrder $record): void {
                    try {
                        app(UpdateStoreSalesOrderStatusAction::class)->execute(
                            $record,
                            StoreSalesOrderStatus::from(self::enumValue($data['operational_status'])),
                            $data['cancellation_reason'] ?? null,
                            auth()->user(),
                        );
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Gagal mengubah status')
                            ->body(collect($exception->errors())->flatten()->implode(' '))
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->refresh()->load(['activities.creator']);

                    Notification::make()->title('Status berhasil diubah')->success()->send();
                }),

            Action::make('refresh_esb')
                ->label('Refresh dari ESB')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Memperbarui data Sales Order ESB (tanggal, customer, total, status ESB). Informasi operasional, kebutuhan produk, attachment, dan status lokal tidak akan berubah.')
                ->visible(fn (StoreSalesOrder $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->action(function (StoreSalesOrder $record): void {
                    try {
                        app(RefreshStoreSalesOrderSnapshotAction::class)->execute($record, auth()->user());
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Gagal refresh dari ESB')
                            ->body(collect($exception->errors())->flatten()->implode(' '))
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->refresh()->load(['activities.creator']);

                    Notification::make()->title('Snapshot ESB berhasil diperbarui')->success()->send();
                }),
        ];
    }
}
