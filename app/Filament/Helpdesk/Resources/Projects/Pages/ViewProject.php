<?php

namespace App\Filament\Helpdesk\Resources\Projects\Pages;

use App\Filament\Helpdesk\Resources\Projects\ProjectResource;
use App\Http\Controllers\Helpdesk\RndProjectBomPdfController;
use App\Models\Branch;
use App\Models\RndProductSalesProjection;
use App\Models\RndProjectProduct;
use App\Models\SalesRegion;
use Carbon\Carbon;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\WithFileUploads;
use Throwable;

class ViewProject extends ViewRecord
{
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

    public string $shelfLifeValue = '';

    public string $shelfLifeUnit = 'month';

    public string $storageCondition = 'dry';

    public string $storageNotes = '';

    public string $targetOutlets = '';

    public array $salesProjections = [];

    public array $ccpDocumentUploads = [];

    public $productPhoto = null;

    public string $productImagePath = '';

    public bool $projectExportPinModalOpen = false;

    public string $projectExportPin = '';

    public string $projectExportScope = 'kitchen';

    /** @var list<int> */
    public array $projectExportBomIds = [];

    /** @var array<int, list<string>> */
    public array $projectExportBomComponentKeys = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->reloadProject();
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
        return [
            EditAction::make()->label('Edit Project'),
        ];
    }

    public function addCcpDocumentUpload(): void
    {
        abort_unless(ProjectResource::canEdit($this->record), 403);
        $this->ccpDocumentUploads[] = ['name' => '', 'file' => null];
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
        $this->shelfLifeValue = (string) ($product->shelf_life_value ?? '');
        $this->shelfLifeUnit = $product->shelf_life_unit ?? 'month';
        $this->storageCondition = $product->storage_condition ?? 'dry';
        $this->storageNotes = $product->storage_notes ?? '';
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
            'shelfLifeValue' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'shelfLifeUnit' => ['nullable', Rule::in(array_keys(RndProjectProduct::SHELF_LIFE_UNITS))],
            'storageCondition' => ['nullable', Rule::in(array_keys(RndProjectProduct::STORAGE_CONDITIONS))],
            'storageNotes' => ['nullable', 'string', 'max:2000'],
            'salesProjections' => ['array'],
            'salesProjections.*.id' => ['nullable', 'integer'],
            'salesProjections.*.projection_month' => ['required', 'date_format:Y-m'],
            'salesProjections.*.sales_region_id' => ['required', 'integer', 'exists:sales_regions,id'],
            'salesProjections.*.channel' => ['required', Rule::in(array_keys(RndProductSalesProjection::CHANNELS))],
            'salesProjections.*.target_quantity' => ['nullable', 'numeric', 'min:0'],
            'salesProjections.*.target_revenue' => ['required', 'numeric', 'min:0'],
            'salesProjections.*.target_outlets' => ['nullable', 'integer', 'min:1'],
            'salesProjections.*.branch_targets' => ['required', 'array'],
            'salesProjections.*.branch_targets.*.branch_id' => ['required', 'integer', 'exists:branches,id'],
            'salesProjections.*.branch_targets.*.enabled' => ['required', 'boolean'],
            'salesProjections.*.branch_targets.*.target_quantity' => ['nullable', 'numeric', 'min:0'],
            'salesProjections.*.notes' => ['nullable', 'string', 'max:1000'],
            'productPhoto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $selectedRegionalPrices = collect($validated['regionalPrices'])
            ->where('enabled', true)
            ->values()
            ->all();
        $projectionKeys = collect($validated['salesProjections'])->map(
            fn (array $projection): string => implode('|', [
                $projection['projection_month'],
                $projection['sales_region_id'],
                $projection['channel'],
            ]),
        );
        if ($projectionKeys->unique()->count() !== $projectionKeys->count()) {
            $this->addError('salesProjections', 'Periode, region, dan channel tidak boleh duplikat dalam satu product.');

            return;
        }
        foreach ($validated['salesProjections'] as $projectionIndex => $projection) {
            $enabledTargets = collect($projection['branch_targets'])->where('enabled', true);
            if ($enabledTargets->isEmpty()) {
                $this->addError("salesProjections.$projectionIndex.branch_targets", 'Pilih minimal satu branch untuk target quantity.');

                return;
            }
            foreach ($enabledTargets as $targetIndex => $target) {
                if ((float) ($target['target_quantity'] ?? 0) <= 0) {
                    $this->addError("salesProjections.$projectionIndex.branch_targets.$targetIndex.target_quantity", 'Target quantity branch wajib lebih dari 0.');

                    return;
                }
            }
        }
        if (in_array($validated['productStatus'], ['ready', 'released'], true)) {
            $planningIsInvalid = false;

            if (blank($validated['releaseDate'])) {
                $this->addError('releaseDate', 'Tanggal rilis wajib diisi sebelum produk Ready/Released.');
                $planningIsInvalid = true;
            }
            if (blank($validated['shelfLifeValue'])) {
                $this->addError('shelfLifeValue', 'Shelf life wajib diisi sebelum produk Ready/Released.');
                $planningIsInvalid = true;
            }
            if (blank($validated['shelfLifeUnit'])) {
                $this->addError('shelfLifeUnit', 'Satuan shelf life wajib dipilih sebelum produk Ready/Released.');
                $planningIsInvalid = true;
            }
            if (blank($validated['storageCondition'])) {
                $this->addError('storageCondition', 'Kondisi penyimpanan wajib dipilih sebelum produk Ready/Released.');
                $planningIsInvalid = true;
            }
            if ($validated['salesProjections'] === []) {
                $this->addError('salesProjections', 'Minimal satu sales projection wajib sebelum produk Ready/Released.');
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
            'shelf_life_value' => $validated['shelfLifeValue'] ?: null,
            'shelf_life_unit' => $validated['shelfLifeValue'] ? $validated['shelfLifeUnit'] : null,
            'storage_condition' => $validated['shelfLifeValue'] ? $validated['storageCondition'] : null,
            'storage_notes' => trim($validated['storageNotes']) ?: null,
            'target_outlets' => null,
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
                $this->saveSalesProjections($product, $validated['salesProjections']);
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

        if ($product->boms()->exists()) {
            Notification::make()
                ->title('Product masih memiliki BOM')
                ->body('Lepas seluruh BOM dari product sebelum menghapusnya.')
                ->warning()
                ->send();

            return;
        }

        $imagePath = $product->image_path;
        $materialPaths = $product->marketingMaterials()->pluck('file_path')->all();
        $product->delete();
        if ($imagePath) {
            Storage::disk('b2')->delete($imagePath);
        }
        if ($materialPaths !== []) {
            Storage::disk('b2')->delete($materialPaths);
        }
        $this->reloadProject();
        Notification::make()->title('Product berhasil dihapus')->success()->send();
    }

    public function openProjectBomExport(string $scope): void
    {
        abort_unless(auth()->user()?->can('view bill of materials'), 403);
        abort_unless(in_array($scope, ['kitchen', 'store'], true), 422);
        $this->projectExportScope = $scope;
        $this->projectExportBomIds = $this->eligibleProjectExportBoms($scope)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->projectExportBomComponentKeys = [];
        $this->projectExportPin = '';
        $this->resetValidation('projectExportPin');
        $this->projectExportPinModalOpen = true;
    }

    public function closeProjectBomExport(): void
    {
        $this->projectExportPinModalOpen = false;
        $this->projectExportPin = '';
        $this->resetValidation('projectExportPin');
    }

    public function exportProjectBomPdf(): mixed
    {
        abort_unless(auth()->user()?->can('view bill of materials'), 403);
        $eligibleBomIds = $this->eligibleProjectExportBoms($this->projectExportScope)->pluck('id')->map(fn ($id): int => (int) $id);
        if ($this->projectExportScope === 'store') {
            $this->projectExportBomIds = $eligibleBomIds->all();
        }

        $rules = [
            'projectExportPin' => ['required', 'string', 'max:20'],
        ];
        if ($this->projectExportScope !== 'store') {
            $rules['projectExportBomIds'] = ['required', 'array', 'min:1'];
            $rules['projectExportBomIds.*'] = ['integer'];
        }
        $this->validate($rules);
        abort_unless(collect($this->projectExportBomIds)->every(fn ($id): bool => $eligibleBomIds->contains((int) $id)), 422);
        $rateKey = 'rnd-project-bom-export-pin:'.auth()->id().':'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $this->addError('projectExportPin', 'Terlalu banyak percobaan. Coba kembali dalam '.RateLimiter::availableIn($rateKey).' detik.');

            return null;
        }

        if (! auth()->user()?->hasBomPin()) {
            $this->reset('projectExportPin');
            $this->addError('projectExportPin', 'PIN BOM Anda belum diset. Silakan set PIN terlebih dahulu melalui CMS User.');

            return null;
        }
        if (! auth()->user()?->verifiesBomPin($this->projectExportPin)) {
            RateLimiter::hit($rateKey, 60);
            $this->reset('projectExportPin');
            $this->addError('projectExportPin', 'PIN yang dimasukkan tidak sesuai.');

            return null;
        }

        RateLimiter::clear($rateKey);
        session()->put(
            RndProjectBomPdfController::sessionKey(auth()->id(), $this->record->id),
            now()->addMinutes(config('rnd.bom_pin_ttl_minutes', 15))->timestamp,
        );
        session()->forget(RndProjectBomPdfController::componentSessionKey(auth()->id(), $this->record->id));

        $routeParameters = [
            'project' => $this->record->id,
            'scope' => $this->projectExportScope,
        ];
        if ($this->projectExportScope !== 'store' && $eligibleBomIds->sort()->values()->all() !== collect($this->projectExportBomIds)->map(fn ($id): int => (int) $id)->sort()->values()->all()) {
            $routeParameters['bom_ids'] = collect($this->projectExportBomIds)->map(fn ($id): int => (int) $id)->implode(',');
        }

        return $this->redirect(route('helpdesk.rnd-projects.bom-pdf', $routeParameters), navigate: false);
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
        $this->shelfLifeValue = '';
        $this->shelfLifeUnit = 'month';
        $this->storageCondition = 'dry';
        $this->storageNotes = '';
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
        unset($this->salesProjections[$index]);
        $this->salesProjections = array_values($this->salesProjections);
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
                    'branch_targets' => $this->emptyBranchTargets($existingTargets),
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

    private function saveSalesProjections(RndProjectProduct $product, array $projections): void
    {
        $keptIds = [];
        foreach ($projections as $projection) {
            $enabledBranchTargets = collect($projection['branch_targets'])->where('enabled', true);
            $values = [
                'sales_region_id' => $projection['sales_region_id'],
                'projection_month' => Carbon::createFromFormat('Y-m', $projection['projection_month'])->startOfMonth(),
                'channel' => $projection['channel'],
                'target_quantity' => $enabledBranchTargets->sum(fn (array $target): float => (float) $target['target_quantity']),
                'target_revenue' => $projection['target_revenue'],
                'target_outlets' => $enabledBranchTargets->count(),
                'notes' => trim($projection['notes']) ?: null,
            ];
            $record = filled($projection['id'] ?? null)
                ? $product->salesProjections()->findOrFail((int) $projection['id'])
                : null;

            if ($record) {
                $record->update($values);
            } else {
                $record = $product->salesProjections()->create($values + ['created_by' => auth()->id()]);
            }
            $record->targetBranches()->sync(
                $enabledBranchTargets->mapWithKeys(fn (array $target): array => [
                    (int) $target['branch_id'] => ['target_quantity' => (float) $target['target_quantity']],
                ])->all(),
            );
            $keptIds[] = $record->id;
        }

        $product->salesProjections()
            ->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))
            ->delete();

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

    private function reloadProject(): void
    {
        $this->record->refresh()->load([
            'products.boms',
            'products.currentRegionalPrices.region',
            'products.salesProjections.region',
            'products.salesProjections.targetBranches',
            'documents.creator',
        ]);
    }
}
