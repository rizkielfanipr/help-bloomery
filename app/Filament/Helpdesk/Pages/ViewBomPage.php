<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\RndProject;
use App\Models\RndProjectBom;
use App\Models\RndProjectProduct;
use App\Services\EsbCoreService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\RateLimiter;

class ViewBomPage extends Page
{
    protected static ?string $slug = 'rnd-projects/{project}/products/{product}/bom/{bom}/view';

    protected static ?string $title = 'Detail Bill of Material';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.view-bom';

    public int $bomId;

    public int $projectId;

    public string $projectName = '';

    public int $productId;

    public string $productName = '';

    public array $detail = [];

    public string $pin = '';

    public bool $unlocked = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('SUPERADMIN')
            || ($user?->can('view bill of materials') ?? false);
    }

    public function mount(int $project, int $product, int $bom): void
    {
        $projectRecord = RndProject::query()->findOrFail($project);
        $productRecord = RndProjectProduct::query()
            ->where('rnd_project_id', $projectRecord->id)
            ->findOrFail($product);
        abort_unless(
            RndProjectBom::query()
                ->where('rnd_project_id', $projectRecord->id)
                ->where('esb_bom_id', $bom)
                ->whereHas('products', fn ($query) => $query->where('rnd_project_products.id', $productRecord->id))
                ->exists(),
            404,
        );
        $this->projectId = $projectRecord->id;
        $this->projectName = $projectRecord->name;
        $this->productId = $productRecord->id;
        $this->productName = $productRecord->name;
        $this->bomId = $bom;
        $this->unlocked = $this->hasActiveUnlock();
        if ($this->unlocked) {
            $this->loadDetail();
        }
    }

    public function verifyPin(): void
    {
        $this->validate(['pin' => ['required', 'string', 'max:20']]);
        $key = 'rnd-bom-pin:'.auth()->id().':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('pin', 'Terlalu banyak percobaan. Silakan coba lagi dalam '.RateLimiter::availableIn($key).' detik.');

            return;
        }

        $configuredPin = (string) config('rnd.bom_pin');
        if ($configuredPin === '' || ! hash_equals($configuredPin, $this->pin)) {
            RateLimiter::hit($key, 60);
            $this->reset('pin');
            $this->addError('pin', $configuredPin === '' ? 'PIN resep belum dikonfigurasi oleh administrator.' : 'PIN yang dimasukkan tidak sesuai.');

            return;
        }

        RateLimiter::clear($key);
        session()->put($this->unlockSessionKey(), now()->addMinutes(config('rnd.bom_pin_ttl_minutes', 15))->timestamp);
        $this->unlocked = true;
        $this->reset('pin');
        $this->loadDetail();
    }

    private function hasActiveUnlock(): bool
    {
        return (int) session()->get($this->unlockSessionKey(), 0) > now()->timestamp;
    }

    private function unlockSessionKey(): string
    {
        return 'rnd.bom.unlocked.'.auth()->id().'.'.$this->projectId.'.'.$this->productId.'.'.$this->bomId;
    }

    private function loadDetail(): void
    {
        $this->detail = app(EsbCoreService::class)->getBillOfMaterial($this->bomId);
    }
}
