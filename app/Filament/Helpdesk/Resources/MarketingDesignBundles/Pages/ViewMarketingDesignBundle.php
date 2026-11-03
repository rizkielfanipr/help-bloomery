<?php

namespace App\Filament\Helpdesk\Resources\MarketingDesignBundles\Pages;

use App\Actions\BrandMarketing\DesignMaster\ApproveDesignVersionAction;
use App\Actions\BrandMarketing\DesignMaster\ArchiveDesignBundleAction;
use App\Actions\BrandMarketing\DesignMaster\CreateDesignRevisionAction;
use App\Actions\BrandMarketing\DesignMaster\PublishDesignVersionAction;
use App\Actions\BrandMarketing\DesignMaster\RemoveDesignFileAction;
use App\Actions\BrandMarketing\DesignMaster\RemoveDesignItemAction;
use App\Actions\BrandMarketing\DesignMaster\ReopenDesignVersionAction;
use App\Actions\BrandMarketing\DesignMaster\RequestDesignVersionRevisionAction;
use App\Actions\BrandMarketing\DesignMaster\RestoreDesignBundleAction;
use App\Actions\BrandMarketing\DesignMaster\SaveDesignItemAction;
use App\Actions\BrandMarketing\DesignMaster\SubmitDesignVersionAction;
use App\Actions\BrandMarketing\DesignMaster\UpdateDesignBundleAction;
use App\Actions\BrandMarketing\DesignMaster\UpdateDesignVersionSummaryAction;
use App\Actions\BrandMarketing\DesignMaster\UploadDesignFileAction;
use App\Enums\MarketingDesignFileRole;
use App\Enums\MarketingDesignVersionStatus;
use App\Filament\Helpdesk\Resources\MarketingDesignBundles\MarketingDesignBundleResource;
use App\Models\MarketingDesignAuditLog;
use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignBundleVersion;
use App\Models\MarketingDesignFile;
use App\Models\MarketingDesignItem;
use App\Models\RndProjectMarketingMaterial;
use App\Policies\MarketingDesignBundlePolicy;
use App\Policies\MarketingDesignFilePolicy;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignReadiness;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignVersionComparison;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/**
 * Detail and editor of one bundle (PRD §17.3, §17.4). The browser only chooses IDs; every record is
 * resolved inside the viewer's visible version, and every mutation goes through an Action that
 * re-authorizes under a row lock. File binaries never enter Livewire state.
 *
 * @property MarketingDesignBundle $record
 */
class ViewMarketingDesignBundle extends ViewRecord
{
    protected static string $resource = MarketingDesignBundleResource::class;

    protected string $view = 'filament.helpdesk.marketing-design-bundles.view';

    private const AUDIT_LIMIT = 50;

    #[Url(as: 'version')]
    public ?int $selectedVersionId = null;

    private ?MarketingDesignBundleVersion $resolvedVersion = null;

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

    public function updatedSelectedVersionId(): void
    {
        $this->resolvedVersion = null;
    }

    public function selectVersion(int $versionId): void
    {
        $this->selectedVersionId = $versionId;
        $this->resolvedVersion = null;
    }

    /** Internal users open the requested or newest version; everyone else only the published one. */
    public function selectedVersion(): ?MarketingDesignBundleVersion
    {
        if ($this->resolvedVersion !== null) {
            return $this->resolvedVersion;
        }

        $versions = $this->record->versions();

        if (! $this->isInternal()) {
            $version = $versions->where('status', MarketingDesignVersionStatus::Published)->first();
        } else {
            $version = ($this->selectedVersionId !== null ? (clone $versions)->find($this->selectedVersionId) : null)
                ?? $versions->orderByDesc('version_number')->first();
        }

        return $this->resolvedVersion = $version?->setRelation('bundle', $this->record);
    }

