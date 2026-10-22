<?php

namespace App\Filament\Helpdesk\Resources\CustomerComplaints\Pages;

use App\Filament\Helpdesk\Resources\CustomerComplaints\CustomerComplaintResource;
use App\Models\CustomerComplaint;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewCustomerComplaint extends ViewRecord
{
    protected static string $resource = CustomerComplaintResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->load(['branch', 'submitter']);
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
                TextEntry::make('occurred_at')->label('Tanggal Kejadian')->date('d M Y'),
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
                            TextEntry::make('url')
                                ->label('')
                                ->formatStateUsing(fn (string $state): string => basename($state))
                                ->url(fn (string $state): string => $state, shouldOpenInNewTab: true),
                        ]),
                ])
                ->visible(fn (CustomerComplaint $record): bool => filled($record->attachment_paths)),

        ]);
    }
}
