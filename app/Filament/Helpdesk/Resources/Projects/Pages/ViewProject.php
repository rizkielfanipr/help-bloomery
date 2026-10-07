<?php

namespace App\Filament\Helpdesk\Resources\Projects\Pages;

use App\Actions\ArchiveRndProjectAction;
use App\Filament\Helpdesk\Concerns\ReleasesBomToStoreSop;
use App\Filament\Helpdesk\Resources\Projects\ProjectResource;
use App\Http\Controllers\Helpdesk\RndProjectBomPdfController;
use App\Models\Branch;
use App\Models\RndProductSalesProjection;
use App\Models\RndProject;
use App\Models\RndProjectProduct;
use App\Models\SalesRegion;
use App\Services\Rnd\ShelfLife\ProjectProductShelfLifeReadiness;
use App\Services\RndProjectMaterialForecastService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;
use Throwable;

class ViewProject extends ViewRecord
{
    use ReleasesBomToStoreSop;
    use WithFileUploads;

    protected static string $resource = ProjectResource::class;

    protected string $view = 'filament.helpdesk.rnd-projects.view';

    protected Width|string|null $maxContentWidth = Width::Full;

    public ?int $editingProductId = null;

    public string $productName = '';

    public string $productCode = '';

    public string $productDescription = '';

    public string $offlinePrice = '0';

    public string $onlinePrice = '0';

    public array $regionalPrices = [];

    public string $priceEffectiveFrom = '';

    public string $releaseDate = '';

    public string $productStatus = 'draft';

    public string $targetOutlets = '';

    public array $salesProjections = [];

    public string $forecastType = 'kitchen';

    public string $forecastPercentage = '100';

    public array $ccpDocumentUploads = [];

    public bool $ccpUploadModalOpen = false;

    public bool $editProjectModalOpen = false;

    public string $editProjectName = '';

    public string $editProjectDescription = '';

    public string $editProjectStartDate = '';

    public string $editProjectEndDate = '';

    public $productPhoto = null;

    public string $productImagePath = '';

    public bool $projectExportModalOpen = false;

    public int $projectExportPreviewRevision = 0;

    public string $projectExportScope = 'kitchen';

    /** @var list<int> */
    public array $projectExportBomIds = [];

