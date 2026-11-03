<?php

namespace App\Filament\Helpdesk\Resources\MarketingDesignBundles\Pages;

use App\Actions\BrandMarketing\DesignMaster\ArchiveDesignBundleAction;
use App\Actions\BrandMarketing\DesignMaster\DeleteDesignItemAction;
use App\Actions\BrandMarketing\DesignMaster\ReplaceDesignFileAction;
use App\Actions\BrandMarketing\DesignMaster\RestoreDesignBundleAction;
use App\Actions\BrandMarketing\DesignMaster\SaveDesignItemAction;
use App\Actions\BrandMarketing\DesignMaster\SaveDesignItemSpecificationAction;
use App\Actions\BrandMarketing\DesignMaster\UpdateDesignBundleAction;
use App\Filament\Helpdesk\Resources\MarketingDesignBundles\MarketingDesignBundleResource;
use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignItem;
use App\Models\RndProjectMarketingMaterial;
use App\Policies\MarketingDesignBundlePolicy;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignCompleteness;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignFileStore;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Detail and editor of one bundle (PRD §17.3, §17.4). The browser only chooses IDs; every record is
 * resolved inside this bundle, and every mutation goes through an Action that re-authorizes under a
 * row lock. File binaries never enter Livewire state.
 *
 * @property MarketingDesignBundle $record
 */
class ViewMarketingDesignBundle extends ViewRecord
{
    protected static string $resource = MarketingDesignBundleResource::class;

    protected string $view = 'filament.helpdesk.marketing-design-bundles.view';

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getViewData(): array
    {
        $bundle = $this->record->loadMissing(['brand', 'creator']);

        return [
            'bundle' => $bundle,
            'items' => $bundle->items()->with('file.uploader')->get(),
            'isInternal' => $this->isInternal(),
            'canDownload' => auth()->user()->can('download design master files'),
            'completeness' => app(MarketingDesignCompleteness::class),
        ];
    }

