<?php

namespace App\Filament\Helpdesk\Resources\MaterialSourcingRequests\Pages;

use App\Actions\Purchasing\MaterialSourcingRequest\ClaimMaterialSourcingRequestAction;
use App\Actions\Purchasing\MaterialSourcingRequest\ConvertMaterialSourcingRequestAction;
use App\Actions\Purchasing\MaterialSourcingRequest\DecideMaterialSourcingRequestAction;
use App\Filament\Helpdesk\Resources\MaterialSourcingRequests\MaterialSourcingRequestResource;
use App\Filament\Helpdesk\Resources\MaterialSourcings\MaterialSourcingResource;
use App\Models\MaterialSourcingRequest;
use App\Policies\MaterialSourcingRequestPolicy;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Width;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Request detail with the PIC workflow (PRD §10.2). "Buat Sourcing" opens the existing supplier
 * form with the requested product read-only; no second product picker is stacked on top.
 */
class ViewMaterialSourcingRequest extends ViewRecord
{
    protected static string $resource = MaterialSourcingRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('claim')->label('Mulai Proses')->icon('heroicon-o-play')->color('primary')
                ->visible(fn (MaterialSourcingRequest $record): bool => $this->allows('claim', $record))
                ->requiresConfirmation()
                ->modalDescription('Anda akan tercatat sebagai PIC request ini.')
                ->action(fn (MaterialSourcingRequest $record, Action $action) => $this->runGuarded($action, function () use ($record): void {
                    app(ClaimMaterialSourcingRequestAction::class)->execute(auth()->user(), $record);
                    $this->refreshRecord('Request mulai diproses');
                })),

            Action::make('convert')->label('Buat Sourcing')->icon('heroicon-o-truck')->color('success')
                ->visible(fn (MaterialSourcingRequest $record): bool => $this->allows('convert', $record))
                ->modalHeading('Buat Sourcing dari Request')
                ->modalDescription('Produk mengikuti request dan dibaca ulang dari ESB saat disimpan. Sourcing langsung masuk review R&D.')
                ->modalSubmitActionLabel('Simpan & Kirim ke RnD')
                ->modalWidth(Width::ThreeExtraLarge)
                ->stickyModalHeader()
                ->stickyModalFooter()
                ->extraModalWindowAttributes(['class' => 'material-sourcing-modal'])
                ->fillForm(fn (MaterialSourcingRequest $record): array => [
                    'product_code' => $record->product_code,
                    'product_name' => $record->product_name,
                    'category_name' => $record->category_name,
                    'sub_category_name' => $record->sub_category_name,
                    'uom_name' => $record->uom_name,
                    'sourcings' => [[]],
                ])
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('product_code')->label('Kode Bahan')->disabled()->dehydrated(false),
                        TextInput::make('product_name')->label('Nama Bahan')->disabled()->dehydrated(false),
                        TextInput::make('category_name')->label('Kategori')->disabled()->dehydrated(false)->placeholder('-'),
                        TextInput::make('sub_category_name')->label('Subkategori')->disabled()->dehydrated(false)->placeholder('-'),
                        TextInput::make('uom_name')->label('UOM')->disabled()->dehydrated(false),
                    ])->columnSpanFull(),
                    Textarea::make('material_notes')->label('Catatan Bahan')->maxLength(100)->columnSpanFull(),
                    Repeater::make('sourcings')
                        ->label('Daftar Supplier / Pengirim Sample')
                        ->schema(MaterialSourcingResource::supplierFormFields())
                        ->columns(2)
                        ->defaultItems(1)
                        ->minItems(1)
                        ->addActionLabel('Tambah Supplier')
                        ->columnSpanFull(),
                ])
                ->action(fn (array $data, MaterialSourcingRequest $record, Action $action) => $this->runGuarded($action, function () use ($data, $record): void {
                    app(ConvertMaterialSourcingRequestAction::class)->execute(auth()->user(), $record, [
                        'material_notes' => $data['material_notes'] ?? null,
                        'sourcings' => array_values((array) ($data['sourcings'] ?? [])),
                    ]);
                    $this->refreshRecord('Sourcing dibuat dan dikirim untuk review RnD');
                })),

            Action::make('requestRevision')->label('Minta Perbaikan')->icon('heroicon-o-pencil-square')->color('warning')
                ->visible(fn (MaterialSourcingRequest $record): bool => $this->allows('requestRevision', $record))
                ->schema([Textarea::make('reason')->label('Yang Perlu Diperbaiki')->required()->minLength(5)->maxLength(2000)->rows(3)])
                ->action(fn (array $data, MaterialSourcingRequest $record, Action $action) => $this->runGuarded($action, function () use ($data, $record): void {
                    app(DecideMaterialSourcingRequestAction::class)->requestRevision(auth()->user(), $record, $data['reason']);
                    $this->refreshRecord('Perbaikan diminta', 'Requester mendapat notifikasi.');
                })),

            Action::make('reject')->label('Tolak')->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn (MaterialSourcingRequest $record): bool => $this->allows('reject', $record))
                ->schema([Textarea::make('reason')->label('Alasan Penolakan')->required()->minLength(5)->maxLength(2000)->rows(3)])
                ->modalSubmitActionLabel('Tolak Request')
                ->action(fn (array $data, MaterialSourcingRequest $record, Action $action) => $this->runGuarded($action, function () use ($data, $record): void {
                    app(DecideMaterialSourcingRequestAction::class)->reject(auth()->user(), $record, $data['reason']);
                    $this->refreshRecord('Request ditolak', 'Requester mendapat notifikasi.');
                })),
        ];
    }

    /**
     * Button visibility asks the Policy directly: `Gate::before` lets SUPERADMIN pass every ability,
     * which would show buttons the request status does not allow. Actions still authorize through the
     * Gate and enforce the status themselves.
     */
    private function allows(string $ability, MaterialSourcingRequest $record): bool
    {
        $user = auth()->user();

        return $user !== null && app(MaterialSourcingRequestPolicy::class)->{$ability}($user, $record);
    }

    /** Keep the modal open with the reason instead of failing with a generic error. */
    private function runGuarded(Action $action, \Closure $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Request belum dapat diproses')->body(collect($exception->errors())->flatten()->implode(' '))->persistent()->send();
            $this->record->refresh();
            $action->halt();
        } catch (AuthorizationException $exception) {
            Notification::make()->danger()->title('Akses ditolak')->body($exception->getMessage())->send();
            $this->record->refresh();
            $action->halt();
        }
    }

    private function refreshRecord(string $title, ?string $body = null): void
    {
        $this->record->refresh();
        Notification::make()->success()->title($title)->body($body)->send();
    }
}
