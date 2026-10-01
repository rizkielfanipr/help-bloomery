<?php

namespace App\Filament\Helpdesk\Resources\CustomerComplaints\Pages;

use App\Actions\CustomerComplaint\UpdateCustomerComplaintAction;
use App\Enums\CustomerComplaintStatus;
use App\Filament\Helpdesk\Resources\CustomerComplaints\CustomerComplaintResource;
use App\Models\CustomerComplaint;
use App\Models\CustomerComplaintActivity;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class ViewCustomerComplaint extends ViewRecord
{
    protected static string $resource = CustomerComplaintResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->load(['branch', 'submitter', 'assignee', 'resolvedBy', 'closedBy', 'activities.creator']);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->complaint_number;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * Routed through CustomerComplaintAttachmentController rather than a raw disk temporary URL,
     * so every open re-checks CustomerComplaintPolicy::view() instead of relying solely on a
     * signed URL's own expiry (docs/customer-complaints-prd.md §15, §20.3).
     *
     * @return list<array{name: string, url: string}>
     */
    public function getAttachmentLinks(): array
    {
        return collect($this->record->attachment_paths ?? [])
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'url' => route('helpdesk.customer-complaints.attachments.show', ['path' => $path]),
            ])
            ->all();
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Complaint Information')->schema([
                Grid::make(2)->schema([
                    TextEntry::make('status')->label('Status')->badge()
                        ->formatStateUsing(fn (CustomerComplaintStatus $state) => $state->getLabel())
                        ->color(fn (CustomerComplaintStatus $state) => $state->getColor()),
                    TextEntry::make('occurred_at')->label('Tanggal Kejadian')->dateTime('d M Y H:i'),
                ]),
                Grid::make(2)->schema([
                    TextEntry::make('branch.name')->label('Branch'),
                    TextEntry::make('submitter.name')->label('Pelapor')->placeholder('-'),
                ]),
                Grid::make(2)->schema([
                    TextEntry::make('source')->label('Sumber Komplain')
                        ->formatStateUsing(fn ($state) => $state->getLabel()),
                    TextEntry::make('category')->label('Kategori Komplain')
                        ->formatStateUsing(fn ($state) => $state->getLabel()),
                ]),
                TextEntry::make('order_reference')->label('Nomor Pesanan / Struk')->placeholder('-'),
                Grid::make(2)->schema([
                    TextEntry::make('customer_name')->label('Nama Customer')->placeholder('-'),
                    TextEntry::make('customer_contact')->label('Kontak Customer')->placeholder('-'),
                ]),
                TextEntry::make('description')->label('Detail Komplain')->columnSpanFull(),
            ]),

            Section::make('Attachment')
                ->schema([
                    RepeatableEntry::make('attachmentLinks')
                        ->label('')
                        ->state(fn (): array => $this->getAttachmentLinks())
                        ->schema([
                            TextEntry::make('name')->label('')->url(fn (array $state): ?string => $state['url'] ?? null, shouldOpenInNewTab: true),
                        ]),
                ])
                ->visible(fn (CustomerComplaint $record): bool => filled($record->attachment_paths)),

            Section::make('Follow-up')->schema([
                Grid::make(2)->schema([
                    TextEntry::make('assignee.name')->label('Person in Charge')->placeholder('Belum ditentukan'),
                    TextEntry::make('resolved_at')->label('Resolved Pada')->dateTime('d M Y H:i')->placeholder('-'),
                ]),
                TextEntry::make('internal_notes')->label('Internal Notes')->placeholder('-')->columnSpanFull(),
                TextEntry::make('resolution')->label('Resolution')->placeholder('-')->columnSpanFull(),
            ]),

            Section::make('Activity Timeline')->schema([
                RepeatableEntry::make('activities')
                    ->label('')
                    ->schema([
                        TextEntry::make('description')
                            ->label('')
                            ->state(fn (CustomerComplaintActivity $record): string => $this->describeActivity($record)),
                        TextEntry::make('created_at')->label('')->dateTime('d M Y H:i')
                            ->formatStateUsing(fn ($state, CustomerComplaintActivity $record): string => ($record->creator?->name ?? 'Sistem').' · '.$state->format('d M Y H:i')),
                    ])
                    ->contained(false),
            ])->visible(fn (CustomerComplaint $record): bool => $record->activities->isNotEmpty()),
        ]);
    }

    private function describeActivity(CustomerComplaintActivity $activity): string
    {
        return match ($activity->activity_type) {
            CustomerComplaintActivity::TYPE_CREATED => 'Komplain dibuat.',
            CustomerComplaintActivity::TYPE_STATUS_CHANGED => sprintf(
                'Status diubah dari %s ke %s.',
                $activity->previous_status ? CustomerComplaintStatus::from($activity->previous_status)->getLabel() : '-',
                $activity->new_status ? CustomerComplaintStatus::from($activity->new_status)->getLabel() : '-',
            ),
            CustomerComplaintActivity::TYPE_PIC_CHANGED => 'Person in Charge diperbarui.',
            CustomerComplaintActivity::TYPE_NOTES_UPDATED => 'Internal Notes diperbarui: '.$activity->notes,
            CustomerComplaintActivity::TYPE_RESOLUTION_UPDATED => 'Resolution diperbarui: '.$activity->notes,
            default => $activity->activity_type,
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('follow_up')
                ->label('Tindak Lanjut')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->visible(fn (CustomerComplaint $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->fillForm(fn (CustomerComplaint $record): array => [
                    'status' => $record->status->value,
                    'assigned_to' => $record->assigned_to,
                    'internal_notes' => $record->internal_notes,
                    'resolution' => $record->resolution,
                ])
                ->schema([
                    Select::make('status')
                        ->label('Status')
                        ->options(CustomerComplaintStatus::class)
                        ->required(),
                    Select::make('assigned_to')
                        ->label('Person in Charge')
                        ->options(fn (): array => User::query()
                            ->where('is_active', true)
                            ->permission('update customer complaints')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->native(false),
                    Textarea::make('internal_notes')
                        ->label('Internal Notes')
                        ->rows(3)
                        ->maxLength(2000)
                        ->helperText('Tidak terlihat oleh pelapor.'),
                    Textarea::make('resolution')
                        ->label('Resolution')
                        ->rows(3)
                        ->maxLength(2000)
                        ->helperText('Wajib diisi untuk status Resolved atau Closed.'),
                ])
                ->action(function (array $data, CustomerComplaint $record): void {
                    try {
                        app(UpdateCustomerComplaintAction::class)->execute($record, $data, auth()->user());
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Gagal menyimpan tindak lanjut')
                            ->body(collect($exception->errors())->flatten()->implode(' '))
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->refresh()->load(['assignee', 'activities.creator']);

                    Notification::make()->title('Tindak lanjut berhasil disimpan')->success()->send();
                }),
        ];
    }
}