    /** @var array<int, list<string>> */
    public array $projectExportBomComponentKeys = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->reloadProject();
        $this->forecastPercentage = (string) (float) ($this->record->forecast_percentage ?? 100);
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getActiveSalesRegionsProperty(): Collection
    {
        return SalesRegion::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    public function getActiveBranchesProperty(): Collection
    {
        return Branch::query()->where('is_active', true)->orderBy('name')->get();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function openEditProjectModal(): void
    {
        abort_unless(ProjectResource::canEdit($this->record), 403);
        $this->resetValidation();
        $this->editProjectName = $this->record->name;
        $this->editProjectDescription = $this->record->description ?? '';
        $this->editProjectStartDate = $this->record->start_date->format('Y-m-d');
        $this->editProjectEndDate = $this->record->end_date->format('Y-m-d');
        $this->editProjectModalOpen = true;
    }

    public function closeEditProjectModal(): void
    {
        $this->resetValidation();
        $this->editProjectModalOpen = false;
    }

    public function saveProjectInformation(): void
    {
        abort_unless(ProjectResource::canEdit($this->record), 403);
        $validated = $this->validate([
            'editProjectName' => ['required', 'string', 'max:255'],
            'editProjectDescription' => ['nullable', 'string'],
            'editProjectStartDate' => ['required', 'date'],
            'editProjectEndDate' => ['required', 'date', 'after_or_equal:editProjectStartDate'],
        ]);

        $this->record->update([
            'name' => trim($validated['editProjectName']),
            'description' => trim($validated['editProjectDescription']) ?: null,
            'start_date' => $validated['editProjectStartDate'],
            'end_date' => $validated['editProjectEndDate'],
        ]);

        $this->editProjectModalOpen = false;
        $this->reloadProject();
        Notification::make()->title('Project berhasil diperbarui')->success()->send();
    }

    public function addCcpDocumentUpload(): void
    {
        abort_unless(ProjectResource::canEdit($this->record), 403);
        $this->ccpDocumentUploads[] = ['name' => '', 'file' => null];
    }

    public function openCcpUploadModal(): void
    {
        abort_unless(ProjectResource::canEdit($this->record), 403);
        $this->resetValidation();
        $this->ccpDocumentUploads = [['name' => '', 'file' => null]];
        $this->ccpUploadModalOpen = true;
    }

    public function closeCcpUploadModal(): void
    {
        $this->resetValidation();
        $this->ccpDocumentUploads = [];
        $this->ccpUploadModalOpen = false;
    }

    public function removeCcpDocumentUpload(int $index): void
    {
        unset($this->ccpDocumentUploads[$index]);
        $this->ccpDocumentUploads = array_values($this->ccpDocumentUploads);
    }

    public function saveCcpDocuments(): void
    {
        abort_unless(ProjectResource::canEdit($this->record), 403);
        $validated = $this->validate([
            'ccpDocumentUploads' => ['required', 'array', 'min:1'],
            'ccpDocumentUploads.*.name' => ['required', 'string', 'max:255'],
            'ccpDocumentUploads.*.file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,webp', 'max:20480'],
        ]);
        $storedPaths = [];

        try {
            DB::transaction(function () use ($validated, &$storedPaths): void {
                foreach ($validated['ccpDocumentUploads'] as $document) {
                    $file = $document['file'];
                    $path = $file->store('rnd/projects/'.$this->record->id.'/ccp-documents', 'b2');
                    if (! is_string($path) || $path === '') {
                        throw new \RuntimeException('Dokumen CCP gagal diunggah.');
                    }
                    $storedPaths[] = $path;
                    $this->record->documents()->create([
                        'name' => trim($document['name']),
                        'file_path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'created_by' => auth()->id(),
                    ]);
                }
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('b2')->delete($path);
            }
            throw $exception;
        }

        $this->ccpDocumentUploads = [];
        $this->ccpUploadModalOpen = false;
        $this->reloadProject();
        Notification::make()->title('Dokumen CCP berhasil disimpan')->success()->send();
    }

    public function deleteCcpDocument(int $documentId): void
    {
        abort_unless(ProjectResource::canEdit($this->record), 403);
        $document = $this->record->documents()->findOrFail($documentId);
        $path = $document->file_path;
        $document->delete();
        Storage::disk('b2')->delete($path);
        $this->reloadProject();
        Notification::make()->title('Dokumen CCP berhasil dihapus')->success()->send();
    }

    public function openCreateProduct(): void
    {
        abort_unless(ProjectResource::canEdit($this->record), 403);
        $this->resetProductForm();
        $this->loadRegionalPriceForm();
        $this->dispatch('open-product-form');
    }

    public function editProduct(int $productId): void
    {
        abort_unless(ProjectResource::canEdit($this->record), 403);
        $product = $this->record->products()->findOrFail($productId);

        $this->editingProductId = $product->id;
        $this->productName = $product->name;
        $this->productCode = $product->product_code ?? '';
        $this->productDescription = $product->description ?? '';
        $this->offlinePrice = (string) $product->offline_price;
        $this->onlinePrice = (string) $product->online_price;
        $this->priceEffectiveFrom = today()->toDateString();
        $this->loadRegionalPriceForm($product);
        $this->releaseDate = $product->release_date?->toDateString() ?? '';
        $this->productStatus = $product->status;
        $this->targetOutlets = (string) ($product->target_outlets ?? '');
        $this->loadSalesProjectionForm($product);
        $this->productImagePath = $product->image_path ?? '';
        $this->productPhoto = null;
        $this->resetValidation();
        $this->dispatch('open-product-form');
    }

    public function saveProduct(): void
    {
        abort_unless(ProjectResource::canEdit($this->record), 403);

        $this->regionalPrices = collect($this->regionalPrices)->map(function (array $price): array {
            $offlinePrice = (string) ($price['offline_price'] ?? 0);
            $onlinePrice = (string) ($price['online_price'] ?? 0);

            $price['enabled'] = (bool) ($price['enabled'] ?? true);
            $price['has_separate_offline_prices'] = true;
            $price['dine_in_price'] = (string) ($price['dine_in_price'] ?? $offlinePrice);
            $price['takeaway_price'] = (string) ($price['takeaway_price'] ?? $offlinePrice);
            $price['gofood_price'] = (string) ($price['gofood_price'] ?? $onlinePrice);
            $price['grabfood_price'] = (string) ($price['grabfood_price'] ?? $onlinePrice);
            $price['shopeefood_price'] = (string) ($price['shopeefood_price'] ?? $onlinePrice);

            return $price;
        })->all();

        $validated = $this->validate([
            'productName' => ['required', 'string', 'max:255'],
            'productCode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('rnd_project_products', 'product_code')
                    ->where('rnd_project_id', $this->record->id)
                    ->ignore($this->editingProductId),
            ],
            'productDescription' => ['nullable', 'string', 'max:3000'],
            'priceEffectiveFrom' => ['required', 'date'],
            'regionalPrices' => ['required', 'array'],
            'regionalPrices.*.enabled' => ['required', 'boolean'],
            'regionalPrices.*.region_id' => ['required', 'integer', 'exists:sales_regions,id'],
            'regionalPrices.*.offline_price' => ['nullable', 'numeric', 'min:0'],
            'regionalPrices.*.has_separate_offline_prices' => ['required', 'boolean'],
            'regionalPrices.*.dine_in_price' => ['nullable', 'required_if:regionalPrices.*.enabled,true', 'numeric', 'min:0'],
            'regionalPrices.*.takeaway_price' => ['nullable', 'required_if:regionalPrices.*.enabled,true', 'numeric', 'min:0'],
            'regionalPrices.*.online_price' => ['nullable', 'numeric', 'min:0'],
            'regionalPrices.*.gofood_price' => ['nullable', 'required_if:regionalPrices.*.enabled,true', 'numeric', 'min:0'],
            'regionalPrices.*.grabfood_price' => ['nullable', 'required_if:regionalPrices.*.enabled,true', 'numeric', 'min:0'],
            'regionalPrices.*.shopeefood_price' => ['nullable', 'required_if:regionalPrices.*.enabled,true', 'numeric', 'min:0'],
            'releaseDate' => ['nullable', 'date'],
            'productStatus' => ['required', Rule::in(array_keys(RndProjectProduct::STATUSES))],
            'productPhoto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $selectedRegionalPrices = collect($validated['regionalPrices'])
            ->where('enabled', true)
            ->values()
            ->all();
        $existingProduct = $this->editingProductId
            ? $this->record->products()->find($this->editingProductId)
            : null;
        if (in_array($validated['productStatus'], ['ready', 'released'], true)) {
            $planningIsInvalid = false;

            if (blank($validated['releaseDate'])) {
                $this->addError('releaseDate', 'Tanggal rilis wajib diisi sebelum produk Ready/Released.');
                $planningIsInvalid = true;
            }
            // Shelf Life is checked per WIP of the Product, never on the Menu itself
            // (docs/rnd-wip-shelf-life-prd.md §9.4, §18).
            $wipShelfLifeBlockers = $existingProduct
                ? app(ProjectProductShelfLifeReadiness::class)->blockers($existingProduct)
                : [];
            if ($wipShelfLifeBlockers !== []) {
                $this->addError('wipShelfLife', implode(' ', $wipShelfLifeBlockers));
                $planningIsInvalid = true;
            }
            if ($existingProduct === null || $existingProduct->salesProjections()->count() === 0) {
                $this->addError('salesProjections', 'Minimal satu sales projection wajib sebelum produk Ready/Released.');
                $planningIsInvalid = true;
            } elseif ($existingProduct->salesProjections()->whereDoesntHave('targetBranches')->exists()) {
                $this->addError('salesProjections', 'Setiap sales projection wajib memiliki split branch sebelum produk Ready/Released.');
                $planningIsInvalid = true;
            }

            if ($planningIsInvalid) {
                return;
            }
            foreach ($selectedRegionalPrices as $index => $price) {
                if ((float) $price['dine_in_price'] <= 0
                    || (float) $price['takeaway_price'] <= 0
                    || (float) $price['gofood_price'] <= 0
                    || (float) $price['grabfood_price'] <= 0
                    || (float) $price['shopeefood_price'] <= 0) {
                    $this->addError("regionalPrices.$index.dine_in_price", 'Seluruh harga channel wajib diisi sebelum produk Ready/Released.');

                    return;
                }
            }
        }

        $newImagePath = null;
        if ($this->productPhoto) {
            $newImagePath = $this->productPhoto->store(
                'rnd/products/'.$this->record->id,
                'b2',
            );

            if (! is_string($newImagePath) || $newImagePath === '') {
                $this->addError('productPhoto', 'Foto gagal diunggah ke Cloudflare R2.');

                return;
            }
        }

        $minimumOffline = collect($selectedRegionalPrices)->min(
            fn (array $price): float => min((float) $price['dine_in_price'], (float) $price['takeaway_price'])
        ) ?? 0;
        $minimumOnline = collect($selectedRegionalPrices)->min(
            fn (array $price): float => min(
                (float) $price['gofood_price'],
                (float) $price['grabfood_price'],
                (float) $price['shopeefood_price'],
            )
        ) ?? 0;
        $payload = [
            'name' => trim($validated['productName']),
            'product_code' => trim($validated['productCode']) ?: null,
            'description' => trim($validated['productDescription']) ?: null,
            'offline_price' => $minimumOffline,
            'online_price' => $minimumOnline,
            'release_date' => $validated['releaseDate'] ?: null,
            'status' => $validated['productStatus'],
        ];
        if ($newImagePath) {
            $payload['image_path'] = $newImagePath;
        }

        try {
            DB::transaction(function () use ($payload, $selectedRegionalPrices, $validated, &$message): void {
                if ($this->editingProductId) {
                    $product = $this->record->products()->findOrFail($this->editingProductId);
                    $product->update($payload);
                    $message = 'Product berhasil diperbarui';
                } else {
                    $product = $this->record->products()->create($payload + ['created_by' => auth()->id()]);
                    $message = 'Product berhasil ditambahkan';
                }
                $this->saveRegionalPrices($product, $selectedRegionalPrices, $validated['priceEffectiveFrom']);
            });
        } catch (Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('b2')->delete($newImagePath);
            }

            throw $exception;
        }

        if ($newImagePath && $this->productImagePath && $this->productImagePath !== $newImagePath) {
            Storage::disk('b2')->delete($this->productImagePath);
        }

        $this->reloadProject();
        $this->resetProductForm();
        $this->dispatch('close-product-form');
        Notification::make()->title($message)->success()->send();
    }

    public function deleteProduct(int $productId): void
    {
        abort_unless(ProjectResource::canEdit($this->record), 403);
        $product = $this->record->products()->findOrFail($productId);
        $imagePath = $product->image_path;
        $materialPaths = $product->marketingMaterials()->pluck('file_path')->all();
        $detachedBomCount = $product->boms()->count();

        DB::transaction(function () use ($product): void {
            $product->boms()->detach();
            $product->delete();
        });

        if ($imagePath) {
            Storage::disk('b2')->delete($imagePath);
        }
        if ($materialPaths !== []) {
            Storage::disk('b2')->delete($materialPaths);
        }
        $this->reloadProject();
        Notification::make()
            ->title('Menu berhasil dihapus')
            ->body($detachedBomCount > 0 ? $detachedBomCount.' relasi BOM otomatis dilepas. Data BOM tetap tersimpan.' : null)
            ->success()
            ->send();
    }

    public function archiveProject(ArchiveRndProjectAction $archiveProject): mixed
    {
        abort_unless(ProjectResource::canDelete($this->record), 403);

        $archiveProject->execute($this->record);

        Notification::make()
            ->title('Project berhasil diarsipkan')
            ->body('Seluruh data dan attachment tetap tersimpan dan dapat dipulihkan.')
            ->success()
            ->send();

        return $this->redirect(ProjectResource::getUrl('index'), navigate: true);
    }

    /**
     * PDF shown in the preview modal: the same export route, inline for the preview and as an
     * attachment for "Download PDF". Access is the export permission, checked again by the route.
     *
     * @var array{title: string, preview_url: string, download_url: string}|null
     */
    #[Locked]
    public ?array $pdfPreview = null;

    public function openProjectBomExport(string $scope): void
    {
        abort_unless(in_array($scope, ['kitchen', 'store'], true), 422);
        abort_unless($this->canExportProjectBomScope($scope), 403);
        $this->projectExportScope = $scope;
        $this->projectExportBomIds = $this->eligibleProjectExportBoms($scope)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->projectExportBomComponentKeys = [];
        $this->pdfPreview = null;
        $this->resetValidation('projectExportBomIds');

        if ($scope === 'store') {
            $this->exportProjectBomPdf();

            return;
        }

        $this->projectExportModalOpen = true;
        $this->refreshProjectBomPreview();
    }

    public function closePdfPreview(): void
    {
        $this->pdfPreview = null;
    }

    public function closeProjectBomExport(): void
    {
        $this->projectExportModalOpen = false;
        $this->pdfPreview = null;
        $this->resetValidation('projectExportBomIds');
    }

    public function updatedProjectExportBomIds(): void
    {
        $this->refreshProjectBomPreview();
    }

    public function refreshProjectBomPreview(): void
    {
        if (! $this->projectExportModalOpen) {
            return;
        }

        $this->resetValidation('projectExportBomIds');

        if ($this->projectExportBomIds === []) {
            $this->pdfPreview = null;
            $this->addError('projectExportBomIds', 'Pilih minimal satu BOM.');

            return;
        }

        $this->exportProjectBomPdf();
    }

    public function exportProjectBomPdf(): mixed
    {
        abort_unless($this->canExportProjectBomScope($this->projectExportScope), 403);
        $eligibleBomIds = $this->eligibleProjectExportBoms($this->projectExportScope)->pluck('id')->map(fn ($id): int => (int) $id);
        if ($this->projectExportScope === 'store') {
            $this->projectExportBomIds = $eligibleBomIds->all();
        }

        if ($this->projectExportScope !== 'store') {
            $this->validate([
                'projectExportBomIds' => ['required', 'array', 'min:1'],
                'projectExportBomIds.*' => ['integer'],
            ]);
        }
        abort_unless(collect($this->projectExportBomIds)->every(fn ($id): bool => $eligibleBomIds->contains((int) $id)), 422);

        session()->forget(RndProjectBomPdfController::componentSessionKey(auth()->id(), $this->record->id));

        $routeParameters = [
            'project' => $this->record->id,
            'scope' => $this->projectExportScope,
        ];
        if ($this->projectExportScope !== 'store' && $eligibleBomIds->sort()->values()->all() !== collect($this->projectExportBomIds)->map(fn ($id): int => (int) $id)->sort()->values()->all()) {
            $routeParameters['bom_ids'] = collect($this->projectExportBomIds)->map(fn ($id): int => (int) $id)->implode(',');
        }

        $this->projectExportPreviewRevision++;
        $this->pdfPreview = [
            'title' => 'Preview '.($this->projectExportScope === 'store' ? 'Store' : 'Kitchen').' PDF',
            'preview_url' => route('helpdesk.rnd-projects.bom-pdf', [...$routeParameters, 'preview' => 1]),
            'download_url' => route('helpdesk.rnd-projects.bom-pdf', $routeParameters),
        ];

        return null;
    }

    private function canExportProjectBomScope(string $scope): bool
    {
        return auth()->user()?->can(match ($scope) {
            'kitchen' => 'export kitchen bill of materials',
            'store' => 'export store bill of materials',
            default => '',
        }) ?? false;
    }

    public function eligibleProjectExportBoms(?string $scope = null): \Illuminate\Support\Collection
    {
        $scope ??= $this->projectExportScope;

        return $this->record->products
            ->flatMap->boms
            ->filter(fn ($bom): bool => $scope === 'store'
                ? $bom->pivot->usage_type === 'menu'
                : $bom->pivot->usage_type !== 'menu')
            ->unique('id')
            ->values();
    }

    /** @return list<array{key:string,name:string,code:string}> */
    public function projectExportBomComponents(int $bomId): array
    {
        $bom = $this->record->boms->firstWhere('id', $bomId);

        $documentMaterials = $bom?->documentMaterials?->map(fn ($material): array => [
            'documentMaterialId' => $material->id,
            'productName' => $material->name,
            'productCode' => '',
        ]) ?? collect();

        return collect($bom?->detail_snapshot['bomDetails'] ?? [])->concat($documentMaterials)->values()->map(fn (array $component, int $index): array => [
            'key' => isset($component['documentMaterialId'])
                ? 'document-'.$component['documentMaterialId']
                : (string) ($component['productDetailID'] ?? $component['ID'] ?? $component['productCode'] ?? 'index-'.$index),
            'name' => (string) ($component['productName'] ?? 'Component '.($index + 1)),
            'code' => (string) ($component['productCode'] ?? ''),
        ])->all();
    }

    protected function releaseSopProject(): RndProject
    {
        return $this->record;
    }

    protected function releaseSopProduct(): ?RndProjectProduct
    {
        return null;
    }

    protected function releaseSopScope(): string
    {
        return $this->projectExportScope;
    }

    protected function releaseSopBomIds(): array
    {
        return collect($this->projectExportBomIds)->map(fn ($id): int => (int) $id)->values()->all();
    }

    protected function releaseSopAutoBomKeys(): array
    {
        return [];
    }

    protected function releaseSopComponentKeys(): array
    {
        return $this->projectExportBomComponentKeys;
    }

    private function resetProductForm(): void
    {
        $this->editingProductId = null;
        $this->productName = '';
        $this->productCode = '';
        $this->productDescription = '';
        $this->offlinePrice = '0';
        $this->onlinePrice = '0';
        $this->regionalPrices = [];
        $this->priceEffectiveFrom = today()->toDateString();
        $this->releaseDate = '';
        $this->productStatus = 'draft';
        $this->targetOutlets = '';
        $this->salesProjections = [];
        $this->productPhoto = null;
        $this->productImagePath = '';
        $this->resetValidation();
    }

    private function loadRegionalPriceForm(?RndProjectProduct $product = null): void
    {
        $regions = SalesRegion::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $existing = $product?->regionalPrices()
            ->with('region')
            ->where('status', 'active')
            ->orderByDesc('effective_from')
            ->get()
            ->unique('sales_region_id')
            ->keyBy('sales_region_id') ?? collect();

        $this->regionalPrices = $regions->map(function (SalesRegion $region) use ($existing, $product): array {
            $price = $existing->get($region->id);

            return [
                'enabled' => $price !== null,
                'region_id' => $region->id,
                'region_name' => $region->name,
                'region_code' => $region->code,
                'offline_price' => (string) ($price?->offline_price ?? $product?->offline_price ?? 0),
                'has_separate_offline_prices' => (bool) ($price?->has_separate_offline_prices ?? false),
                'dine_in_price' => (string) ($price?->dine_in_price ?? $price?->offline_price ?? $product?->offline_price ?? 0),
                'takeaway_price' => (string) ($price?->takeaway_price ?? $price?->offline_price ?? $product?->offline_price ?? 0),
                'online_price' => (string) ($price?->online_price ?? $product?->online_price ?? 0),
                'gofood_price' => (string) ($price?->gofood_price ?? $price?->online_price ?? $product?->online_price ?? 0),
                'grabfood_price' => (string) ($price?->grabfood_price ?? $price?->online_price ?? $product?->online_price ?? 0),
                'shopeefood_price' => (string) ($price?->shopeefood_price ?? $price?->online_price ?? $product?->online_price ?? 0),
            ];
        })->all();
    }

    private function saveRegionalPrices(RndProjectProduct $product, array $prices, string $effectiveFrom): void
    {
        $effectiveDate = Carbon::parse($effectiveFrom)->startOfDay();
        $selectedRegionIds = collect($prices)->pluck('region_id')->map(fn ($id): int => (int) $id);

        $product->regionalPrices()
            ->where('status', 'active')
            ->when($selectedRegionIds->isNotEmpty(), fn ($query) => $query->whereNotIn('sales_region_id', $selectedRegionIds))
            ->update(['status' => 'expired']);

        foreach ($prices as $price) {
            $dineInPrice = (float) $price['dine_in_price'];
            $takeawayPrice = (float) $price['takeaway_price'];
            $gofoodPrice = (float) $price['gofood_price'];
            $grabfoodPrice = (float) $price['grabfood_price'];
            $shopeefoodPrice = (float) $price['shopeefood_price'];
            $nextEffective = $product->regionalPrices()
                ->where('sales_region_id', $price['region_id'])
                ->whereDate('effective_from', '>', $effectiveDate)
                ->min('effective_from');
            $product->regionalPrices()
                ->where('sales_region_id', $price['region_id'])
                ->where('status', 'active')
                ->whereDate('effective_from', '<', $effectiveDate)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveDate))
                ->update(['effective_to' => $effectiveDate->copy()->subDay()->toDateString(), 'status' => 'expired']);

            $values = [
                'offline_price' => min($dineInPrice, $takeawayPrice),
                'has_separate_offline_prices' => (bool) $price['has_separate_offline_prices'],
                'dine_in_price' => $dineInPrice,
                'takeaway_price' => $takeawayPrice,
                'online_price' => min($gofoodPrice, $grabfoodPrice, $shopeefoodPrice),
                'gofood_price' => $gofoodPrice,
                'grabfood_price' => $grabfoodPrice,
                'shopeefood_price' => $shopeefoodPrice,
                'effective_to' => $nextEffective ? Carbon::parse($nextEffective)->subDay()->toDateString() : null,
                'status' => 'active',
                'created_by' => auth()->id(),
            ];
            $existingPrice = $product->regionalPrices()
                ->where('sales_region_id', $price['region_id'])
                ->whereDate('effective_from', $effectiveDate)
                ->first();

            if ($existingPrice) {
                $existingPrice->update($values);
            } else {
                $product->regionalPrices()->create($values + [
                    'sales_region_id' => $price['region_id'],
                    'effective_from' => $effectiveDate->toDateString(),
                ]);
            }
        }
    }