    protected function getViewData(): array
    {
        $bundle = $this->record->loadMissing(['brand', 'creator', 'updater', 'archiver']);
        $version = $this->selectedVersion();
        $version?->load([
            'creator', 'submitter', 'reviewer', 'publisher', 'clonedFrom',
            'items.files.uploader',
        ]);
        $isInternal = $this->isInternal();
        $items = $version?->items ?? new Collection;
        $allowedRoles = $this->fileRolesVisibleToViewer();
        $readiness = app(MarketingDesignReadiness::class);

        return [
            'bundle' => $bundle,
            'version' => $version,
            'items' => $items,
            'isInternal' => $isInternal,
            'visibleRoles' => $allowedRoles,
            'downloadableRoles' => app(MarketingDesignFilePolicy::class)->downloadableRoles(auth()->user()),
            'filesByRole' => collect($allowedRoles)->map(fn (MarketingDesignFileRole $role): array => [
                'role' => $role,
                'entries' => $items->flatMap(fn (MarketingDesignItem $item) => $item->files
                    ->where('role', $role)
                    ->map(fn (MarketingDesignFile $file): array => ['file' => $file, 'item' => $item])),
            ]),
            'versionIssues' => $version !== null && ! $version->status->isImmutable() && $isInternal ? $readiness->versionIssues($version) : [],
            'readiness' => $readiness,
            'comparison' => $version !== null && $isInternal ? app(MarketingDesignVersionComparison::class)->compare($version) : null,
            'history' => $this->visibleVersions(),
            'auditLogs' => $this->visibleAuditLogs(),
            'auditLimit' => self::AUDIT_LIMIT,
        ];
    }

    public function editBundleAction(): Action
    {
        return Action::make('editBundle')
            ->label('Edit Bundle')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn (): bool => $this->allows('update', $this->record))
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

    public function createRevisionAction(): Action
    {
        return Action::make('createRevision')
            ->label('Buat Revisi')
            ->icon('heroicon-o-document-duplicate')
            ->visible(fn (): bool => $this->allows('createRevision', $this->record) && $this->canStartRevision())
            ->modalHeading('Buat Revisi dari Version Published')
            ->modalDescription('Item dan spesifikasi disalin ke draft baru. File memakai referensi yang sama tanpa menyalin binary. Version published tidak berubah.')
            ->modalSubmitActionLabel('Buat Revisi')
            ->schema([Textarea::make('change_summary')->label('Ringkasan Perubahan')->required()->rows(3)->maxLength(5000)])
            ->action(function (array $data, Action $action): void {
                $this->runGuarded($action, 'Draft revisi dibuat', function () use ($data): void {
                    $revision = app(CreateDesignRevisionAction::class)->execute(auth()->user(), $this->record, $data['change_summary']);
                    $this->selectedVersionId = $revision->id;
                });
            });
    }

