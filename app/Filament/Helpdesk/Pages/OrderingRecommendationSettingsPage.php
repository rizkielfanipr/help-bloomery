<?php

namespace App\Filament\Helpdesk\Pages;

use App\Actions\Ordering\SaveOrderingStockLocationSettingsAction;
use App\Enums\OrderingStockLocationMode;
use App\Models\BranchEsbCode;
use App\Services\Ordering\OrderingStockLocationDiscoveryService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use UnitEnum;

class OrderingRecommendationSettingsPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Analytics';

    protected static ?string $navigationLabel = 'Ordering Recommendation Settings';

    protected static ?string $title = 'Ordering Recommendation Settings';

    protected static ?string $slug = 'ordering-recommendation/settings';

    protected string $view = 'filament.helpdesk.pages.ordering-recommendation-settings-page';

    public string $search = '';

    #[Locked]
    public ?int $pairId = null;

    public string $mode = 'unconfigured';

    /** @var list<string> */
    public array $selectedLocationFingerprints = [];

    /** @var list<array{fingerprint: string, key: string, name: string}> */
    #[Locked]
    public array $availableLocations = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage ordering recommendation settings') ?? false;
    }

    /** @return Collection<int, BranchEsbCode> */
    public function mappings(): Collection
    {
        abort_unless(static::canAccess(), 403);

        return BranchEsbCode::query()
            ->with('branch:id,name,brand_id')
            ->where('branch_esb_codes.is_active', true)
            ->when(trim($this->search) !== '', function ($query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(function ($query) use ($search): void {
                    $query->where('esb_comcode', 'like', $search)
                        ->orWhere('esb_branch_code', 'like', $search)
                        ->orWhereHas('branch', fn ($query) => $query->where('name', 'like', $search));
                });
            })
            ->join('branches', 'branches.id', '=', 'branch_esb_codes.branch_id')
            ->orderBy('branches.name')
            ->orderBy('branch_esb_codes.esb_comcode')
            ->select('branch_esb_codes.*')
            ->get();
    }

    public function configure(int $pairId): void
    {
        abort_unless(static::canAccess(), 403);
        $pair = BranchEsbCode::query()->where('is_active', true)->findOrFail($pairId);
        $this->pairId = $pair->id;
        $this->mode = ($pair->ordering_stock_location_mode ?? OrderingStockLocationMode::Unconfigured)->value;
        $this->availableLocations = collect($pair->ordering_stock_locations ?? [])
            ->map(fn (array $location): array => $this->location((string) ($location['key'] ?? ''), (string) ($location['name'] ?? '')))
            ->filter(fn (array $location): bool => $location['key'] !== '' && $location['name'] !== '')
            ->values()
            ->all();
        $this->selectedLocationFingerprints = array_column($this->availableLocations, 'fingerprint');
        $this->resetValidation();
        $this->dispatch('open-modal', id: 'ordering-stock-location-settings');
    }

    public function discoverLocations(OrderingStockLocationDiscoveryService $discovery): void
    {
        abort_unless(static::canAccess(), 403);
        $pair = BranchEsbCode::query()->where('is_active', true)->findOrFail($this->pairId);

        try {
            $discovered = $discovery->discover($pair);
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()->title('Lokasi ESB tidak dapat dimuat')->body('Periksa mapping, token, koneksi ESB, lalu coba kembali. Lokasi tersimpan tidak berubah.')->danger()->send();

            return;
        }

        $this->availableLocations = collect([...$this->availableLocations, ...$discovered])
            ->unique('fingerprint')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $notification = Notification::make()
            ->title($discovered === [] ? 'Tidak ada lokasi pada periode 30 hari terakhir' : count($discovered).' lokasi ESB ditemukan');

        if ($discovered === []) {
            $notification->warning();
        } else {
            $notification->success();
        }

        $notification->send();
    }

    public function save(SaveOrderingStockLocationSettingsAction $action): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate([
            'pairId' => ['required', 'integer', Rule::exists('branch_esb_codes', 'id')->where('is_active', true)],
            'mode' => ['required', Rule::enum(OrderingStockLocationMode::class)],
            'selectedLocationFingerprints' => [Rule::requiredIf($this->mode === OrderingStockLocationMode::Selected->value), 'array', $this->mode === OrderingStockLocationMode::Selected->value ? 'min:1' : 'max:0'],
            'selectedLocationFingerprints.*' => ['string', 'distinct', Rule::in(array_column($this->availableLocations, 'fingerprint'))],
        ]);

        $selected = collect($this->availableLocations)
            ->whereIn('fingerprint', $this->selectedLocationFingerprints)
            ->map(fn (array $location): array => ['key' => $location['key'], 'name' => $location['name']])
            ->values()
            ->all();
        $result = $action->execute(auth()->user(), (int) $this->pairId, OrderingStockLocationMode::from($this->mode), $selected);

        Notification::make()
            ->title('Lokasi stok berhasil disimpan')
            ->body($result['resync_jobs'].' job resync dan forecast dijadwalkan. Forecast lama tetap tersedia sampai hasil baru selesai.')
            ->success()
            ->send();
        $this->dispatch('close-modal', id: 'ordering-stock-location-settings');
        $this->resetEditor();
    }

    public function selectMode(string $mode): void
    {
        abort_unless(static::canAccess(), 403);
        $selectedMode = OrderingStockLocationMode::tryFrom($mode);
        abort_if($selectedMode === null, 422);

        $this->mode = $selectedMode->value;
        $this->updatedMode($this->mode);
        $this->resetValidation();
    }

    public function updatedMode(string $mode): void
    {
        if ($mode !== OrderingStockLocationMode::Selected->value) {
            $this->selectedLocationFingerprints = [];
        }
    }

    private function resetEditor(): void
    {
        $this->pairId = null;
        $this->mode = OrderingStockLocationMode::Unconfigured->value;
        $this->selectedLocationFingerprints = [];
        $this->availableLocations = [];
        $this->resetValidation();
    }

    /** @return array{fingerprint: string, key: string, name: string} */
    private function location(string $key, string $name): array
    {
        $key = trim($key);
        $name = Str::squish($name);

        return [
            'fingerprint' => hash('sha256', mb_strtolower($key).'|'.mb_strtolower($name)),
            'key' => $key,
            'name' => $name,
        ];
    }
}