    public function addSalesProjection(): void
    {
        abort_unless($this->editingProductId, 422);
        $firstRegion = SalesRegion::query()->where('is_active', true)->orderBy('sort_order')->value('id');
        $this->salesProjections[] = [
            'id' => null,
            'projection_month' => today()->startOfMonth()->format('Y-m'),
            'sales_region_id' => $firstRegion,
            'channel' => 'all',
            'target_quantity' => '',
            'target_revenue' => '',
            'target_outlets' => null,
            'branch_targets' => $this->emptyBranchTargets(),
            'notes' => '',
        ];
    }

    public function removeSalesProjection(int $index): void
    {
        $projection = $this->salesProjections[$index] ?? null;
        abort_unless($projection !== null, 404);

        if (filled($projection['id'] ?? null)) {
            abort_unless(ProjectResource::canEdit($this->record) && $this->editingProductId, 403);
            $product = $this->record->products()->findOrFail($this->editingProductId);
            $product->salesProjections()->whereKey((int) $projection['id'])->delete();
            $this->recalculateProductTargetOutlets($product);
            Notification::make()->title('Sales projection berhasil dihapus')->success()->send();
        }

        unset($this->salesProjections[$index]);
        $this->salesProjections = array_values($this->salesProjections);
    }

    /**
     * Save only the main projection fields (period, channel, quantity, revenue, notes).
     * Branch split is handled separately by saveSalesProjectionBranchSplit() once this
     * row has an id — it only seeds an equal-split suggestion the first time a row is
     * saved, so an already-split row keeps its existing per-branch values untouched.
     */
    public function saveSalesProjectionMain(int $index): void
    {
        abort_unless(ProjectResource::canEdit($this->record), 403);
        abort_unless($this->editingProductId, 422);
        $projection = $this->salesProjections[$index] ?? null;
        abort_unless($projection !== null, 404);
        $product = $this->record->products()->findOrFail($this->editingProductId);

        $validated = $this->validate([
            "salesProjections.$index.projection_month" => ['required', 'date_format:Y-m'],
            "salesProjections.$index.sales_region_id" => ['required', 'integer', 'exists:sales_regions,id'],
            "salesProjections.$index.channel" => ['required', Rule::in(array_keys(RndProductSalesProjection::CHANNELS))],
            "salesProjections.$index.target_quantity" => ['required', 'numeric', 'min:0.01'],
            "salesProjections.$index.target_revenue" => ['required', 'numeric', 'min:0'],
            "salesProjections.$index.notes" => ['nullable', 'string', 'max:1000'],
        ])['salesProjections'][$index];

        $projectionMonth = Carbon::createFromFormat('Y-m', $validated['projection_month'])->startOfMonth();
        $duplicate = $product->salesProjections()
            ->whereDate('projection_month', $projectionMonth)
            ->where('sales_region_id', $validated['sales_region_id'])
            ->where('channel', $validated['channel'])
            ->when(filled($projection['id'] ?? null), fn ($query) => $query->whereKeyNot((int) $projection['id']))
            ->exists();

        if ($duplicate) {
            $this->addError("salesProjections.$index.projection_month", 'Periode, region, dan channel tidak boleh duplikat dalam satu product.');

            return;
        }

        $values = [
            'sales_region_id' => $validated['sales_region_id'],
            'projection_month' => $projectionMonth,
            'channel' => $validated['channel'],
            'target_quantity' => $validated['target_quantity'],
            'target_revenue' => $validated['target_revenue'],
            'notes' => trim((string) $validated['notes']) ?: null,
        ];

        $record = filled($projection['id'] ?? null)
            ? $product->salesProjections()->findOrFail((int) $projection['id'])
            : null;

        if ($record) {
            $record->update($values);
        } else {
            $record = $product->salesProjections()->create($values + ['created_by' => auth()->id()]);
        }

        $this->salesProjections[$index]['id'] = $record->id;

        $hasExistingSplit = collect($projection['branch_targets'])->contains('enabled', true);
        if (! $hasExistingSplit) {
            $this->salesProjections[$index]['branch_targets'] = $this->equalSplitBranchTargets((float) $validated['target_quantity']);
        }

        Notification::make()
            ->title('Projection tersimpan')
            ->body('Lanjutkan isi split target per branch di bawah.')
            ->success()
            ->send();
    }