    public function editVersionSummaryAction(): Action
    {
        return Action::make('editVersionSummary')
            ->label('Edit Ringkasan')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn (): bool => $this->currentVersionAllows('editContent'))
            ->modalHeading('Edit Ringkasan Version')
            ->modalSubmitActionLabel('Simpan')
            ->schema([Textarea::make('change_summary')->label('Ringkasan Perubahan')->rows(3)->maxLength(5000)])
            ->fillForm(fn (): array => ['change_summary' => $this->selectedVersion()?->change_summary])
            ->action(function (array $data, Action $action): void {
                $this->runGuarded($action, 'Ringkasan diperbarui', fn () => app(UpdateDesignVersionSummaryAction::class)->execute(auth()->user(), $this->selectedVersion(), $data['change_summary'] ?? null));
            });
    }

    public function submitVersionAction(): Action
    {
        return Action::make('submitVersion')
            ->label('Kirim untuk Review')
            ->icon('heroicon-o-paper-airplane')
            ->visible(fn (): bool => $this->currentVersionAllows('submit'))
            ->requiresConfirmation()
            ->modalHeading('Kirim untuk Review?')
            ->modalDescription('Draft tidak dapat diubah selama direview.')
            ->modalSubmitActionLabel('Kirim')
            ->action(function (Action $action): void {
                $this->runGuarded($action, 'Version dikirim untuk review', fn () => app(SubmitDesignVersionAction::class)->execute(auth()->user(), $this->selectedVersion()));
            });
    }

    public function approveVersionAction(): Action
    {
        return Action::make('approveVersion')
            ->label('Setujui')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (): bool => $this->currentVersionAllows('approve'))
            ->modalHeading('Setujui Version')
            ->modalSubmitActionLabel('Setujui')
            ->schema([Textarea::make('note')->label('Catatan Review')->rows(3)->maxLength(5000)])
            ->action(function (array $data, Action $action): void {
                $this->runGuarded($action, 'Version disetujui', fn () => app(ApproveDesignVersionAction::class)->execute(auth()->user(), $this->selectedVersion(), $data['note'] ?? null));
            });
    }

    public function requestRevisionAction(): Action
    {
        return Action::make('requestRevision')
            ->label('Kembalikan untuk Revisi')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->visible(fn (): bool => $this->currentVersionAllows('requestRevision'))
            ->modalHeading('Kembalikan untuk Revisi')
            ->modalSubmitActionLabel('Kembalikan')
            ->schema([Textarea::make('reason')->label('Alasan')->required()->rows(3)->maxLength(5000)])
            ->action(function (array $data, Action $action): void {
                $this->runGuarded($action, 'Version dikembalikan untuk revisi', fn () => app(RequestDesignVersionRevisionAction::class)->execute(auth()->user(), $this->selectedVersion(), $data['reason']));
            });
    }

    public function reopenVersionAction(): Action
    {
        return Action::make('reopenVersion')
            ->label('Perbaiki sebagai Draft')
            ->icon('heroicon-o-pencil')
            ->visible(fn (): bool => $this->currentVersionAllows('reopen'))
            ->requiresConfirmation()
            ->modalHeading('Buka Kembali sebagai Draft?')
            ->modalSubmitActionLabel('Buka Draft')
            ->action(function (Action $action): void {
                $this->runGuarded($action, 'Version kembali menjadi draft', fn () => app(ReopenDesignVersionAction::class)->execute(auth()->user(), $this->selectedVersion()));
            });
    }

    public function publishVersionAction(): Action
    {
        return Action::make('publishVersion')
            ->label('Publish')
            ->icon('heroicon-o-rocket-launch')
            ->color('success')
            ->visible(fn (): bool => $this->currentVersionAllows('publish'))
            ->modalHeading('Publish Version')
            ->modalDescription('Version published tidak dapat diubah lagi. Version published sebelumnya menjadi superseded.')
            ->modalSubmitActionLabel('Publish')
            ->schema([Textarea::make('note')->label('Catatan Publish')->rows(3)->maxLength(5000)])
            ->action(function (array $data, Action $action): void {
                $this->runGuarded($action, 'Version dipublikasikan', fn () => app(PublishDesignVersionAction::class)->execute(auth()->user(), $this->selectedVersion(), $data['note'] ?? null));
            });
    }

    public function archiveBundleAction(): Action
    {
        return Action::make('archiveBundle')
            ->label('Arsipkan')
            ->icon('heroicon-o-archive-box')
            ->color('warning')
            ->visible(fn (): bool => $this->allows('archive', $this->record))
            ->modalHeading('Arsipkan Bundle')
            ->modalDescription('Bundle disembunyikan dari Purchasing dan tidak dapat direvisi. Version, item, file, dan audit tetap tersimpan.')
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
            ->visible(fn (): bool => $this->allows('restore', $this->record))
            ->requiresConfirmation()
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
            ->visible(fn (): bool => $this->currentVersionAllows('editContent'))
            ->modalHeading('Tambah Item Desain')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Simpan Item')
            ->schema($this->itemFormSchema())
            ->action(function (array $data, Action $action): void {
                $this->runGuarded($action, 'Item ditambahkan', fn () => app(SaveDesignItemAction::class)->execute(auth()->user(), $this->selectedVersion(), $data));
            });
    }

    public function editItemAction(): Action
    {
        return Action::make('editItem')
            ->label('Edit')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->size('sm')
            ->visible(fn (): bool => $this->currentVersionAllows('editContent'))
            ->modalHeading('Edit Item Desain')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Simpan Item')
            ->schema($this->itemFormSchema())
            ->fillForm(fn (array $arguments): array => $this->resolveItem($arguments)->only([
                'type', 'title', 'description', 'dimensions', 'material_specification', 'finishing', 'color_print_notes', 'sides', 'production_notes',
            ]))
            ->action(function (array $data, array $arguments, Action $action): void {
                $this->runGuarded($action, 'Item diperbarui', fn () => app(SaveDesignItemAction::class)->execute(auth()->user(), $this->selectedVersion(), $data, $this->resolveItem($arguments)));
            });
    }

    public function removeItemAction(): Action
    {
        return Action::make('removeItem')
            ->label('Lepas')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->size('sm')
            ->visible(fn (): bool => $this->currentVersionAllows('editContent'))
            ->requiresConfirmation()
            ->modalHeading('Lepas Item dari Draft?')
            ->modalDescription('Item hilang dari draft ini. Object file tetap tersimpan di penyimpanan.')
            ->modalSubmitActionLabel('Lepas Item')
            ->action(function (array $arguments, Action $action): void {
                $this->runGuarded($action, 'Item dilepas', fn () => app(RemoveDesignItemAction::class)->execute(auth()->user(), $this->resolveItem($arguments)));
            });
    }

    public function uploadFileAction(): Action
    {
        return Action::make('uploadFile')
            ->label('Unggah File')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->size('sm')
            ->visible(fn (): bool => $this->currentVersionAllows('uploadFile'))
            ->modalHeading('Unggah File Desain')
            ->modalSubmitActionLabel('Simpan File')
            ->schema([
                Select::make('role')
                    ->label('Jenis File')
                    ->placeholder('Pilih jenis file')
                    ->options(MarketingDesignFileRole::class)
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('file', null)),
                FileUpload::make('file')
                    ->label('File')
                    ->required()
                    ->storeFiles(false)
                    ->previewable(false)
                    ->maxSize(MarketingDesignFileRole::MAX_KILOBYTES)
                    ->visible(fn (Get $get): bool => filled($get('role')))
                    ->acceptedFileTypes(fn (Get $get): array => $this->roleFromState($get('role'))?->acceptedBrowserTypes() ?? [])
                    ->helperText(fn (Get $get): ?string => $this->roleFromState($get('role'))?->formatHint()),
            ])
            ->action(function (array $data, array $arguments, Action $action): void {
                $this->runGuarded($action, 'File diunggah', fn () => app(UploadDesignFileAction::class)->execute(
                    auth()->user(),
                    $this->resolveItem($arguments),
                    $this->roleFromState($data['role']),
                    $this->uploadedFile($data['file']),
                ));
            });
    }

    public function replaceFileAction(): Action
    {
        return Action::make('replaceFile')
            ->label('Ganti')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->size('xs')
            ->visible(fn (): bool => $this->currentVersionAllows('uploadFile'))
            ->modalHeading('Ganti File')
            ->modalDescription('File baru disimpan pada path baru. Object file lama tetap tersimpan.')
            ->modalSubmitActionLabel('Ganti File')
            ->schema(fn (array $arguments): array => [
                FileUpload::make('file')
                    ->label('File Pengganti')
                    ->required()
                    ->storeFiles(false)
                    ->previewable(false)
                    ->maxSize(MarketingDesignFileRole::MAX_KILOBYTES)
                    ->acceptedFileTypes($this->resolveFile($arguments)->role->acceptedBrowserTypes())
                    ->helperText($this->resolveFile($arguments)->role->formatHint()),
            ])
            ->action(function (array $data, array $arguments, Action $action): void {
                $this->runGuarded($action, 'File diganti', function () use ($data, $arguments): void {
                    $file = $this->resolveFile($arguments);
                    app(UploadDesignFileAction::class)->execute(auth()->user(), $file->item, $file->role, $this->uploadedFile($data['file']), $file);
                });
            });
    }

    public function removeFileAction(): Action
    {
        return Action::make('removeFile')
            ->label('Lepas')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->size('xs')
            ->visible(fn (): bool => $this->currentVersionAllows('uploadFile'))
            ->requiresConfirmation()
            ->modalHeading('Lepas File dari Draft?')
            ->modalDescription('Record file hilang dari draft ini. Object file tetap tersimpan di penyimpanan.')
            ->modalSubmitActionLabel('Lepas File')
            ->action(function (array $arguments, Action $action): void {
                $this->runGuarded($action, 'File dilepas', fn () => app(RemoveDesignFileAction::class)->execute(auth()->user(), $this->resolveFile($arguments)));
            });
    }

    /** @return array<int, mixed> */
    private function itemFormSchema(): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                Select::make('type')->label('Tipe')->options(RndProjectMarketingMaterial::TYPES)->required()->native(false)->live(),
                TextInput::make('title')->label('Judul')->required()->maxLength(255),
            ]),
            Textarea::make('description')->label('Deskripsi')->rows(2)->maxLength(5000),
            Section::make('Spesifikasi Produksi')
                ->description('Dimensi dan material wajib terisi sebelum dikirim untuk review.')
                ->visible(fn (Get $get): bool => in_array($get('type'), RndProjectMarketingMaterial::PHYSICAL_TYPES, true))
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2])->schema([
                        TextInput::make('dimensions')->label('Dimensi')->maxLength(255),
                        TextInput::make('material_specification')->label('Material')->maxLength(255),
                        TextInput::make('finishing')->label('Finishing')->maxLength(255),
                        TextInput::make('color_print_notes')->label('Warna/Cetak')->maxLength(255),
                        TextInput::make('sides')->label('Sisi')->maxLength(100),
                    ]),
                    Textarea::make('production_notes')->label('Catatan Produksi')->rows(2)->maxLength(5000),
                ]),
        ];
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
        $this->resolvedVersion = null;
        Notification::make()->success()->title($successTitle)->send();
    }

    private function allows(string $ability, MarketingDesignBundle|MarketingDesignBundleVersion $subject): bool
    {
        return app(MarketingDesignAccess::class)->allows(auth()->user(), $ability, $subject);
    }

    private function isInternal(): bool
    {
        return app(MarketingDesignBundlePolicy::class)->viewUnpublished(auth()->user());
    }

    private function currentVersionAllows(string $ability): bool
    {
        $version = $this->selectedVersion();

        return $version !== null && $this->allows($ability, $version);
    }

    private function canStartRevision(): bool
    {
        $versions = $this->record->versions()->get(['status']);

        return $versions->contains('status', MarketingDesignVersionStatus::Published)
            && ! $versions->contains(fn (MarketingDesignBundleVersion $version): bool => $version->status->isOpen());
    }

    /** @param  array<string, mixed>  $arguments */
    private function resolveItem(array $arguments): MarketingDesignItem
    {
        return $this->selectedVersion()->items()->findOrFail((int) ($arguments['item'] ?? 0))->setRelation('version', $this->selectedVersion());
    }

    /** @param  array<string, mixed>  $arguments */
    private function resolveFile(array $arguments): MarketingDesignFile
    {
        $file = MarketingDesignFile::query()
            ->whereIn('marketing_design_item_id', $this->selectedVersion()->items()->select('marketing_design_items.id'))
            ->findOrFail((int) ($arguments['file'] ?? 0));

        return $file->setRelation('item', $file->item->setRelation('version', $this->selectedVersion()));
    }

    private function roleFromState(mixed $state): ?MarketingDesignFileRole
    {
        return $state instanceof MarketingDesignFileRole ? $state : MarketingDesignFileRole::tryFrom((string) $state);
    }

    private function uploadedFile(mixed $state): UploadedFile
    {
        $file = Arr::first(Arr::wrap($state));

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => 'File wajib diunggah.']);
        }

        return $file;
    }

    /** @return list<MarketingDesignFileRole> Non-internal users do not even see files they cannot download. */
    private function fileRolesVisibleToViewer(): array
    {
        return $this->isInternal()
            ? MarketingDesignFileRole::cases()
            : app(MarketingDesignFilePolicy::class)->downloadableRoles(auth()->user());
    }

    /** @return Collection<int, MarketingDesignBundleVersion> */
    private function visibleVersions(): Collection
    {
        return $this->record->versions()
            ->with(['creator', 'publisher', 'reviewer'])
            ->withCount('items')
            ->when(! $this->isInternal(), fn ($versions) => $versions->where('status', MarketingDesignVersionStatus::Published))
            ->orderByDesc('version_number')
            ->get();
    }

    /** @return Collection<int, MarketingDesignAuditLog> */
    private function visibleAuditLogs(): Collection
    {
        return $this->record->auditLogs()
            ->with(['actor', 'version'])
            ->when(! $this->isInternal(), fn ($logs) => $logs->where(function ($logs): void {
                $logs->whereNull('marketing_design_bundle_version_id')
                    ->orWhere('marketing_design_bundle_version_id', $this->selectedVersion()?->id);
            }))
            ->latest('created_at')
            ->latest('id')
            ->limit(self::AUDIT_LIMIT)
            ->get();
    }
}
