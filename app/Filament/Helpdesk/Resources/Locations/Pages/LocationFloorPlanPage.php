<?php

namespace App\Filament\Helpdesk\Resources\Locations\Pages;

use App\Filament\Helpdesk\Resources\Locations\LocationResource;
use App\Models\Branch;
use App\Models\Location;
use App\Models\LocationType;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LocationFloorPlanPage extends Page
{
    protected static string $resource = LocationResource::class;

    protected string $view = 'filament.helpdesk.locations.floor-plan';

    public ?int $branchId = null;

    /** @var Collection<int, Location> */
    public Collection $locations;

    /** @var array<int, Branch> */
    public array $accessibleBranches = [];

    /** @var array<int, string> */
    public array $typeOptions = [];

    // Accepts an optional explicit $branchId (mainly for Livewire::test(),
    // whose request simulation doesn't carry a real query string) and falls
    // back to the ?branch_id= query param used by the actual page route.
    public function mount(?int $branchId = null): void
    {
        $user = auth()->user();
        abort_unless($user?->can('view locations'), 403);

        $this->accessibleBranches = $user->canAccessAllBranches()
            ? Branch::query()->orderBy('name')->get()->all()
            : Branch::query()->whereIn('id', $user->accessibleBranchIds())->orderBy('name')->get()->all();

        $this->typeOptions = LocationType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name')
            ->all();

        $resolvedBranchId = $branchId ?? (int) request()->query('branch_id', 0);
        $this->branchId = ($resolvedBranchId && $user->canAccessBranch($resolvedBranchId)) ? $resolvedBranchId : null;

        $this->loadLocations();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Denah Lokasi';
    }

    /** @return array<int, array<string, mixed>> */
    public function getTree(): array
    {
        return $this->buildTree(null);
    }

    /** @return array<int, array<string, mixed>> */
    protected function buildTree(?int $parentId): array
    {
        return $this->locations
            ->where('parent_id', $parentId)
            ->map(fn (Location $location): array => [
                'id' => $location->id,
                'name' => $location->name,
                'type' => $location->type,
                'code' => $location->code,
                'pos_x' => (float) ($location->pos_x ?? 4),
                'pos_y' => (float) ($location->pos_y ?? 4),
                'width' => (float) ($location->width ?? 20),
                'height' => (float) ($location->height ?? 14),
                'children' => $this->buildTree($location->id),
            ])
            ->values()
            ->all();
    }

    /** @return array{id: int, parent_id: ?int, name: string, type: string, code: string, pos_x: float, pos_y: float, width: float, height: float} */
    public function createChild(array $data): array
    {
        abort_unless(auth()->user()?->can('create locations'), 403);
        abort_unless($this->branchId !== null, 422);

        $validated = validator($data, [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in($this->typeOptions)],
            'segment' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'parent_id' => ['nullable', 'integer'],
        ])->validate();

        $parentId = $validated['parent_id'] ?? null;

        if ($parentId !== null) {
            abort_unless($this->locations->contains('id', $parentId), 403);
        }

        $segmentTaken = Location::query()
            ->where('branch_id', $this->branchId)
            ->where('parent_id', $parentId)
            ->where('segment', $validated['segment'])
            ->exists();

        if ($segmentTaken) {
            throw ValidationException::withMessages([
                'segment' => 'Kode segmen ini sudah dipakai di level yang sama.',
            ]);
        }

        // Stagger new boxes across a simple 4-column grid (relative to their
        // own siblings) so consecutively added locations under the same
        // parent don't spawn stacked exactly on top of each other.
        $siblingCount = $this->locations->where('parent_id', $parentId)->count();
        $column = $siblingCount % 4;
        $row = intdiv($siblingCount, 4);

        $location = Location::create([
            'branch_id' => $this->branchId,
            'parent_id' => $parentId,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'segment' => $validated['segment'],
            'pos_x' => 4 + ($column * 23),
            'pos_y' => 4 + ($row * 18),
            'width' => 20,
            'height' => 14,
            'sort_order' => $siblingCount,
        ]);

        $this->loadLocations();

        Notification::make()->title('Lokasi ditambahkan')->success()->send();

        return [
            'id' => $location->id,
            'parent_id' => $location->parent_id,
            'name' => $location->name,
            'type' => $location->type,
            'code' => $location->code,
            'pos_x' => (float) $location->pos_x,
            'pos_y' => (float) $location->pos_y,
            'width' => (float) $location->width,
            'height' => (float) $location->height,
        ];
    }

    /** @param array<int, array{id: int, pos_x: float, pos_y: float, width: float, height: float}> $nodes */
    public function saveLayout(array $nodes): void
    {
        abort_unless(auth()->user()?->can('edit locations'), 403);

        $ids = collect($nodes)->pluck('id')->map(fn ($id): int => (int) $id);
        $validIds = $this->locations->pluck('id');

        abort_unless($ids->diff($validIds)->isEmpty(), 403);

        DB::transaction(function () use ($nodes): void {
            foreach ($nodes as $node) {
                Location::whereKey((int) $node['id'])->update([
                    'pos_x' => $node['pos_x'],
                    'pos_y' => $node['pos_y'],
                    'width' => $node['width'],
                    'height' => $node['height'],
                ]);
            }
        });

        $this->loadLocations();

        Notification::make()->title('Layout tersimpan')->success()->send();
    }

    public function deleteNode(int $id): bool
    {
        abort_unless(auth()->user()?->can('delete locations'), 403);

        $location = $this->locations->firstWhere('id', $id);
        abort_unless($location, 404);

        if ($location->children()->exists()) {
            Notification::make()
                ->title('Tidak dapat menghapus')
                ->body('Lokasi ini masih memiliki sub-lokasi.')
                ->danger()
                ->send();

            return false;
        }

        if ($location->qr_svg_path) {
            Storage::disk('b2')->delete($location->qr_svg_path);
        }

        $location->delete();

        $this->loadLocations();

        Notification::make()->title('Lokasi dihapus')->success()->send();

        return true;
    }

    protected function loadLocations(): void
    {
        if ($this->branchId === null) {
            $this->locations = new Collection;

            return;
        }

        $this->locations = Location::query()
            ->where('branch_id', $this->branchId)
            ->orderBy('depth')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