    public function resetEqualSplit(int $index): void
    {
        $projection = $this->salesProjections[$index] ?? null;
        abort_unless($projection !== null && filled($projection['id'] ?? null), 422);

        $this->salesProjections[$index]['branch_targets'] = $this->equalSplitBranchTargets(
            (float) ($projection['target_quantity'] ?? 0),
        );
    }

    public function saveSalesProjectionBranchSplit(int $index): void
    {
        abort_unless(ProjectResource::canEdit($this->record), 403);
        abort_unless($this->editingProductId, 422);
        $projection = $this->salesProjections[$index] ?? null;
        abort_unless($projection !== null && filled($projection['id'] ?? null), 422);
        $product = $this->record->products()->findOrFail($this->editingProductId);
        $record = $product->salesProjections()->findOrFail((int) $projection['id']);

        $validated = $this->validate([
            "salesProjections.$index.branch_targets" => ['required', 'array'],
            "salesProjections.$index.branch_targets.*.branch_id" => ['required', 'integer', 'exists:branches,id'],
            "salesProjections.$index.branch_targets.*.enabled" => ['required', 'boolean'],
            "salesProjections.$index.branch_targets.*.target_quantity" => ['nullable', 'numeric', 'min:0'],
        ])['salesProjections'][$index];

        $enabledBranchTargets = collect($validated['branch_targets'])->where('enabled', true);
        if ($enabledBranchTargets->isEmpty()) {
            $this->addError("salesProjections.$index.branch_targets", 'Pilih minimal satu branch untuk target quantity.');

            return;
        }
        foreach ($enabledBranchTargets as $targetIndex => $target) {
            if ((float) ($target['target_quantity'] ?? 0) <= 0) {
                $this->addError("salesProjections.$index.branch_targets.$targetIndex.target_quantity", 'Target quantity branch wajib lebih dari 0.');

                return;
            }
        }

        $record->targetBranches()->sync(
            $enabledBranchTargets->mapWithKeys(fn (array $target): array => [
                (int) $target['branch_id'] => ['target_quantity' => (float) $target['target_quantity']],
            ])->all(),
        );
        $record->update(['target_outlets' => $enabledBranchTargets->count()]);
        $this->recalculateProductTargetOutlets($product);

        Notification::make()->title('Split branch berhasil disimpan')->success()->send();
    }

