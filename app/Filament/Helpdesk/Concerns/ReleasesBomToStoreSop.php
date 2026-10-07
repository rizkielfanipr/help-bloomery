<?php

namespace App\Filament\Helpdesk\Concerns;

use App\Actions\Rnd\Bom\ReleaseBomToStoreSopAction;
use App\Filament\Helpdesk\Resources\StoreSops\StoreSopResource;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\RndProject;
use App\Models\RndProjectProduct;
use App\Models\StoreSopCategory;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;

trait ReleasesBomToStoreSop
{
    public bool $releaseSopModalOpen = false;

    public string $releaseSopCode = '';

    public string $releaseSopTitle = '';

    public ?int $releaseSopCategoryId = null;

    public ?int $releaseSopBrandId = null;

    /** @var list<int> */
    public array $releaseSopBranchIds = [];

    public string $releaseSopEffectiveDate = '';

    public string $releaseSopExpiresAt = '';

    public string $releaseSopSummary = '';

    /** @var array<int, string> */
    public array $releaseSopCategoryOptions = [];

    /** @var array<int, string> */
    public array $releaseSopBrandOptions = [];

    /** @var array<int, string> */
    public array $releaseSopBranchOptions = [];

    public ?int $releasedStoreSopId = null;

    public function canReleaseBomToStoreSop(): bool
    {
        $exportPermission = match ($this->releaseSopScope()) {
            'kitchen' => 'export kitchen bill of materials',
            'store' => 'export store bill of materials',
            default => null,
        };
        $user = auth()->user();

        return $exportPermission !== null
            && $user !== null
            && $user->can('view bill of materials')
            && $user->can($exportPermission)
            && $user->can('create store sops')
            && $user->can('publish store sops');
    }

    public function openReleaseSopModal(): void
    {
        abort_unless($this->canReleaseBomToStoreSop(), 403);

        if ($this->releaseSopBomIds() === []) {
            $this->addError('releaseSop', 'Pilih minimal satu BOM sebelum merilis SOP.');

            return;
        }

        $this->resetValidation();
        $this->releaseSopCategoryOptions = StoreSopCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
        $this->releaseSopBrandOptions = Brand::query()
            ->whereHas('branches', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
        $this->releaseSopBranchOptions = [];
        $this->releaseSopBrandId = null;
        $this->releaseSopBranchIds = [];
        $this->releaseSopCategoryId = array_key_first($this->releaseSopCategoryOptions);

        $project = $this->releaseSopProject();
        $product = $this->releaseSopProduct();
        $sourceCode = $product?->id ?? 'PROJECT';
        $this->releaseSopCode = str('SOP-RND-'.$project->id.'-'.$sourceCode.'-'.now()->format('YmdHis'))->limit(50, '')->toString();
        $this->releaseSopTitle = 'SOP Resep '.($product?->name ?? $project->name);
        $this->releaseSopEffectiveDate = today()->toDateString();
        $this->releaseSopExpiresAt = today()->addYear()->toDateString();
        $this->releaseSopSummary = 'Dirilis dari R&D Project: '.$project->name.($product ? ' — '.$product->name : '');
        $this->releaseSopModalOpen = true;
    }

    public function updatedReleaseSopBrandId(): void
    {
        $this->releaseSopBranchIds = [];
        $this->releaseSopBranchOptions = Branch::query()
            ->where('is_active', true)
            ->where('brand_id', $this->releaseSopBrandId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function closeReleaseSopModal(): void
    {
        $this->releaseSopModalOpen = false;
        $this->resetValidation();
    }

    public function releaseBomToStoreSop(ReleaseBomToStoreSopAction $action): void
    {
        abort_unless($this->canReleaseBomToStoreSop(), 403);

        $validated = $this->validate([
            'releaseSopCode' => ['required', 'string', 'max:50', Rule::unique('store_sops', 'code')],
            'releaseSopTitle' => ['required', 'string', 'max:255'],
            'releaseSopCategoryId' => ['required', 'integer'],
            'releaseSopBrandId' => ['required', 'integer'],
            'releaseSopBranchIds' => ['required', 'array', 'min:1'],
            'releaseSopBranchIds.*' => ['integer'],
            'releaseSopEffectiveDate' => ['required', 'date'],
            'releaseSopExpiresAt' => ['required', 'date', 'after_or_equal:releaseSopEffectiveDate'],
            'releaseSopSummary' => ['nullable', 'string', 'max:3000'],
        ], attributes: [
            'releaseSopCode' => 'nomor SOP',
            'releaseSopTitle' => 'judul SOP',
            'releaseSopCategoryId' => 'kategori SOP',
            'releaseSopBrandId' => 'brand',
            'releaseSopBranchIds' => 'target branch',
            'releaseSopEffectiveDate' => 'tanggal berlaku',
            'releaseSopExpiresAt' => 'tanggal berakhir',
            'releaseSopSummary' => 'ringkasan',
        ]);

        $result = $action->execute(
            user: auth()->user(),
            project: $this->releaseSopProject(),
            product: $this->releaseSopProduct(),
            scope: $this->releaseSopScope(),
            bomIds: $this->releaseSopBomIds(),
            autoBomKeys: $this->releaseSopAutoBomKeys(),
            componentKeys: $this->releaseSopComponentKeys(),
            metadata: [
                'code' => $validated['releaseSopCode'],
                'title' => $validated['releaseSopTitle'],
                'store_sop_category_id' => $validated['releaseSopCategoryId'],
                'brand_id' => $validated['releaseSopBrandId'],
                'branch_ids' => $validated['releaseSopBranchIds'],
                'effective_date' => $validated['releaseSopEffectiveDate'],
                'expires_at' => $validated['releaseSopExpiresAt'],
                'summary' => $validated['releaseSopSummary'] ?: null,
            ],
        );

        $this->releasedStoreSopId = $result['sop']->id;
        $this->releaseSopModalOpen = false;

        Notification::make()
            ->title($result['created'] ? 'SOP berhasil dirilis' : 'SOP ini sudah pernah dirilis')
            ->body($result['created'] ? $result['branch_count'].' branch menerima SOP.' : 'SOP yang sama tidak dibuat ulang.')
            ->success()
            ->send();
    }

    public function releasedStoreSopUrl(): ?string
    {
        if ($this->releasedStoreSopId === null) {
            return null;
        }

        return StoreSopResource::getUrl('view', ['record' => $this->releasedStoreSopId]);
    }

    abstract protected function releaseSopProject(): RndProject;

    abstract protected function releaseSopProduct(): ?RndProjectProduct;

    abstract protected function releaseSopScope(): string;

    /** @return list<int> */
    abstract protected function releaseSopBomIds(): array;

    /** @return list<string> */
    abstract protected function releaseSopAutoBomKeys(): array;

    /** @return array<int, list<string>> */
    abstract protected function releaseSopComponentKeys(): array;
}
