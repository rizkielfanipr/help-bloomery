<?php

namespace App\Filament\Helpdesk\Pages;

use App\Http\Controllers\Helpdesk\RndProductBomPdfController;
use App\Models\RndBomInstruction;
use App\Models\RndProductEsbMaterial;
use App\Models\RndProductEsbMaterialUnit;
use App\Models\RndProject;
use App\Models\RndProjectBom;
use App\Models\RndProjectMarketingMaterial;
use App\Models\RndProjectProduct;
use App\Services\EsbCoreService;
use App\Services\EsbService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\WithFileUploads;
use Throwable;

class ViewProjectProductPage extends Page
{
    use WithFileUploads;

    protected static ?string $slug = 'rnd-projects/{project}/products/{product}';

    protected static ?string $title = 'Product Release';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.view-project-product';

    protected Width|string|null $maxContentWidth = Width::Full;

    public int $projectId;

    public int $productId;

    public RndProject $projectRecord;

    public RndProjectProduct $productRecord;

    public array $importBomOptions = [];

    public string $importSearch = '';

    public string $importBomCodeSearch = '';

    public string $importBomNameSearch = '';

    public string $importBomProductSearch = '';

    public string $importBomUnitSearch = '';

    public string $importBomTypeSearch = '';

    public string $importUsageType = 'main';

    public ?int $importParentBomId = null;

    public int $importPage = 1;

    public int $importPerPage = 10;

    public bool $importModalOpen = false;

    public bool $materialModalOpen = false;

    public bool $esbMaterialModalOpen = false;

    public bool $exportPinModalOpen = false;

    public bool $inlineProductModalOpen = false;

    public string $materialType = 'packaging_design';

    public string $materialTitle = '';

    public string $materialNotes = '';

    public $materialFile = null;

    public string $exportPin = '';

    public ?int $materialDraftId = null;

    public string $esbMaterialProductName = '';

    public string $esbMaterialProductBaseName = '';

    public string $esbMaterialNamePrefix = '';

    public string $esbMaterialProductCode = '';

    public ?int $esbMaterialCategoryId = null;

    public ?int $esbMaterialSubCategoryId = null;

    public ?int $esbMaterialUomId = null;

    public string $esbMaterialUomName = '';

    public string $esbMaterialSku = '';

    public string $esbMaterialConversionFactor = '1';

    public string $esbMaterialBasePrice = '0';

    public string $esbMaterialNotes = '';

    public array $esbMaterialUnits = [];

    public array $esbCategoryOptions = [];

    public array $esbSubCategoryOptions = [];

    public array $bomComponentDetails = [];

    public array $bomComponentDrafts = [];

    public array $bomComponentExpanded = [];

    public array $bomComponentEditing = [];

    public bool $bomComponentsInitialized = false;

    public array $autoWipComponentRecipes = [];

    public array $autoPackagingItems = [];

    public ?string $autoWipComponentError = null;

    public array $bomInstructions = [];

    public ?int $bomInstructionEsbId = null;

    public string $bomInstructionName = '';

    public string $bomInstructionHtml = '';

    public array $bomInstructionUploads = [];

    public array $bomInstructionInlineUploads = [];

    public array $bomInstructionTextDrafts = [];

    public array $inlineProductOptions = [];

    public array $inlineProductCategoryOptions = [];

    public array $inlineProductSubCategoryOptions = [];

    public array $inlineProductUnitOptions = [];

    public string $inlineProductNameSearch = '';

    public string $inlineProductCodeSearch = '';

    public string $inlineProductCategoryId = '';

    public string $inlineProductSubCategoryId = '';

    public int $inlineProductPage = 1;

    public int $inlineProductTotal = 0;

    public int $inlineProductPerPage = 10;

    public bool $inlineProductHasNext = false;

    public ?int $inlineProductBomId = null;