    /** Non-blocking hint shown in the UI when the branch split no longer adds up to the main quantity. */
    public function branchSplitWarning(array $projection): ?string
    {
        $enabledTotal = collect($projection['branch_targets'] ?? [])
            ->where('enabled', true)
            ->sum(fn (array $target): float => (float) ($target['target_quantity'] ?: 0));
        $target = (float) ($projection['target_quantity'] ?? 0);

        if (abs($enabledTotal - $target) < 0.01) {
            return null;
        }

        return sprintf(
            'Total split branch (%s) belum sama dengan target quantity utama (%s).',
            number_format($enabledTotal, 2, ',', '.'),
            number_format($target, 2, ',', '.'),
        );
    }

    private function loadSalesProjectionForm(RndProjectProduct $product): void
    {
        $this->salesProjections = $product->salesProjections()->with('targetBranches')->get()
            ->map(function (RndProductSalesProjection $projection): array {
                $existingTargets = $projection->targetBranches->keyBy('id');

                return [
                    'id' => $projection->id,
                    'projection_month' => $projection->projection_month->format('Y-m'),
                    'sales_region_id' => $projection->sales_region_id,
                    'channel' => $projection->channel,
                    'target_quantity' => (string) $projection->target_quantity,
                    'target_revenue' => (string) $projection->target_revenue,
                    'target_outlets' => (string) ($projection->target_outlets ?? ''),
                    'branch_targets' => $existingTargets->isNotEmpty()
                        ? $this->emptyBranchTargets($existingTargets)
                        : $this->equalSplitBranchTargets((float) $projection->target_quantity),
                    'notes' => $projection->notes ?? '',
                ];
            })->all();
    }