    public function editBundleAction(): Action
    {
        return Action::make('editBundle')
            ->label('Edit Bundle')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn (): bool => $this->allows('update'))
            ->modalHeading('Edit Informasi Bundle')
            ->modalSubmitActionLabel('Simpan')
            ->schema(MarketingDesignBundleResource::bundleFormSchema(brandLocked: true))
            ->fillForm(fn (): array => [
                'brand_id' => $this->record->brand_id,
                'code' => $this->record->code,
                'category' => $this->record->category->value,
                'name' => $this->record->name,
                'description' => $this->record->description,
            ])
            ->action(function (array $data, Action $action): void {
                $this->runGuarded($action, 'Bundle diperbarui', fn () => app(UpdateDesignBundleAction::class)->execute(auth()->user(), $this->record, $data));
            });
    }

    public function archiveBundleAction(): Action
    {
        return Action::make('archiveBundle')
            ->label('Arsipkan')
            ->icon('heroicon-o-archive-box')
            ->color('warning')
            ->visible(fn (): bool => $this->allows('archive'))
            ->modalHeading('Arsipkan Bundle')
            ->modalDescription('Bundle disembunyikan dari Purchasing dan tidak dapat diubah. Item, file, dan audit tetap tersimpan.')
            ->modalSubmitActionLabel('Arsipkan')
            ->schema([Textarea::make('reason')->label('Alasan')->required()->rows(3)->maxLength(5000)])
            ->action(function (array $data, Action $action): void {
                $this->runGuarded($action, 'Bundle diarsipkan', fn () => app(ArchiveDesignBundleAction::class)->execute(auth()->user(), $this->record, $data['reason']));
            });
    }

    public function restoreBundleAction(): Action
    {
        return Action::make('restoreBundle')
            ->label('Pulihkan')
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (): bool => $this->allows('restore'))
            ->requiresConfirmation()
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalHeading('Pulihkan Bundle?')
            ->modalSubmitActionLabel('Pulihkan')
            ->action(function (Action $action): void {
                $this->runGuarded($action, 'Bundle dipulihkan', fn () => app(RestoreDesignBundleAction::class)->execute(auth()->user(), $this->record));
            });
    }

    public function addItemAction(): Action
    {
        return Action::make('addItem')
            ->label('Tambah Item')
            ->icon('heroicon-o-plus')
            ->size('sm')
            ->visible(fn (): bool => $this->allows('editContent') && $this->allows('uploadFile'))
            ->modalHeading('Tambah Item Desain')
            ->modalDescription('Spesifikasi produksi item fisik dapat diisi setelah item tersimpan.')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Simpan Item')
            ->schema([...$this->itemFormSchema(), $this->designFileField('File Desain')])
            ->action(function (array $data, Action $action): void {
                $this->runGuarded($action, 'Item ditambahkan', fn () => app(SaveDesignItemAction::class)->create(
                    auth()->user(),
                    $this->record,
                    $data,
                    $this->uploadedFile($data['file'] ?? null),
                ));
            });
    }

    public function editItemAction(): Action
    {
        return Action::make('editItem')
            ->label('Edit')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->size('sm')
            ->visible(fn (): bool => $this->allows('editContent'))
            ->modalHeading('Edit Item Desain')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Simpan Item')
            ->schema($this->itemFormSchema())
            ->fillForm(fn (array $arguments): array => $this->resolveItem($arguments)->only(['type', 'title', 'description']))
            ->action(function (array $data, array $arguments, Action $action): void {
                $this->runGuarded($action, 'Item diperbarui', fn () => app(SaveDesignItemAction::class)->update(auth()->user(), $this->record, $this->resolveItem($arguments), $data));
            });
    }

    public function editSpecificationAction(): Action
    {
        return Action::make('editSpecification')
            ->label('Isi Spesifikasi')
            ->icon('heroicon-o-clipboard-document-list')
            ->color('gray')
            ->size('sm')
            ->visible(fn (): bool => $this->allows('editContent'))
            ->modalHeading('Spesifikasi Produksi')
            ->modalDescription('Dimensi dan material diperlukan agar item fisik dianggap lengkap.')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Simpan Spesifikasi')
            ->schema(fn (array $arguments): array => [
                View::make('filament.helpdesk.marketing-design-bundles.partials.item-summary')
                    ->viewData(['item' => $this->resolveItem($arguments)->load('file'), 'framed' => true]),
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    TextInput::make('dimensions')->label('Dimensi')->maxLength(255),
                    TextInput::make('material_specification')->label('Material')->maxLength(255),
                    TextInput::make('finishing')->label('Finishing')->maxLength(255),
                    TextInput::make('color_print_notes')->label('Warna/Cetak')->maxLength(255),
                    TextInput::make('sides')->label('Sisi')->maxLength(100),
                ]),
                Textarea::make('production_notes')->label('Catatan Produksi')->rows(2)->maxLength(5000),
            ])
            ->fillForm(fn (array $arguments): array => $this->resolveItem($arguments)->only([
                'dimensions', 'material_specification', 'finishing', 'color_print_notes', 'sides', 'production_notes',
            ]))
            ->action(function (array $data, array $arguments, Action $action): void {
                $this->runGuarded($action, 'Spesifikasi disimpan', fn () => app(SaveDesignItemSpecificationAction::class)->execute(auth()->user(), $this->record, $this->resolveItem($arguments), $data));
            });
    }

    public function deleteItemAction(): Action
    {
        return Action::make('deleteItem')
            ->label('Hapus')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->size('sm')
            ->visible(fn (): bool => $this->allows('editContent'))
            ->requiresConfirmation()
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalHeading('Hapus Item Permanen?')
            ->modalDescription('Item, file desainnya, dan file lama yang pernah diganti akan dihapus permanen dari penyimpanan. Tindakan ini tidak dapat dibatalkan. Catatan audit tetap tersimpan.')
            ->modalSubmitActionLabel('Hapus Permanen')
            ->action(function (array $arguments, Action $action): void {
                $this->runGuarded($action, 'Item dihapus permanen', fn () => app(DeleteDesignItemAction::class)->execute(auth()->user(), $this->resolveItem($arguments)));
            });
    }

    public function replaceFileAction(): Action
    {
        return Action::make('replaceFile')
            ->label(fn (array $arguments): string => ($arguments['hasFile'] ?? true) ? 'Ganti File' : 'Unggah File')
            ->icon(fn (array $arguments): string => ($arguments['hasFile'] ?? true) ? 'heroicon-o-arrow-path' : 'heroicon-o-arrow-up-tray')
            ->color('gray')
            ->size('sm')
            ->visible(fn (): bool => $this->allows('uploadFile'))
            ->modalHeading(fn (array $arguments): string => ($arguments['hasFile'] ?? true) ? 'Ganti File Desain' : 'Unggah File Desain')
            ->modalDescription('File baru disimpan pada path baru. Object file lama tetap tersimpan dan tercatat di audit.')
            ->modalSubmitActionLabel('Ganti File')
            ->schema([$this->designFileField('File Pengganti')])
            ->action(function (array $data, array $arguments, Action $action): void {
                $this->runGuarded($action, 'File desain disimpan', fn () => app(ReplaceDesignFileAction::class)->execute(auth()->user(), $this->resolveItem($arguments), $this->uploadedFile($data['file'] ?? null)));
            });
    }

    /** @return array<int, mixed> */
    private function itemFormSchema(): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                Select::make('type')->label('Tipe')->placeholder('Pilih tipe')->options(RndProjectMarketingMaterial::TYPES)->required()->native(false),
                TextInput::make('title')->label('Nama Item')->required()->maxLength(255),
            ]),
            Textarea::make('description')->label('Deskripsi')->rows(2)->maxLength(5000),
        ];
    }

    private function designFileField(string $label): FileUpload
    {
        return FileUpload::make('file')
            ->label($label)
            ->required()
            ->storeFiles(false)
            ->previewable(false)
            ->maxSize(MarketingDesignFileStore::MAX_KILOBYTES)
            ->acceptedFileTypes(MarketingDesignFileStore::acceptedBrowserTypes())
            ->helperText(MarketingDesignFileStore::formatHint());
    }

    /**
     * Runs a mutation and keeps the modal and its input open when it is refused.
     *
     * @param  callable(): mixed  $mutation
     */
    private function runGuarded(Action $action, string $successTitle, callable $mutation): void
    {
        try {
            $mutation();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Belum dapat diproses')->body($exception->validator->errors()->first())->send();
            $action->halt();

            return;
        } catch (AuthorizationException) {
            Notification::make()->danger()->title('Aksi tidak lagi tersedia')->body('Status data sudah berubah atau akses Anda tidak mencukupi. Muat ulang halaman.')->send();
            $action->halt();

            return;
        }

        $this->record->refresh();
        Notification::make()->success()->title($successTitle)->send();
    }

    private function allows(string $ability): bool
    {
        return app(MarketingDesignAccess::class)->allows(auth()->user(), $ability, $this->record);
    }

    private function isInternal(): bool
    {
        return app(MarketingDesignBundlePolicy::class)->viewArchived(auth()->user());
    }

    /** @param  array<string, mixed>  $arguments */
    private function resolveItem(array $arguments): MarketingDesignItem
    {
        return $this->record->items()->findOrFail((int) ($arguments['item'] ?? 0))->setRelation('bundle', $this->record);
    }

    private function uploadedFile(mixed $state): UploadedFile
    {
        $file = Arr::first(Arr::wrap($state));

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => 'File wajib diunggah.']);
        }

        return $file;
    }
}
