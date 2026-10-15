<?php

namespace App\Filament\Helpdesk\Resources\MaterialSourcings\Pages;

use App\Actions\Rnd\CreateStandaloneMaterialSourcingAction;
use App\Filament\Helpdesk\Concerns\ManagesEsbProductPicker;
use App\Filament\Helpdesk\Resources\MaterialSourcings\MaterialSourcingResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Width;
use Filament\Support\Livewire\Partials\PartialsComponentHook;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;

/**
 * Sourcing Bahan list. "Buat Sourcing" records a supplier sample without a Project: the material is
 * picked first in the shared Master Product ESB picker — the same modal as the BOM
 * "Tambah Komponen" (ManagesEsbProductPicker + esb-product-picker-modal) — and only then does the
 * sourcing form open, so the picker never stacks on top of another dialog.
 */
class ListMaterialSourcings extends ListRecords
{
    use ManagesEsbProductPicker;

    protected static string $resource = MaterialSourcingResource::class;

    /**
     * Keep filter state inside Livewire without synchronizing it to the URL.
     *
     * @var array<string, mixed>|null
     */
    public ?array $tableFilters = null;

    public bool $inlineProductModalOpen = false;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_standalone_sourcing')
                ->label('Buat Sourcing')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->visible(fn (): bool => $this->canCreateStandaloneSourcing())
                ->action(fn () => $this->openSourcingProductPicker()),
        ];
    }

    public function openSourcingProductPicker(): void
    {
        abort_unless($this->canCreateStandaloneSourcing(), 403);

        $this->resetInlineProductPicker();
        $this->inlineProductModalOpen = true;
        $this->renderProductPicker();
    }

    public function closeInlineProductPicker(): void
    {
        $this->inlineProductModalOpen = false;
        $this->renderProductPicker();
    }

    private function renderProductPicker(): void
    {
        app(PartialsComponentHook::class)->renderPartial($this, fn (): array => [
            'sourcing-product-picker' => $this->getFooter(),
        ]);
    }

    /**
     * Closes the picker and opens the sourcing form for the picked row. The row is display data;
     * CreateStandaloneMaterialSourcingAction reads the product again from ESB before saving.
     */
    public function selectSourcingProduct(int $productDetailId): void
    {
        abort_unless($this->canCreateStandaloneSourcing(), 403);
        $option = $this->inlineProductOptions[$productDetailId] ?? null;
        abort_unless(is_array($option) && filled($option['productCode'] ?? null), 422);

        $this->closeInlineProductPicker();
        $this->mountAction('standaloneSourcingForm', [
            'product_code' => (string) $option['productCode'],
            'product_detail_id' => $productDetailId,
            'product_name' => (string) ($option['productName'] ?? ''),
            'category_name' => (string) ($option['categoryName'] ?? ''),
            'sub_category_name' => (string) ($option['subCategoryName'] ?? ''),
            'uom_name' => (string) (($option['baseUnit'] ?? '') ?: ($option['unit'] ?? '')),
        ]);

        // Mounting an action makes Filament re-render only the action modal; the picker lives in
        // the page footer, so it is re-rendered as its own partial to actually disappear.
        $this->renderProductPicker();
    }

    public function standaloneSourcingFormAction(): Action
    {
        return Action::make('standaloneSourcingForm')
            ->modalHeading('Buat Sourcing Tanpa Project')
            ->modalDescription('Catat supplier sample untuk bahan yang tidak berasal dari Project R&D.')
            ->modalSubmitActionLabel('Simpan & Kirim ke RnD')
            ->modalWidth(Width::ThreeExtraLarge)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->extraModalWindowAttributes(['class' => 'material-sourcing-modal'])
            ->authorize(fn (): bool => $this->canCreateStandaloneSourcing())
            ->fillForm(fn (array $arguments): array => [
                'product_code' => $arguments['product_code'] ?? null,
                'product_name' => $arguments['product_name'] ?? null,
                'category_name' => $arguments['category_name'] ?? null,
                'sub_category_name' => $arguments['sub_category_name'] ?? null,
                'uom_name' => $arguments['uom_name'] ?? null,
                'sourcings' => [[]],
            ])
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('product_code')->label('Kode Bahan')->disabled()->dehydrated(false),
                    TextInput::make('product_name')->label('Nama Bahan')->disabled()->dehydrated(false),
                    TextInput::make('category_name')->label('Kategori')->disabled()->dehydrated(false)->placeholder('-'),
                    TextInput::make('sub_category_name')->label('Subkategori')->disabled()->dehydrated(false)->placeholder('-'),
                    TextInput::make('uom_name')->label('UOM Utama')->disabled()->dehydrated(false),
                ])->columnSpanFull(),
                Textarea::make('material_notes')
                    ->label('Catatan Bahan')
                    ->maxLength(100)
                    ->columnSpanFull(),
                Repeater::make('sourcings')
                    ->label('Daftar Supplier / Pengirim Sample')
                    ->schema(MaterialSourcingResource::supplierFormFields())
                    ->columns(2)
                    ->defaultItems(1)
                    ->minItems(1)
                    ->addActionLabel('Tambah Supplier')
                    ->columnSpanFull(),
            ])
            ->extraModalFooterActions([
                Action::make('change_sourcing_product')
                    ->label('Ganti Bahan')
                    ->color('gray')
                    ->cancelParentActions()
                    ->action(fn () => $this->openSourcingProductPicker()),
            ])
            ->action(function (array $data, array $arguments, Action $action, CreateStandaloneMaterialSourcingAction $createSourcing): void {
                try {
                    $createSourcing->execute([
                        ...$data,
                        'product_code' => $arguments['product_code'] ?? null,
                        'product_detail_id' => $arguments['product_detail_id'] ?? null,
                    ], auth()->user());
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('Sourcing belum dapat disimpan')
                        ->body(collect($exception->errors())->flatten()->implode(' '))
                        ->danger()
                        ->send();

                    $action->halt();
                }

                Notification::make()
                    ->title('Sourcing mandiri dikirim untuk review RnD')
                    ->success()
                    ->send();
            });
    }

    public function getFooter(): ?View
    {
        return view('filament.helpdesk.material-sourcings.product-picker-footer', [
            'inlineProductModalOpen' => $this->inlineProductModalOpen,
            'inlineProductOptions' => $this->inlineProductOptions,
            'inlineProductCategoryOptions' => $this->inlineProductCategoryOptions,
            'inlineProductSubCategoryOptions' => $this->inlineProductSubCategoryOptions,
            'inlineProductUnitOptions' => $this->inlineProductUnitOptions,
            'inlineProductPage' => $this->inlineProductPage,
            'inlineProductTotal' => $this->inlineProductTotal,
            'inlineProductPerPage' => $this->inlineProductPerPage,
        ]);
    }

    private function canCreateStandaloneSourcing(): bool
    {
        return auth()->user()?->can('submit material sourcing') ?? false;
    }
}