    private function emptyBranchTargets(?Collection $existingTargets = null): array
    {
        $existingTargets ??= collect();

        return $this->activeBranches->map(function (Branch $branch) use ($existingTargets): array {
            $existingBranch = $existingTargets->get($branch->id);

            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'enabled' => $existingBranch !== null,
                'target_quantity' => $existingBranch ? (string) $existingBranch->pivot->target_quantity : '',
            ];
        })->all();
    }

    private function equalSplitBranchTargets(float $total): array
    {
        $branches = $this->activeBranches;
        if ($branches->isEmpty()) {
            return [];
        }

        $perBranch = round($total / $branches->count(), 2);

        return $branches->map(fn (Branch $branch): array => [
            'branch_id' => $branch->id,
            'branch_name' => $branch->name,
            'enabled' => true,
            'target_quantity' => (string) $perBranch,
        ])->all();
    }

    private function recalculateProductTargetOutlets(RndProjectProduct $product): void
    {
        $product->update([
            'target_outlets' => $product->salesProjections()
                ->with('targetBranches:id')
                ->get()
                ->flatMap->targetBranches
                ->pluck('id')
                ->unique()
                ->count() ?: null,
        ]);
    }

    public function productImageUrl(): ?string
    {
        if (! $this->productImagePath) {
            return null;
        }

        try {
            return Storage::disk('b2')->temporaryUrl($this->productImagePath, now()->addHour());
        } catch (Throwable) {
            return Storage::disk('b2')->url($this->productImagePath);
        }
    }

    public function setForecastType(string $forecastType): void
    {
        abort_unless(auth()->user()?->can('view material forecast'), 403);

        if (! in_array($forecastType, ['kitchen', 'store'], true)) {
            return;
        }

        $this->forecastType = $forecastType;
    }

    public function saveForecastPercentage(): void
    {
        abort_unless(auth()->user()?->can('edit material forecast'), 403);
        $validated = $this->validate([
            'forecastPercentage' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);

        $this->record->update([
            'forecast_percentage' => (float) $validated['forecastPercentage'],
        ]);
        $this->reloadProject();
        $this->forecastPercentage = (string) (float) $this->record->forecast_percentage;

        Notification::make()
            ->title('Persentase forecast berhasil disimpan')
            ->body('Kebutuhan Kitchen dan Store dihitung dari '.$this->forecastPercentage.'% total Sales Projection.')
            ->success()
            ->send();
    }

    /** @return array{rows: list<array{code: string, name: string, unit: string, quantity: float, product_count: int}>, projection_details: list<array{name: string, quantity: float, effective_quantity: float, is_calculated: bool}>, forecast_percentage: float, projected_units: float, effective_projected_units: float, projection_products: int, projected_products: int, warnings: list<string>} */
    public function materialForecast(): array
    {
        abort_unless(auth()->user()?->can('view material forecast'), 403);

        return app(RndProjectMaterialForecastService::class)->calculate($this->record, $this->forecastType);
    }

    private function reloadProject(): void
    {
        $this->record->refresh()->load([
            'products.boms.documentMaterials',
            'products.currentRegionalPrices.region',
            'products.salesProjections.region',
            'products.salesProjections.targetBranches',
            'boms.documentMaterials',
            'documents.creator',
        ]);
    }
}