    public string $inlineProductTarget = 'component';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('SUPERADMIN') ?? false;
    }

    public function mount(int $project, int $product): void
    {
        $this->projectRecord = RndProject::query()->findOrFail($project);
        $this->productRecord = $this->projectRecord->products()->findOrFail($product);
        $this->projectId = $this->projectRecord->id;
        $this->productId = $this->productRecord->id;
        $this->reloadProduct();
    }

    public function getTitle(): string
    {
        return $this->productRecord->name;
    }

    public function closeModal(string $modal): void
    {
        match ($modal) {
            'import' => $this->importModalOpen = false,
            'material' => $this->materialModalOpen = false,
            'esbMaterial' => $this->esbMaterialModalOpen = false,
            'exportPin' => $this->exportPinModalOpen = false,
            'inlineProduct' => $this->inlineProductModalOpen = false,
            default => abort(422),
        };
    }

    public function openBomInstruction(int $esbBomId, string $bomName): void
    {
        $this->authorizeProjectManagement();
        $instruction = RndBomInstruction::query()
            ->where('rnd_project_id', $this->projectId)
            ->where('rnd_project_product_id', $this->productId)
            ->where('esb_bom_id', $esbBomId)
            ->first();

        $this->bomInstructionEsbId = $esbBomId;
        $this->bomInstructionName = $bomName;
        $this->bomInstructionHtml = (string) ($instruction?->content_html ?? '');
        $this->bomInstructionUploads = [];
        $this->resetValidation();
        $this->dispatch('open-bom-instruction');
    }

    public function saveBomInstruction(): void
    {
        $this->authorizeProjectManagement();
        $validated = $this->validate([
            'bomInstructionEsbId' => ['required', 'integer', 'min:1'],
            'bomInstructionHtml' => ['nullable', 'string', 'max:100000'],
            'bomInstructionUploads' => ['array', 'max:8'],
            'bomInstructionUploads.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);
        $instruction = RndBomInstruction::query()->firstOrNew([
            'rnd_project_id' => $this->projectId,
            'rnd_project_product_id' => $this->productId,
            'esb_bom_id' => $validated['bomInstructionEsbId'],
        ]);
        $newPaths = [];

        try {
            foreach ($this->bomInstructionUploads as $upload) {
                $path = $upload->store(
                    "rnd/bom-instructions/{$this->projectId}/{$this->productId}/{$validated['bomInstructionEsbId']}",
                    'b2',
                );
                if (! is_string($path) || $path === '') {
                    throw new \RuntimeException('Gambar instruksi gagal diunggah ke Cloudflare R2.');
                }
                $newPaths[] = $path;
            }

            $instruction->fill([
                'content_html' => $this->sanitizeBomInstruction($validated['bomInstructionHtml'] ?? ''),
                'image_paths' => array_values(array_merge($instruction->image_paths ?? [], $newPaths)),
                'updated_by' => auth()->id(),
            ])->save();
        } catch (Throwable $exception) {
            if ($newPaths !== []) {
                Storage::disk('b2')->delete($newPaths);
            }
            throw $exception;
        }

        $this->loadBomInstructions();
        $this->bomInstructionUploads = [];
        $this->dispatch('close-bom-instruction');
        Notification::make()->title('Informasi tambahan BOM berhasil disimpan')->success()->send();
    }

    public function saveInlineBomInstruction(int $esbBomId, string $contentHtml): void
    {
        $this->authorizeProjectManagement();
        validator(
            ['content' => $contentHtml, 'uploads' => $this->bomInstructionInlineUploads[$esbBomId] ?? []],
            [
                'content' => ['nullable', 'string', 'max:100000'],
                'uploads' => ['array', 'max:8'],
                'uploads.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            ],
        )->validate();

        $instruction = RndBomInstruction::query()->firstOrNew([
            'rnd_project_id' => $this->projectId,
            'rnd_project_product_id' => $this->productId,
            'esb_bom_id' => $esbBomId,
        ]);
        $newPaths = [];

        try {
            foreach ($this->bomInstructionInlineUploads[$esbBomId] ?? [] as $upload) {
                $path = $upload->store(
                    "rnd/bom-instructions/{$this->projectId}/{$this->productId}/{$esbBomId}",
                    'b2',
                );
                if (! is_string($path) || $path === '') {
                    throw new \RuntimeException('Gambar instruksi gagal diunggah ke Cloudflare R2.');
                }
                $newPaths[] = $path;
            }

            $instruction->fill([
                'content_html' => $this->sanitizeBomInstruction($contentHtml),
                'image_paths' => array_values(array_merge($instruction->image_paths ?? [], $newPaths)),
                'updated_by' => auth()->id(),
            ])->save();
        } catch (Throwable $exception) {
            if ($newPaths !== []) {
                Storage::disk('b2')->delete($newPaths);
            }
            throw $exception;
        }

        unset($this->bomInstructionInlineUploads[$esbBomId]);
        $this->loadBomInstructions();
        Notification::make()->title('Informasi BOM berhasil disimpan')->success()->send();
    }

    public function saveInlineBomInstructionDraft(int $esbBomId): void
    {
        $text = (string) ($this->bomInstructionTextDrafts[$esbBomId] ?? '');
        $contentHtml = $text === ''
            ? ''
            : '<p>'.nl2br(e($text), false).'</p>';

        $this->saveInlineBomInstruction($esbBomId, $contentHtml);
    }

    public function deleteBomInstructionImage(int $esbBomId, string $encodedPath): void
    {
        $this->authorizeProjectManagement();
        $path = base64_decode($encodedPath, true);
        abort_unless(is_string($path) && $path !== '', 422);
        $instruction = RndBomInstruction::query()
            ->where('rnd_project_id', $this->projectId)
            ->where('rnd_project_product_id', $this->productId)
            ->where('esb_bom_id', $esbBomId)
            ->firstOrFail();
        abort_unless(in_array($path, $instruction->image_paths ?? [], true), 404);

        $instruction->update([
            'image_paths' => array_values(array_filter(
                $instruction->image_paths ?? [],
                fn (string $item): bool => $item !== $path,
            )),
            'updated_by' => auth()->id(),
        ]);
        Storage::disk('b2')->delete($path);
        $this->loadBomInstructions();
    }

    private function loadBomInstructions(): void
    {
        $instructions = RndBomInstruction::query()
            ->where('rnd_project_id', $this->projectId)
            ->where('rnd_project_product_id', $this->productId)
            ->get();

        $this->bomInstructions = $instructions
            ->mapWithKeys(fn (RndBomInstruction $instruction): array => [
                $instruction->esb_bom_id => [
                    'content_html' => $instruction->content_html,
                    'images' => $instruction->imageUrls(),
                    'updated_at' => $instruction->updated_at?->toIso8601String(),
                ],
            ])
            ->all();

        foreach ($instructions as $instruction) {
            if (! array_key_exists($instruction->esb_bom_id, $this->bomInstructionTextDrafts)) {
                $html = preg_replace('/<br\s*\/?>/i', "\n", (string) $instruction->content_html) ?? '';
                $html = preg_replace('/<\/(p|div|h[1-6]|li)>/i', "\n", $html) ?? $html;
                $this->bomInstructionTextDrafts[$instruction->esb_bom_id] = trim(
                    html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                );
            }
        }
    }

    private function sanitizeBomInstruction(string $html): string
    {
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><s><ol><ul><li><h1><h2><h3><blockquote><a>');
        $html = preg_replace('/\son\w+\s*=\s*(["\']).*?\1/iu', '', $html) ?? '';
        $html = preg_replace('/javascript\s*:/iu', '', $html) ?? '';

        return trim($html);
    }

    public function loadImportBoms(): void
    {
        if ($this->importBomOptions !== []) {
            return;
        }

        try {
            $attachedToProduct = $this->productRecord->boms()->pluck('rnd_project_boms.esb_bom_id')->all();
            $ownedByOtherProjects = RndProjectBom::query()
                ->where('rnd_project_id', '!=', $this->projectId)
                ->pluck('esb_bom_id')
                ->all();
            $excluded = array_merge($attachedToProduct, $ownedByOtherProjects);

            $this->importBomOptions = array_values(array_filter(
                app(EsbCoreService::class)->getAllBillOfMaterials(),
                fn (array $bom): bool => ! in_array((int) ($bom['bomID'] ?? 0), $excluded, true),
            ));
        } catch (\RuntimeException $exception) {
            $this->importBomOptions = [];
            Notification::make()->title('Daftar BOM belum dapat dimuat')->body($exception->getMessage())->danger()->send();
        }
    }

    public function openBomPicker(string $usageType, ?int $parentBomId = null): void
    {
        $this->authorizeBomManagement();
        abort_unless(array_key_exists($usageType, RndProjectProduct::BOM_USAGE_TYPES), 422);
        if ($usageType !== 'main') {
            abort_unless($parentBomId !== null && $this->isAttachedMainBom($parentBomId), 422);
        }
        $this->importUsageType = $usageType;
        $this->importParentBomId = $usageType === 'main' ? null : $parentBomId;
        $this->importSearch = '';
        $this->importBomCodeSearch = '';
        $this->importBomNameSearch = '';
        $this->importBomProductSearch = '';
        $this->importBomUnitSearch = '';
        $this->importBomTypeSearch = '';
        $this->importPage = 1;
        $this->importModalOpen = true;
    }

    public function updatedImportSearch(): void
    {
        $this->importPage = 1;
    }

    public function updatedImportBomCodeSearch(): void
    {
        $this->importPage = 1;
    }

    public function updatedImportBomNameSearch(): void
    {
        $this->importPage = 1;
    }

    public function updatedImportBomProductSearch(): void
    {
        $this->importPage = 1;
    }

    public function updatedImportBomUnitSearch(): void
    {
        $this->importPage = 1;
    }

    public function updatedImportBomTypeSearch(): void
    {
        $this->importPage = 1;
    }

    public function filteredImportBoms(): array
    {
        $search = mb_strtolower(trim($this->importSearch));
        $code = mb_strtolower(trim($this->importBomCodeSearch));
        $name = mb_strtolower(trim($this->importBomNameSearch));
        $product = mb_strtolower(trim($this->importBomProductSearch));
        $unit = mb_strtolower(trim($this->importBomUnitSearch));
        $type = mb_strtolower(trim($this->importBomTypeSearch));

        return array_values(array_filter($this->importBomOptions, function (array $bom) use ($search, $code, $name, $product, $unit, $type): bool {
            $bomCode = mb_strtolower((string) ($bom['bomCode'] ?? ''));
            $bomName = mb_strtolower((string) ($bom['bomName'] ?? ''));
            $productName = mb_strtolower((string) ($bom['productName'] ?? ''));
            $uomName = mb_strtolower((string) ($bom['uomName'] ?? ''));
            $typeName = mb_strtolower((string) ($bom['bomTypeName'] ?? 'Assembly'));
            $all = implode(' ', [$bomCode, $bomName, $productName, $uomName, $typeName]);

            return ($search === '' || str_contains($all, $search))
                && ($code === '' || str_contains($bomCode, $code))
                && ($name === '' || str_contains($bomName, $name))
                && ($product === '' || str_contains($productName, $product))
                && ($unit === '' || $uomName === $unit)
                && ($type === '' || $typeName === $type);
        }));
    }

    public function importRows(): array
    {
        return array_slice(
            $this->filteredImportBoms(),
            ($this->importPage - 1) * $this->importPerPage,
            $this->importPerPage,
        );
    }

    public function goToImportPage(int $page): void
    {
        $lastPage = max(1, (int) ceil(count($this->filteredImportBoms()) / $this->importPerPage));
        $this->importPage = min($lastPage, max(1, $page));
    }

    public function attachBom(int $bomId): void
    {
        $this->authorizeBomManagement();
        if ($this->importUsageType !== 'main') {
            abort_unless($this->importParentBomId !== null && $this->isAttachedMainBom($this->importParentBomId), 422);
        }

        $projectBom = RndProjectBom::query()->where('esb_bom_id', $bomId)->first();
        if ($projectBom && $projectBom->rnd_project_id !== $this->projectId) {
            Notification::make()->title('BOM dimiliki project lain')->warning()->send();

            return;
        }

        try {
            $detail = app(EsbCoreService::class)->getBillOfMaterial($bomId);

            if (! $projectBom) {
                $projectBom = $this->projectRecord->boms()->create([
                    'esb_bom_id' => $bomId,
                    'bom_code' => $detail['bomCode'] ?? null,
                    'bom_name' => $detail['bomName'] ?? 'BOM '.$bomId,
                    'product_name' => $detail['productName'] ?? null,
                    'uom_name' => $detail['uomName'] ?? null,
                    'bom_type_name' => $detail['bomTypeName'] ?? 'Assembly',
                    'is_active' => (int) ($detail['flagActive'] ?? 1) === 1,
                    'sync_status' => 'synced',
                    'detail_snapshot' => $detail,
                    'created_by' => auth()->id(),
                    'last_synced_at' => now(),
                ]);
            } else {
                $projectBom->update([
                    'bom_code' => $detail['bomCode'] ?? $projectBom->bom_code,
                    'bom_name' => $detail['bomName'] ?? $projectBom->bom_name,
                    'product_name' => $detail['productName'] ?? $projectBom->product_name,
                    'uom_name' => $detail['uomName'] ?? $projectBom->uom_name,
                    'bom_type_name' => $detail['bomTypeName'] ?? $projectBom->bom_type_name,
                    'is_active' => (int) ($detail['flagActive'] ?? 1) === 1,
                    'sync_status' => 'synced',
                    'detail_snapshot' => $detail,
                    'last_synced_at' => now(),
                ]);
            }

            $this->productRecord->boms()->syncWithoutDetaching([
                $projectBom->id => [
                    'usage_type' => $this->importUsageType,
                    'parent_rnd_project_bom_id' => $this->importUsageType === 'main' ? null : $this->importParentBomId,
                ],
            ]);
            $this->importBomOptions = array_values(array_filter(
                $this->importBomOptions,
                fn (array $bom): bool => (int) ($bom['bomID'] ?? 0) !== $bomId,
            ));
            $this->reloadProduct();
            $this->setBomComponentState($projectBom->id, $detail);
            $this->bomComponentExpanded[$projectBom->id] = true;
            Cache::forget("rnd.wip-recipes.{$projectBom->esb_bom_id}");
            Cache::forget("rnd.wip-recipes.v2.{$projectBom->esb_bom_id}");
            Cache::forget("rnd.wip-recipes.v3.{$projectBom->esb_bom_id}");
            Cache::forget("rnd.wip-recipes.v4.{$projectBom->esb_bom_id}");
            $shouldMapComponents = $this->importUsageType === 'main';
            $this->importModalOpen = false;
            $this->dispatch('close-import-bom');
            Notification::make()->title('BOM berhasil ditambahkan ke product')->success()->send();
            if ($shouldMapComponents) {
                $this->dispatch('run-rnd-bom-mapping');
            }
        } catch (\RuntimeException $exception) {
            Notification::make()->title('BOM gagal ditambahkan')->body($exception->getMessage())->danger()->send();
        }
    }

    public function detachBom(int $bomId): void
    {
        $this->authorizeBomManagement();
        $projectBom = $this->projectRecord->boms()->where('esb_bom_id', $bomId)->firstOrFail();
        $this->productRecord->boms()->detach($projectBom->id);
        $this->reloadProduct();
        Notification::make()->title('BOM dilepas dari product')->success()->send();
    }

    public function editMappedComponentBom(int $esbBomId, int $mainBomId): void
    {
        $this->authorizeBomUpdate();
        abort_unless($this->isAttachedMainBom($mainBomId), 422);

        try {
            $detail = app(EsbCoreService::class)->getBillOfMaterial($esbBomId);
            $projectBom = RndProjectBom::query()->where('esb_bom_id', $esbBomId)->first();

            if ($projectBom && $projectBom->rnd_project_id !== $this->projectId) {
                Notification::make()->title('BOM dimiliki project lain')->warning()->send();

                return;
            }

            $values = [
                'bom_code' => $detail['bomCode'] ?? null,
                'bom_name' => $detail['bomName'] ?? 'BOM '.$esbBomId,
                'product_name' => $detail['productName'] ?? null,
                'uom_name' => $detail['uomName'] ?? null,
                'bom_type_name' => $detail['bomTypeName'] ?? 'Assembly',
                'is_active' => (int) ($detail['flagActive'] ?? 1) === 1,
                'sync_status' => 'synced',
                'detail_snapshot' => $detail,
                'last_synced_at' => now(),
            ];

            if ($projectBom) {
                $projectBom->update($values);
            } else {
                $projectBom = $this->projectRecord->boms()->create($values + [
                    'esb_bom_id' => $esbBomId,
                    'created_by' => auth()->id(),
                ]);
            }

            $this->productRecord->boms()->syncWithoutDetaching([
                $projectBom->id => [
                    'usage_type' => 'component',
                    'parent_rnd_project_bom_id' => $mainBomId,
                ],
            ]);

            $this->reloadProduct();
            $this->setBomComponentState($projectBom->id, $detail);
            $this->bomComponentExpanded[$projectBom->id] = true;
            $this->bomComponentEditing[$projectBom->id] = true;
            $this->bomComponentDrafts[$projectBom->id] = $this->bomComponentDetails[$projectBom->id];
        } catch (Throwable $exception) {
            Notification::make()
                ->title('BOM belum dapat diedit')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function loadBomComponents(int $projectBomId, bool $force = false): void
    {
        abort_unless(static::canAccess(), 403);
        $projectBom = $this->attachedProjectBom($projectBomId);

        try {
            $detail = ! $force && $projectBom->detail_snapshot
                ? $projectBom->detail_snapshot
                : app(EsbCoreService::class)->getBillOfMaterial($projectBom->esb_bom_id);

            $projectBom->update([
                'detail_snapshot' => $detail,
                'sync_status' => 'synced',
                'last_synced_at' => now(),
            ]);
            $this->setBomComponentState($projectBomId, $detail);
            $this->bomComponentExpanded[$projectBomId] = true;
        } catch (Throwable $exception) {
            $projectBom->update(['sync_status' => 'failed']);
            Notification::make()
                ->title('Komponen BOM belum dapat dimuat')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function loadAllBomComponents(): void
    {
        if ($this->bomComponentsInitialized) {
            return;
        }

        $this->bomComponentsInitialized = true;
        foreach ($this->productRecord->boms as $bom) {
            $this->bomComponentExpanded[$bom->id] = true;
            if (! isset($this->bomComponentDetails[$bom->id])) {
                $this->loadBomComponents($bom->id);
            }
        }

        $this->discoverWipComponentRecipes();
    }

    public function refreshWipComponentRecipes(): void
    {
        foreach ($this->productRecord->boms as $bom) {
            Cache::forget("rnd.wip-recipes.{$bom->esb_bom_id}");
            Cache::forget("rnd.wip-recipes.v2.{$bom->esb_bom_id}");
            Cache::forget("rnd.wip-recipes.v3.{$bom->esb_bom_id}");
            Cache::forget("rnd.wip-recipes.v4.{$bom->esb_bom_id}");
        }

        $this->discoverWipComponentRecipes(true);
    }

    private function discoverWipComponentRecipes(bool $force = false): void
    {
        $this->autoWipComponentRecipes = [];
        $this->autoPackagingItems = [];
        $this->autoWipComponentError = null;

        try {
            $core = app(EsbCoreService::class);
            $mainBoms = $this->productRecord->boms->filter(
                fn (RndProjectBom $bom): bool => $bom->pivot->usage_type === 'main',
            );

            foreach ($mainBoms as $mainBom) {
                $mainDetail = $this->bomComponentDetails[$mainBom->id] ?? null;
                if (! is_array($mainDetail)) {
                    continue;
                }

                $this->autoPackagingItems[$mainBom->id] = collect($mainDetail['bomDetails'] ?? [])
                    ->filter(function (array $component): bool {
                        $code = strtoupper(trim((string) ($component['productCode'] ?? '')));

                        return str_starts_with($code, 'PAM') || str_starts_with($code, 'P');
                    })
                    ->map(fn (array $component): array => [
                        'productDetailID' => (int) ($component['productDetailID'] ?? 0),
                        'productCode' => (string) ($component['productCode'] ?? ''),
                        'productName' => (string) ($component['productName'] ?? ''),
                        'uomName' => (string) ($component['uomName'] ?? ''),
                        'qty' => (float) ($component['qty'] ?? 0),
                    ])
                    ->values()
                    ->all();

                $cacheKey = "rnd.wip-recipes.v4.{$mainBom->esb_bom_id}";
                if ($force) {
                    Cache::forget($cacheKey);
                }

                $this->autoWipComponentRecipes[$mainBom->id] = Cache::remember(
                    $cacheKey,
                    now()->addMinutes(30),
                    function () use ($mainDetail, $mainBom, $core): array {
                        $recipes = [];

                        foreach ($mainDetail['bomDetails'] ?? [] as $component) {
                            $productDetailId = (int) ($component['productDetailID'] ?? 0);
                            $productCode = strtoupper(trim((string) ($component['productCode'] ?? '')));
                            $isWipCode = str_starts_with($productCode, 'BW');
                            $categoryName = trim((string) ($component['categoryName'] ?? ''));

                            if (! $isWipCode && mb_strtolower($categoryName) !== 'barang wip') {
                                continue;
                            }

                            $productName = (string) ($component['productName'] ?? '');
                            $productId = (int) ($component['productID'] ?? 0);
                            $candidates = $core->getBillOfMaterials([
                                'productName' => $productName,
                                'limit' => 100,
                            ]);

                            foreach ($candidates['data'] as $candidate) {
                                $candidateBomId = (int) ($candidate['bomID'] ?? 0);
                                if ($candidateBomId < 1 || $candidateBomId === $mainBom->esb_bom_id) {
                                    continue;
                                }

                                $detail = $core->getBillOfMaterial($candidateBomId);
                                $sameProductDetail = (int) ($detail['productDetailID'] ?? 0) === $productDetailId;
                                $sameMasterProduct = $productId > 0
                                    && (int) ($detail['productID'] ?? 0) === $productId;
                                $sameProductCode = $productCode !== ''
                                    && strtoupper(trim((string) ($detail['productCode'] ?? ''))) === $productCode;

                                if (! $sameProductDetail && ! $sameMasterProduct && ! $sameProductCode) {
                                    continue;
                                }

                                $detail['bomDetails'] = $this->normalizedBomRows($detail['bomDetails'] ?? []);
                                $recipes[$candidateBomId] = [
                                    'bomID' => $candidateBomId,
                                    'bomCode' => (string) ($detail['bomCode'] ?? $candidate['bomCode'] ?? ''),
                                    'bomName' => (string) ($detail['bomName'] ?? $candidate['bomName'] ?? ''),
                                    'productDetailID' => $productDetailId,
                                    'productCode' => $productCode,
                                    'productName' => $productName,
                                    'uomName' => (string) ($detail['uomName'] ?? $component['uomName'] ?? ''),
                                    'sourceQty' => (float) ($component['qty'] ?? 0),
                                    'sourceUnit' => (string) ($component['uomName'] ?? ''),
                                    'bomDetails' => $detail['bomDetails'],
                                ];
                            }
                        }

                        return array_values($recipes);
                    },
                );
            }
        } catch (Throwable $exception) {
            $this->autoWipComponentError = $exception->getMessage();
        }
    }

    public function editBomComponents(int $projectBomId): void
    {
        $this->authorizeBomUpdate();
        if (! isset($this->bomComponentDetails[$projectBomId])) {
            $this->loadBomComponents($projectBomId);
        }
        $this->bomComponentDrafts[$projectBomId] = $this->bomComponentDetails[$projectBomId];
        $this->bomComponentEditing[$projectBomId] = true;
        $this->bomComponentExpanded[$projectBomId] = true;
    }

    public function cancelBomComponentEdit(int $projectBomId): void
    {
        unset($this->bomComponentDrafts[$projectBomId]);
        $this->bomComponentEditing[$projectBomId] = false;
        $this->resetValidation();
    }

    public function removeInlineBomComponent(int $projectBomId, int $index): void
    {
        $this->authorizeBomUpdate();
        if (count($this->bomComponentDrafts[$projectBomId]['bomDetails'] ?? []) <= 1) {
            $this->addError(
                "bomComponentDrafts.$projectBomId.bomDetails",
                'BOM wajib memiliki minimal satu komponen.',
            );

            return;
        }

        unset($this->bomComponentDrafts[$projectBomId]['bomDetails'][$index]);
        $this->bomComponentDrafts[$projectBomId]['bomDetails'] = array_values(
            $this->bomComponentDrafts[$projectBomId]['bomDetails'],
        );
    }

    public function openInlineProductPicker(int $projectBomId, string $target = 'component'): void
    {
        $this->authorizeBomUpdate();
        abort_unless(in_array($target, ['component', 'result'], true), 422);
        if (! ($this->bomComponentEditing[$projectBomId] ?? false)) {
            $this->editBomComponents($projectBomId);
        }

        $this->inlineProductBomId = $projectBomId;
        $this->inlineProductTarget = $target;
        $this->inlineProductNameSearch = '';
        $this->inlineProductCodeSearch = '';
        $this->inlineProductCategoryId = '';
        $this->inlineProductSubCategoryId = '';
        $this->inlineProductPage = 1;
        $this->inlineProductOptions = [];
        $this->inlineProductTotal = 0;
        $this->inlineProductHasNext = false;
        $this->inlineProductModalOpen = true;
    }

    public function loadInlineProducts(bool $reset = false): void
    {
        if ($reset) {
            $this->inlineProductPage = 1;
        }

        try {
            if ($this->inlineProductCategoryOptions === []) {
                $taxonomy = app(EsbCoreService::class)->getProductTaxonomy();
                $this->inlineProductCategoryOptions = $taxonomy['categories'];
                $this->inlineProductSubCategoryOptions = $taxonomy['subCategories'];
                $this->inlineProductUnitOptions = app(EsbService::class)->getAllActiveProductUnits();
            }

            if (filled($this->inlineProductNameSearch) || filled($this->inlineProductCodeSearch) || filled($this->inlineProductCategoryId) || filled($this->inlineProductSubCategoryId)) {
                $list = app(EsbCoreService::class)->getProducts([
                    'page' => $this->inlineProductPage,
                    'limit' => 20,
                    'productName' => trim($this->inlineProductNameSearch),
                    'productCode' => trim($this->inlineProductCodeSearch),
                    'categoryID' => $this->inlineProductCategoryId,
                    'subCategoryID' => $this->inlineProductSubCategoryId,
                ]);
                $this->inlineProductOptions = app(EsbService::class)->getActiveProductDetailsByCodes(
                    array_column($list['data'], 'productCode'),
                );
                $this->inlineProductPage = $list['page'];
                $this->inlineProductTotal = $list['count'];
                $this->inlineProductPerPage = $list['limit'];
                $this->inlineProductHasNext = filled($list['next'])
                    || ($this->inlineProductPage * $this->inlineProductPerPage < $this->inlineProductTotal);
            } else {
                $result = app(EsbService::class)->getActiveProductDetailsPage('', $this->inlineProductPage);
                $this->inlineProductOptions = $result['data'];
                $this->inlineProductPage = $result['page'];
                $this->inlineProductTotal = $result['total'];
                $this->inlineProductPerPage = $result['perPage'];
                $this->inlineProductHasNext = $result['hasNext'];
            }
        } catch (Throwable $exception) {
            $this->inlineProductOptions = [];
            Notification::make()
                ->title('Master Product belum dapat dimuat')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function updatedInlineProductNameSearch(): void
    {
        $this->loadInlineProducts(true);
    }

    public function updatedInlineProductCodeSearch(): void
    {
        $this->loadInlineProducts(true);
    }

    public function updatedInlineProductCategoryId(): void
    {
        $this->loadInlineProducts(true);
    }

    public function updatedInlineProductSubCategoryId(): void
    {
        $this->loadInlineProducts(true);
    }

    public function previousInlineProductPage(): void
    {
        if ($this->inlineProductPage > 1) {
            $this->inlineProductPage--;
            $this->loadInlineProducts();
        }
    }

    public function nextInlineProductPage(): void
    {
        if ($this->inlineProductHasNext) {
            $this->inlineProductPage++;
            $this->loadInlineProducts();
        }
    }

    public function goToInlineProductPage(int $page): void
    {
        $lastPage = max(1, (int) ceil($this->inlineProductTotal / max(1, $this->inlineProductPerPage)));
        $this->inlineProductPage = min($lastPage, max(1, $page));
        $this->loadInlineProducts();
    }

    public function selectInlineProduct(int $productDetailId): void
    {
        $this->authorizeBomUpdate();
        abort_unless($this->inlineProductBomId !== null, 422);
        $product = $this->inlineProductOptions[$productDetailId] ?? null;
        abort_unless(is_array($product), 422);
        $bomId = $this->inlineProductBomId;

        if ($this->inlineProductTarget === 'result') {
            $this->bomComponentDrafts[$bomId]['productDetailID'] = $productDetailId;
            $this->bomComponentDrafts[$bomId]['productName'] = $product['productName'];
            $this->bomComponentDrafts[$bomId]['productCode'] = $product['productCode'];
            $this->bomComponentDrafts[$bomId]['uomName'] = $product['baseUnit'] ?: $product['unit'];
        } else {
            $usedIds = collect($this->bomComponentDrafts[$bomId]['bomDetails'] ?? [])
                ->pluck('productDetailID')
                ->map(fn ($id): int => (int) $id);
            if ($usedIds->contains($productDetailId)) {
                Notification::make()->title('Product sudah menjadi komponen BOM')->warning()->send();

                return;
            }

            $this->bomComponentDrafts[$bomId]['bomDetails'][] = [
                'ID' => 0,
                'productDetailID' => $productDetailId,
                'productCode' => $product['productCode'],
                'productName' => $product['productName'],
                'uomName' => $product['baseUnit'] ?: $product['unit'],
                'lastHPP' => (float) $product['basePrice'],
                'qty' => 1,
                'yieldPercent' => 0,
                'tolerancePercent' => (float) $product['receiptTolerance'],
                'printGroup' => '',
                'subtitution' => [],
            ];
        }

        $this->inlineProductModalOpen = false;
        $this->dispatch('close-inline-product-picker');
    }

    public function updateInlineBom(int $projectBomId): void
    {
        $this->authorizeBomUpdate();
        $projectBom = $this->attachedProjectBom($projectBomId);
        $validated = $this->validate([
            "bomComponentDrafts.$projectBomId.productDetailID" => ['required', 'integer', 'min:1'],
            "bomComponentDrafts.$projectBomId.productName" => ['nullable', 'string', 'max:255'],
            "bomComponentDrafts.$projectBomId.productCode" => ['nullable', 'string', 'max:100'],
            "bomComponentDrafts.$projectBomId.uomName" => ['nullable', 'string', 'max:100'],
            "bomComponentDrafts.$projectBomId.bomDetails" => ['required', 'array', 'min:1'],
            "bomComponentDrafts.$projectBomId.bomDetails.*.ID" => ['required', 'integer', 'min:0'],
            "bomComponentDrafts.$projectBomId.bomDetails.*.productDetailID" => ['required', 'integer', 'min:1'],
            "bomComponentDrafts.$projectBomId.bomDetails.*.lastHPP" => ['required', 'numeric', 'min:0'],
            "bomComponentDrafts.$projectBomId.bomDetails.*.qty" => ['required', 'numeric', 'gt:0'],
            "bomComponentDrafts.$projectBomId.bomDetails.*.yieldPercent" => ['required', 'numeric', 'between:0,100'],
            "bomComponentDrafts.$projectBomId.bomDetails.*.tolerancePercent" => ['required', 'numeric', 'between:0,100'],
            "bomComponentDrafts.$projectBomId.bomDetails.*.printGroup" => ['nullable', 'string', 'max:100'],
        ]);
        $draft = data_get($validated, "bomComponentDrafts.$projectBomId");

        if (collect($draft['bomDetails'])->pluck('productDetailID')->map(fn ($id): int => (int) $id)
            ->contains((int) $draft['productDetailID'])) {
            $this->addError(
                "bomComponentDrafts.$projectBomId.productDetailID",
                'Product hasil tidak boleh digunakan sebagai komponen pada BOM yang sama.',
            );

            return;
        }

        try {
            $latest = app(EsbCoreService::class)->getBillOfMaterial($projectBom->esb_bom_id);
            $loadedEditedDate = data_get($this->bomComponentDetails, "$projectBomId.editedDate");
            $latestEditedDate = $latest['editedDate'] ?? null;

            if ($loadedEditedDate && $latestEditedDate && $loadedEditedDate !== $latestEditedDate) {
                $this->addError(
                    "bomComponentDrafts.$projectBomId",
                    'BOM telah diperbarui user lain di ESB. Muat ulang komponen sebelum menyimpan.',
                );

                return;
            }

            $payload = $this->inlineBomPayload($latest, $draft);
            $projectBom->update(['sync_status' => 'syncing']);
            app(EsbCoreService::class)->updateBillOfMaterial($projectBom->esb_bom_id, $payload);

            $snapshot = $latest;
            $snapshot['productDetailID'] = (int) $draft['productDetailID'];
            $snapshot['productName'] = (string) ($draft['productName'] ?? $latest['productName'] ?? '');
            $snapshot['productCode'] = (string) ($draft['productCode'] ?? $latest['productCode'] ?? '');
            $snapshot['uomName'] = (string) ($draft['uomName'] ?? $latest['uomName'] ?? '');
            $originalById = collect($latest['bomDetails'] ?? [])->keyBy(
                fn (array $item): int => (int) ($item['ID'] ?? 0),
            );
            $snapshot['bomDetails'] = array_map(
                fn (array $updated): array => array_merge(
                    $originalById->get((int) $updated['ID'], []),
                    $updated,
                ),
                $draft['bomDetails'],
            );
            $snapshot['editedDate'] = null;
            $projectBom->update([
                'detail_snapshot' => $snapshot,
                'product_name' => $snapshot['productName'] ?: $projectBom->product_name,
                'uom_name' => $snapshot['uomName'] ?: $projectBom->uom_name,
                'sync_status' => 'synced',
                'last_synced_at' => now(),
            ]);
            $this->setBomComponentState($projectBomId, $snapshot);
            $this->bomComponentEditing[$projectBomId] = false;
            $this->reloadProduct();
            Notification::make()->title('Komponen BOM berhasil diperbarui')->success()->send();
        } catch (Throwable $exception) {
            $projectBom->update(['sync_status' => 'failed']);
            Notification::make()
                ->title('Komponen BOM gagal diperbarui')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    private function setBomComponentState(int $projectBomId, array $detail): void
    {
        $detail['bomDetails'] = $this->normalizedBomRows($detail['bomDetails'] ?? []);
        $this->bomComponentDetails[$projectBomId] = $detail;
        $this->bomComponentDrafts[$projectBomId] = $detail;
    }

    private function normalizedBomRows(array $rows): array
    {
        return array_values(array_map(
            fn (array $item): array => [
                'ID' => (int) ($item['ID'] ?? 0),
                'productID' => (int) ($item['productID'] ?? 0),
                'productDetailID' => (int) ($item['productDetailID'] ?? 0),
                'productCode' => (string) ($item['productCode'] ?? ''),
                'productName' => (string) ($item['productName'] ?? ''),
                'categoryName' => (string) ($item['categoryName'] ?? ''),
                'uomName' => (string) ($item['uomName'] ?? ''),
                'lastHPP' => (float) ($item['lastHPP'] ?? $item['lastHpp'] ?? $item['price'] ?? 0),
                'qty' => (float) ($item['qty'] ?? 0),
                'yieldPercent' => (float) ($item['yieldPercent'] ?? 0),
                'tolerancePercent' => (float) ($item['tolerancePercent'] ?? 0),
                'printGroup' => (string) ($item['printGroup'] ?? ''),
                'subtitution' => is_array($item['subtitution'] ?? null) ? $item['subtitution'] : [],
            ],
            $rows,
        ));
    }

    private function inlineBomPayload(array $latest, array $draft): array
    {
        return [
            'bomTypeID' => (int) ($latest['bomTypeID'] ?? 1),
            'bomName' => (string) ($latest['bomName'] ?? ''),
            'bomCode' => (string) ($latest['bomCode'] ?? ''),
            'productDetailID' => (int) $draft['productDetailID'],
            'notes' => (string) ($latest['notes'] ?? ''),
            'bomCostTotal' => (float) ($latest['bomCostTotal'] ?? 0),
            'accessType' => (int) ($latest['accessType'] ?? 0),
            'selectedUserAccess' => is_array($latest['selectedUserAccess'] ?? null) ? $latest['selectedUserAccess'] : [],
            'bomDetails' => array_map(fn (array $item): array => [
                'ID' => (int) $item['ID'],
                'productDetailID' => (int) $item['productDetailID'],
                'lastHPP' => (float) $item['lastHPP'],
                'qty' => (float) $item['qty'],
                'yieldPercent' => (float) $item['yieldPercent'],
                'printGroup' => (string) ($item['printGroup'] ?? ''),
                'tolerancePercent' => (float) $item['tolerancePercent'],
                'subtitution' => is_array($item['subtitution'] ?? null) ? $item['subtitution'] : [],
            ], $draft['bomDetails']),
            'bomCosts' => is_array($latest['bomCosts'] ?? null) ? $latest['bomCosts'] : [],
        ];
    }

    private function attachedProjectBom(int $projectBomId): RndProjectBom
    {
        return $this->productRecord->boms()
            ->where('rnd_project_boms.id', $projectBomId)
            ->firstOrFail();
    }

    public function updateBomUsage(int $bomId, string $usageType): void
    {
        $this->authorizeBomManagement();
        abort_unless(array_key_exists($usageType, RndProjectProduct::BOM_USAGE_TYPES), 422);
        $projectBom = $this->projectRecord->boms()->where('esb_bom_id', $bomId)->firstOrFail();
        $this->productRecord->boms()->updateExistingPivot($projectBom->id, [
            'usage_type' => $usageType,
            'parent_rnd_project_bom_id' => $usageType === 'main' ? null : $this->importParentBomId,
        ]);
        $this->reloadProduct();
    }

    public function assignBomToMain(int $bomId, int $parentBomId): void
    {
        $this->authorizeBomManagement();
        abort_unless($this->isAttachedMainBom($parentBomId), 422);
        $projectBom = $this->productRecord->boms()->where('rnd_project_boms.esb_bom_id', $bomId)->firstOrFail();
        abort_if($projectBom->pivot->usage_type === 'main', 422);
        $this->productRecord->boms()->updateExistingPivot($projectBom->id, [
            'parent_rnd_project_bom_id' => $parentBomId,
        ]);
        $this->reloadProduct();
    }

    private function isAttachedMainBom(int $bomId): bool
    {
        return $this->productRecord->boms()
            ->where('rnd_project_boms.id', $bomId)
            ->wherePivot('usage_type', 'main')
            ->exists();
    }

    public function openMaterialForm(): void
    {
        $this->authorizeProjectManagement();
        $this->materialType = 'packaging_design';
        $this->materialTitle = '';
        $this->materialNotes = '';
        $this->materialFile = null;
        $this->resetValidation();
        $this->materialModalOpen = true;
        $this->dispatch('open-material-form');
    }

    public function saveMaterial(): void
    {
        $this->authorizeProjectManagement();
        $validated = $this->validate([
            'materialType' => ['required', Rule::in(array_keys(RndProjectMarketingMaterial::TYPES))],
            'materialTitle' => ['required', 'string', 'max:255'],
            'materialNotes' => ['nullable', 'string', 'max:2000'],
            'materialFile' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,svg,pdf,zip', 'max:15360'],
        ]);
        $path = $this->materialFile->store(
            'rnd/marketing-materials/'.$this->projectId.'/'.$this->productId,
            'b2',
        );

        if (! is_string($path) || $path === '') {
            $this->addError('materialFile', 'File gagal diunggah ke Cloudflare R2.');

            return;
        }

        try {
            $this->productRecord->marketingMaterials()->create([
                'type' => $validated['materialType'],
                'title' => trim($validated['materialTitle']),
                'file_path' => $path,
                'original_name' => $this->materialFile->getClientOriginalName(),
                'mime_type' => $this->materialFile->getMimeType(),
                'file_size' => $this->materialFile->getSize(),
                'notes' => trim($validated['materialNotes']) ?: null,
                'created_by' => auth()->id(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('b2')->delete($path);
            throw $exception;
        }

        $this->reloadProduct();
        $this->materialFile = null;
        $this->materialModalOpen = false;
        $this->dispatch('close-material-form');
        Notification::make()->title('Marketing material berhasil diunggah')->success()->send();
    }

    public function deleteMaterial(int $materialId): void
    {
        $this->authorizeProjectManagement();
        $material = $this->productRecord->marketingMaterials()->findOrFail($materialId);
        $path = $material->file_path;
        $material->delete();
        Storage::disk('b2')->delete($path);
        $this->reloadProduct();
        Notification::make()->title('Marketing material berhasil dihapus')->success()->send();
    }

    public function openEsbMaterialForm(?int $materialId = null): void
    {
        $this->authorizeProjectManagement();
        $this->resetValidation();
        $this->loadEsbTaxonomy();
        $this->materialDraftId = $materialId;

        if ($materialId) {
            $material = $this->productRecord->esbMaterials()->with('units')->findOrFail($materialId);
            abort_if($material->status === 'synced', 422, 'Data yang sudah tersinkron tidak dapat diubah.');
            $this->esbMaterialProductName = $material->product_name;
            $this->hydrateEsbMaterialNaming($material->product_name);
            $this->esbMaterialProductCode = $material->product_code;
            $this->esbMaterialCategoryId = $material->category_id;
            $this->esbMaterialSubCategoryId = $material->sub_category_id;
            $this->esbMaterialUomId = $material->uom_id;
            $this->esbMaterialUomName = $material->uom_name;
            $this->esbMaterialSku = $material->sku;
            $this->esbMaterialConversionFactor = (string) $material->conversion_factor;
            $this->esbMaterialBasePrice = (string) $material->base_price;
            $this->esbMaterialNotes = (string) $material->notes;
            $this->esbMaterialUnits = $material->units->isNotEmpty()
                ? $material->units->map(fn ($unit): array => [
                    'uom_id' => $unit->uom_id,
                    'uom_name' => $unit->uom_name,
                    'sku' => $unit->sku,
                    'conversion_factor' => (string) $unit->conversion_factor,
                    'base_price' => (string) $unit->base_price,
                    'is_base' => $unit->is_base,
                ])->values()->all()
                : [$this->newEsbMaterialUnit(true, $material->uom_id, $material->uom_name, $material->base_price)];
        } else {
            $this->reset([
                'esbMaterialProductName',
                'esbMaterialProductBaseName',
                'esbMaterialNamePrefix',
                'esbMaterialProductCode',
                'esbMaterialCategoryId',
                'esbMaterialSubCategoryId',
                'esbMaterialUomId',
                'esbMaterialUomName',
                'esbMaterialSku',
                'esbMaterialNotes',
            ]);
            $this->esbMaterialConversionFactor = '1';
            $this->esbMaterialBasePrice = '0';
            $this->esbMaterialUnits = [$this->newEsbMaterialUnit(true)];
        }

        $this->esbMaterialModalOpen = true;
        $this->dispatch('open-esb-material-form');
    }

    public function saveEsbMaterial(): void
    {
        $this->authorizeProjectManagement();
        $this->syncEsbMaterialProductName();
        $this->syncEsbMaterialUnitSkus();
        $material = $this->materialDraftId
            ? $this->productRecord->esbMaterials()->findOrFail($this->materialDraftId)
            : null;
        abort_if($material?->status === 'synced', 422);

        $validated = $this->validate([
            'esbMaterialProductName' => ['required', 'string', 'max:100'],
            'esbMaterialProductBaseName' => [
                Rule::requiredIf(fn (): bool => $this->isEsbMaterialWipCategory()),
                'nullable', 'string', 'max:90',
            ],
            'esbMaterialNamePrefix' => [
                Rule::requiredIf(fn (): bool => $this->isEsbMaterialWipCategory()),
                'nullable', Rule::in(array_keys($this->esbMaterialNamePrefixOptions())),
            ],
            'esbMaterialProductCode' => [
                'required', 'string', 'max:50',
                Rule::unique('rnd_product_esb_materials', 'product_code')->ignore($material?->id),
            ],
            'esbMaterialCategoryId' => ['required', 'integer', 'min:1'],
            'esbMaterialSubCategoryId' => ['required', 'integer', 'min:1'],
            'esbMaterialSku' => [
                'required', 'string', 'max:50',
                Rule::unique('rnd_product_esb_materials', 'sku')->ignore($material?->id),
            ],
            'esbMaterialNotes' => ['nullable', 'string', 'max:100'],
            'esbMaterialUnits' => ['required', 'array', 'min:1'],
            'esbMaterialUnits.*.uom_id' => [
                'required', 'integer', 'distinct', Rule::in(array_keys($this->esbUomOptions())),
            ],
            'esbMaterialUnits.*.uom_name' => ['required', 'string', 'max:50'],
            'esbMaterialUnits.*.sku' => ['required', 'string', 'max:50', 'distinct'],
            'esbMaterialUnits.*.conversion_factor' => ['required', 'numeric', 'gt:0'],
            'esbMaterialUnits.*.base_price' => ['required', 'numeric', 'min:0'],
            'esbMaterialUnits.*.is_base' => ['required', 'boolean'],
        ], [
            'esbMaterialUnits.*.uom_id.distinct' => 'Unit tidak boleh dipilih lebih dari satu kali.',
            'esbMaterialUnits.*.sku.distinct' => 'SKU unit tidak boleh sama.',
        ]);

        $baseUnits = collect($validated['esbMaterialUnits'])->where('is_base', true);
        if ($baseUnits->count() !== 1 || (float) $baseUnits->first()['conversion_factor'] !== 1.0) {
            $this->addError('esbMaterialUnits.0.conversion_factor', 'Base Unit wajib tepat satu dengan Conversion Factor 1.');

            return;
        }

        $unitSkus = collect($validated['esbMaterialUnits'])->pluck('sku');
        $duplicateSku = RndProductEsbMaterialUnit::query()
            ->whereIn('sku', $unitSkus)
            ->when($material, fn ($query) => $query->where('rnd_product_esb_material_id', '!=', $material->id))
            ->exists();
        if ($duplicateSku) {
            $this->addError('esbMaterialUnits', 'Salah satu SKU unit sudah digunakan pada bahan lain.');

            return;
        }

        $baseUnit = $baseUnits->first();
        $values = [
            'category_id' => $validated['esbMaterialCategoryId'],
            'category_name' => $this->esbCategoryOptions[$validated['esbMaterialCategoryId']] ?? null,
            'sub_category_id' => $validated['esbMaterialSubCategoryId'],
            'sub_category_name' => $this->esbSubCategoryOptions[$validated['esbMaterialSubCategoryId']] ?? null,
            'uom_id' => $baseUnit['uom_id'],
            'uom_name' => $baseUnit['uom_name'],
            'product_code' => trim($validated['esbMaterialProductCode']),
            'product_name' => trim($validated['esbMaterialProductName']),
            'sku' => $baseUnit['sku'],
            'conversion_factor' => 1,
            'base_price' => $baseUnit['base_price'],
            'notes' => trim($validated['esbMaterialNotes']) ?: null,
            'status' => 'draft',
            'sync_error' => null,
        ];

        if ($material) {
            $material->update($values);
        } else {
            $material = $this->productRecord->esbMaterials()->create($values + ['created_by' => auth()->id()]);
        }
        $material->units()->delete();
        $material->units()->createMany(collect($validated['esbMaterialUnits'])
            ->map(fn (array $unit): array => [
                'uom_id' => $unit['uom_id'],
                'uom_name' => $unit['uom_name'],
                'sku' => $unit['sku'],
                'conversion_factor' => $unit['is_base'] ? 1 : $unit['conversion_factor'],
                'base_price' => $unit['base_price'],
                'is_base' => $unit['is_base'],
                'is_stock' => $unit['is_base'],
                'is_purchase' => $unit['is_base'],
                'is_transfer' => $unit['is_base'],
                'is_sales' => false,
                'flag_active' => true,
            ])->all());

        $this->reloadProduct();
        $this->esbMaterialModalOpen = false;
        $this->dispatch('close-esb-material-form');
        Notification::make()->title('Draft bahan berhasil disimpan')->success()->send();
    }

    public function updatedEsbMaterialUomId(): void
    {
        $this->esbMaterialUomName = $this->esbUomOptions()[$this->esbMaterialUomId] ?? '';
        $this->syncEsbMaterialSku();
    }

    public function updatedEsbMaterialUnits(): void
    {
        $this->syncEsbMaterialUnitSkus();
    }

    public function addEsbMaterialUnit(): void
    {
        $this->esbMaterialUnits[] = $this->newEsbMaterialUnit(false);
    }

    public function removeEsbMaterialUnit(int $index): void
    {
        if ($index === 0 || ! isset($this->esbMaterialUnits[$index])) {
            return;
        }

        unset($this->esbMaterialUnits[$index]);
        $this->esbMaterialUnits = array_values($this->esbMaterialUnits);
        $this->syncEsbMaterialUnitSkus();
    }

    public function updatedEsbMaterialProductCode(): void
    {
        $this->syncEsbMaterialUnitSkus();
    }

    public function esbUomOptions(): array
    {
        $options = config('esb.core.uoms', []);

        if ($this->esbMaterialUomId && $this->esbMaterialUomName !== '') {
            $options[$this->esbMaterialUomId] ??= $this->esbMaterialUomName;
        }

        return $options;
    }

    public function updatedEsbMaterialCategoryId(): void
    {
        if ($this->isEsbMaterialWipCategory()) {
            $this->esbMaterialProductBaseName = $this->stripEsbMaterialNamePrefix($this->esbMaterialProductName);
        } else {
            if ($this->esbMaterialProductBaseName !== '') {
                $this->esbMaterialProductName = $this->esbMaterialProductBaseName;
            }
            $this->esbMaterialNamePrefix = '';
        }
        $this->syncEsbMaterialProductName();

        if ($this->esbMaterialCategoryId) {
            $this->generateEsbMaterialProductCode();
        }
    }

    public function updatedEsbMaterialProductBaseName(): void
    {
        $this->syncEsbMaterialProductName();
    }

    public function updatedEsbMaterialNamePrefix(): void
    {
        $this->syncEsbMaterialProductName();
    }

    public function isEsbMaterialWipCategory(): bool
    {
        $name = $this->esbCategoryOptions[$this->esbMaterialCategoryId] ?? '';

        return mb_strtolower(trim((string) $name)) === 'barang wip';
    }

    public function esbMaterialNamePrefixOptions(): array
    {
        return [
            'WIP |' => 'Kitchen - WIP |',
            'JYM |' => 'Joy-Mart - JYM |',
            'ATL |' => 'Atelier - ATL |',
            'EGG |' => 'Eggish - EGG |',
            'Central |' => 'Patisserie - Central |',
        ];
    }

    private function syncEsbMaterialProductName(): void
    {
        if (! $this->isEsbMaterialWipCategory()) {
            return;
        }

        // Preserve trailing spaces while the user is typing. Normalization is
        // performed when the draft is saved, otherwise a space between words
        // is immediately removed by Livewire's real-time update.
        $this->esbMaterialProductName = $this->esbMaterialNamePrefix === ''
            ? $this->esbMaterialProductBaseName
            : $this->esbMaterialNamePrefix.' '.$this->esbMaterialProductBaseName;
    }

    private function hydrateEsbMaterialNaming(string $productName): void
    {
        $this->esbMaterialNamePrefix = '';
        foreach (array_keys($this->esbMaterialNamePrefixOptions()) as $prefix) {
            if (str_starts_with(mb_strtolower(trim($productName)), mb_strtolower($prefix))) {
                $this->esbMaterialNamePrefix = $prefix;
                break;
            }
        }

        $this->esbMaterialProductBaseName = $this->stripEsbMaterialNamePrefix($productName);
        $this->syncEsbMaterialProductName();
    }

    private function stripEsbMaterialNamePrefix(string $name): string
    {
        $name = trim($name);
        foreach (array_keys($this->esbMaterialNamePrefixOptions()) as $prefix) {
            if (str_starts_with(mb_strtolower($name), mb_strtolower($prefix))) {
                return trim(mb_substr($name, mb_strlen($prefix)));
            }
        }

        return $name;
    }

    public function generateEsbMaterialProductCode(): void
    {
        $this->authorizeProjectManagement();

        if (! $this->esbMaterialCategoryId) {
            $this->addError('esbMaterialProductCode', 'Pilih Category terlebih dahulu.');

            return;
        }

        try {
            $remoteSuggestion = app(EsbCoreService::class)
                ->suggestNextProductCode($this->esbMaterialCategoryId);
            $localCodes = RndProductEsbMaterial::query()
                ->where('category_id', $this->esbMaterialCategoryId)
                ->when($this->materialDraftId, fn ($query) => $query->where('id', '!=', $this->materialDraftId))
                ->pluck('product_code')
                ->all();

            $this->esbMaterialProductCode = $this->nextAvailableProductCode(
                $remoteSuggestion,
                $localCodes,
            ) ?? '';

            if ($this->esbMaterialProductCode === '') {
                $this->addError(
                    'esbMaterialProductCode',
                    'Kode otomatis belum dapat dibuat karena belum ada pola kode berakhiran angka pada kategori ini.',
                );
            } else {
                $this->syncEsbMaterialUnitSkus();
                $this->resetValidation('esbMaterialProductCode');
            }
        } catch (Throwable $exception) {
            $this->addError('esbMaterialProductCode', $exception->getMessage());
        }
    }

    private function nextAvailableProductCode(?string $remoteSuggestion, array $localCodes): ?string
    {
        if (! $remoteSuggestion || ! preg_match('/^(.*?)(\d+)$/', $remoteSuggestion, $matches)) {
            return $remoteSuggestion;
        }

        $prefix = $matches[1];
        $padding = strlen($matches[2]);
        $number = (int) $matches[2];

        foreach ($localCodes as $localCode) {
            if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', (string) $localCode, $localMatch)) {
                $number = max($number, ((int) $localMatch[1]) + 1);
                $padding = max($padding, strlen($localMatch[1]));
            }
        }

        return $prefix.str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
    }

    private function syncEsbMaterialSku(): void
    {
        $code = strtoupper(trim($this->esbMaterialProductCode));
        $unit = strtoupper(trim($this->esbUomOptions()[$this->esbMaterialUomId] ?? ''));
        $this->esbMaterialSku = $code !== '' && $unit !== ''
            ? $code.'-'.$unit
            : '';
    }

    private function syncEsbMaterialUnitSkus(): void
    {
        $code = strtoupper(trim($this->esbMaterialProductCode));

        foreach ($this->esbMaterialUnits as $index => $unit) {
            $uomId = isset($unit['uom_id']) && $unit['uom_id'] !== ''
                ? (int) $unit['uom_id']
                : null;
            $unitName = strtoupper(trim($this->esbUomOptions()[$uomId] ?? ''));
            $isBase = $index === 0;
            $this->esbMaterialUnits[$index]['uom_name'] = $unitName;
            $this->esbMaterialUnits[$index]['sku'] = $code !== '' && $unitName !== ''
                ? $code.'-'.$unitName
                : '';
            $this->esbMaterialUnits[$index]['is_base'] = $isBase;
            if ($isBase) {
                $this->esbMaterialUnits[$index]['conversion_factor'] = '1';
            }
        }

        $base = $this->esbMaterialUnits[0] ?? null;
        $this->esbMaterialUomId = isset($base['uom_id']) && $base['uom_id'] !== '' ? (int) $base['uom_id'] : null;
        $this->esbMaterialUomName = (string) ($base['uom_name'] ?? '');
        $this->esbMaterialSku = (string) ($base['sku'] ?? '');
        $this->esbMaterialConversionFactor = '1';
        $this->esbMaterialBasePrice = (string) ($base['base_price'] ?? '0');
    }

    private function newEsbMaterialUnit(
        bool $isBase,
        ?int $uomId = null,
        ?string $uomName = null,
        mixed $basePrice = 0,
    ): array {
        return [
            'uom_id' => $uomId,
            'uom_name' => $uomName ?? '',
            'sku' => '',
            'conversion_factor' => $isBase ? '1' : '',
            'base_price' => (string) $basePrice,
            'is_base' => $isBase,
        ];
    }

    public function syncEsbMaterial(int $materialId): void
    {
        $this->authorizeProjectManagement();
        $material = $this->productRecord->esbMaterials()->findOrFail($materialId);
        if ($material->status === 'synced') {
            Notification::make()->title('Bahan sudah tersinkron ke ESB')->warning()->send();

            return;
        }

        $payload = $material->toEsbPayload();
        $material->update([
            'status' => 'syncing',
            'last_payload' => $payload,
            'sync_error' => null,
        ]);

        try {
            $result = app(EsbCoreService::class)->createProduct($payload);
            $material->update([
                'status' => 'synced',
                'esb_product_id' => $result['productID'],
                'esb_is_temp' => $result['isTemp'],
                'last_response' => $result,
                'sync_error' => null,
                'synced_at' => now(),
            ]);
            Notification::make()->title('Bahan berhasil dibuat di ESB')->success()->send();
        } catch (Throwable $exception) {
            $material->update([
                'status' => 'failed',
                'sync_error' => $exception->getMessage(),
                'last_response' => ['message' => $exception->getMessage()],
            ]);
            Notification::make()->title('Sinkronisasi bahan gagal')->body($exception->getMessage())->danger()->send();
        }

        $this->reloadProduct();
    }

    public function deleteEsbMaterial(int $materialId): void
    {
        $this->authorizeProjectManagement();
        $material = $this->productRecord->esbMaterials()->findOrFail($materialId);
        abort_if($material->status === 'synced', 422, 'Data yang sudah tersinkron tidak dapat dihapus.');
        $material->delete();
        $this->reloadProduct();
        Notification::make()->title('Draft bahan berhasil dihapus')->success()->send();
    }

    private function loadEsbTaxonomy(): void
    {
        if ($this->esbCategoryOptions !== [] && $this->esbSubCategoryOptions !== []) {
            return;
        }

        try {
            $taxonomy = app(EsbCoreService::class)->getProductTaxonomy();
            $this->esbCategoryOptions = $taxonomy['categories'];
            $this->esbSubCategoryOptions = $taxonomy['subCategories'];
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Master kategori belum dapat dimuat')
                ->body($exception->getMessage())
                ->warning()
                ->send();
        }
    }

    public function openExportPdf(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->exportPin = '';
        $this->resetValidation();
        $this->exportPinModalOpen = true;
        $this->dispatch('open-export-pin');
    }

    public function exportBomPdf(): mixed
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['exportPin' => ['required', 'string', 'max:20']]);
        $rateKey = 'rnd-bom-export-pin:'.auth()->id().':'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $this->addError('exportPin', 'Terlalu banyak percobaan. Coba kembali dalam '.RateLimiter::availableIn($rateKey).' detik.');

            return null;
        }

        $configuredPin = (string) config('rnd.bom_pin');
        if ($configuredPin === '' || ! hash_equals($configuredPin, $this->exportPin)) {
            RateLimiter::hit($rateKey, 60);
            $this->reset('exportPin');
            $this->addError('exportPin', $configuredPin === '' ? 'PIN resep belum dikonfigurasi.' : 'PIN yang dimasukkan tidak sesuai.');

            return null;
        }

        RateLimiter::clear($rateKey);
        session()->put(
            RndProductBomPdfController::sessionKey(auth()->id(), $this->projectId, $this->productId),
            now()->addMinutes(config('rnd.bom_pin_ttl_minutes', 15))->timestamp,
        );

        return $this->redirect(route('helpdesk.rnd-products.bom-pdf', [
            'project' => $this->projectId,
            'product' => $this->productId,
        ]), navigate: false);
    }

    private function authorizeProjectManagement(): void
    {
        $user = auth()->user();
        abort_unless($user?->hasRole('SUPERADMIN') || $user?->can('edit rnd projects'), 403);
    }

    private function authorizeBomManagement(): void
    {
        $user = auth()->user();
        abort_unless(
            $user?->hasRole('SUPERADMIN')
            || ($user?->can('edit rnd projects') && $user?->can('create bill of materials')),
            403,
        );
    }

    private function authorizeBomUpdate(): void
    {
        $user = auth()->user();
        abort_unless(
            $user?->hasRole('SUPERADMIN')
            || ($user?->can('edit rnd projects') && $user?->can('edit bill of materials')),
            403,
        );
    }

    private function reloadProduct(): void
    {
        $this->productRecord->refresh()->load([
            'boms',
            'marketingMaterials',
            'esbMaterials',
            'regionalPrices.region',
            'currentRegionalPrices.region',
        ]);

        foreach ($this->productRecord->boms as $bom) {
            if ($bom->detail_snapshot && ! isset($this->bomComponentDetails[$bom->id])) {
                $this->setBomComponentState($bom->id, $bom->detail_snapshot);
            }
        }

        $this->loadBomInstructions();
    }
}
