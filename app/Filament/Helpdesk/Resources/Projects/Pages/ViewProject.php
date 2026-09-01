<?php

namespace App\Filament\Helpdesk\Resources\Projects\Pages;

use App\Filament\Helpdesk\Resources\Projects\ProjectResource;
use App\Http\Controllers\Helpdesk\RndProjectBomPdfController;
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

    public string $storageCondition = 'ambient';

    public string $storageNotes = '';

    public string $targetOutlets = '';

    public array $salesProjections = [];

    public $productPhoto = null;

    public string $productImagePath = '';

    public bool $projectExportPinModalOpen = false;

    public string $projectExportPin = '';

    public string $projectExportScope = 'kitchen';

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

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Edit Project'),
        ];
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
        $this->storageCondition = $product->storage_condition ?? 'ambient';
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
            'regionalPrices.*.region_id' => ['required', 'integer', 'exists:sales_regions,id'],
            'regionalPrices.*.offline_price' => ['required', 'numeric', 'min:0'],
            'regionalPrices.*.online_price' => ['required', 'numeric', 'min:0'],
            'releaseDate' => ['nullable', 'date'],
            'productStatus' => ['required', Rule::in(array_keys(RndProjectProduct::STATUSES))],
            'shelfLifeValue' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'shelfLifeUnit' => ['nullable', Rule::in(array_keys(RndProjectProduct::SHELF_LIFE_UNITS))],
            'storageCondition' => ['nullable', Rule::in(array_keys(RndProjectProduct::STORAGE_CONDITIONS))],
            'storageNotes' => ['nullable', 'string', 'max:2000'],
            'targetOutlets' => ['nullable', 'integer', 'min:1'],
            'salesProjections' => ['array'],
            'salesProjections.*.id' => ['nullable', 'integer'],
            'salesProjections.*.projection_month' => ['required', 'date_format:Y-m'],
            'salesProjections.*.sales_region_id' => ['required', 'integer', 'exists:sales_regions,id'],
            'salesProjections.*.channel' => ['required', Rule::in(array_keys(RndProductSalesProjection::CHANNELS))],
            'salesProjections.*.target_quantity' => ['required', 'numeric', 'gt:0'],
            'salesProjections.*.target_revenue' => ['required', 'numeric', 'min:0'],
            'salesProjections.*.target_outlets' => ['nullable', 'integer', 'min:1'],
            'salesProjections.*.notes' => ['nullable', 'string', 'max:1000'],
            'productPhoto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $activeRegionIds = SalesRegion::query()->where('is_active', true)->pluck('id')->sort()->values();
        $submittedRegionIds = collect($validated['regionalPrices'])->pluck('region_id')->map(fn ($id) => (int) $id)->sort()->values();
        if ($activeRegionIds->values()->all() !== $submittedRegionIds->all()) {
            $this->addError('regionalPrices', 'Harga harus diisi untuk seluruh region aktif.');

            return;
        }
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
        if (in_array($validated['productStatus'], ['ready', 'released'], true)) {
            $planningIsInvalid = false;
            if (blank($validated['releaseDate']) || blank($validated['shelfLifeValue']) || blank($validated['storageCondition'])) {
                $this->addError('shelfLifeValue', 'Shelf life, kondisi penyimpanan, dan tanggal rilis wajib sebelum produk Ready/Released.');
                $planningIsInvalid = true;
            }
            if ($validated['salesProjections'] === []) {
                $this->addError('salesProjections', 'Minimal satu sales projection wajib sebelum produk Ready/Released.');
                $planningIsInvalid = true;
            }

            if ($planningIsInvalid) {
                return;
            }
            foreach ($validated['regionalPrices'] as $index => $price) {
                if ((float) $price['offline_price'] <= 0 || (float) $price['online_price'] <= 0) {
                    $this->addError("regionalPrices.$index.offline_price", 'Harga online dan offline wajib diisi sebelum produk Ready/Released.');

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

        $minimumOffline = collect($validated['regionalPrices'])->min(fn ($price) => (float) $price['offline_price']) ?? 0;
        $minimumOnline = collect($validated['regionalPrices'])->min(fn ($price) => (float) $price['online_price']) ?? 0;
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
            'target_outlets' => $validated['targetOutlets'] ?: null,
            'status' => $validated['productStatus'],
        ];
        if ($newImagePath) {
            $payload['image_path'] = $newImagePath;
        }

        try {
            DB::transaction(function () use ($payload, $validated, &$message): void {
                if ($this->editingProductId) {
                    $product = $this->record->products()->findOrFail($this->editingProductId);
                    $product->update($payload);
                    $message = 'Product berhasil diperbarui';
                } else {
                    $product = $this->record->products()->create($payload + ['created_by' => auth()->id()]);
                    $message = 'Product berhasil ditambahkan';
                }
                $this->saveRegionalPrices($product, $validated['regionalPrices'], $validated['priceEffectiveFrom']);
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
        $this->validate(['projectExportPin' => ['required', 'string', 'max:20']]);
        $rateKey = 'rnd-project-bom-export-pin:'.auth()->id().':'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $this->addError('projectExportPin', 'Terlalu banyak percobaan. Coba kembali dalam '.RateLimiter::availableIn($rateKey).' detik.');

            return null;
        }

        $configuredPin = (string) config('rnd.bom_pin');
        if ($configuredPin === '' || ! hash_equals($configuredPin, $this->projectExportPin)) {
            RateLimiter::hit($rateKey, 60);
            $this->reset('projectExportPin');
            $this->addError('projectExportPin', $configuredPin === '' ? 'PIN resep belum dikonfigurasi.' : 'PIN yang dimasukkan tidak sesuai.');

            return null;
        }

        RateLimiter::clear($rateKey);
        session()->put(
            RndProjectBomPdfController::sessionKey(auth()->id(), $this->record->id),
            now()->addMinutes(config('rnd.bom_pin_ttl_minutes', 15))->timestamp,
        );

        return $this->redirect(route('helpdesk.rnd-projects.bom-pdf', [
            'project' => $this->record->id,
            'scope' => $this->projectExportScope,
        ]), navigate: false);
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
        $this->storageCondition = 'ambient';
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
            ->orderByDesc('effective_from')
            ->get()
            ->unique('sales_region_id')
            ->keyBy('sales_region_id') ?? collect();

        $this->regionalPrices = $regions->map(function (SalesRegion $region) use ($existing, $product): array {
            $price = $existing->get($region->id);

            return [
                'region_id' => $region->id,
                'region_name' => $region->name,
                'region_code' => $region->code,
                'offline_price' => (string) ($price?->offline_price ?? $product?->offline_price ?? 0),
                'online_price' => (string) ($price?->online_price ?? $product?->online_price ?? 0),
            ];
        })->all();
    }

    private function saveRegionalPrices(RndProjectProduct $product, array $prices, string $effectiveFrom): void
    {
        $effectiveDate = Carbon::parse($effectiveFrom)->startOfDay();
        foreach ($prices as $price) {
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
                'offline_price' => (float) $price['offline_price'],
                'online_price' => (float) $price['online_price'],
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
            'target_outlets' => $this->targetOutlets,
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
        $this->salesProjections = $product->salesProjections()->get()->map(fn (RndProductSalesProjection $projection): array => [
            'id' => $projection->id,
            'projection_month' => $projection->projection_month->format('Y-m'),
            'sales_region_id' => $projection->sales_region_id,
            'channel' => $projection->channel,
            'target_quantity' => (string) $projection->target_quantity,
            'target_revenue' => (string) $projection->target_revenue,
            'target_outlets' => (string) ($projection->target_outlets ?? ''),
            'notes' => $projection->notes ?? '',
        ])->all();
    }

    private function saveSalesProjections(RndProjectProduct $product, array $projections): void
    {
        $keptIds = [];
        foreach ($projections as $projection) {
            $values = [
                'sales_region_id' => $projection['sales_region_id'],
                'projection_month' => Carbon::createFromFormat('Y-m', $projection['projection_month'])->startOfMonth(),
                'channel' => $projection['channel'],
                'target_quantity' => $projection['target_quantity'],
                'target_revenue' => $projection['target_revenue'],
                'target_outlets' => $projection['target_outlets'] ?: null,
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
            $keptIds[] = $record->id;
        }

        $product->salesProjections()
            ->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))
            ->delete();
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
        ]);
    }
}
