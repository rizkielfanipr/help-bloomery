<?php

namespace App\Services\Kitchen;

use App\Models\Branch;
use App\Models\KitchenProductionSchedule;
use App\Models\RndBomCatalog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductionScheduleValidator
{
    public const MAX_ITEMS = 100;

    private const QUANTITY_PATTERN = '/^\d{1,14}(\.\d{1,4})?$/';

    public function __construct(private readonly ProductionBomEligibilityService $eligibility) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{branch: Branch, production_date: string, notes: ?string, items: list<array{catalog: RndBomCatalog, planned_quantity: string, notes: ?string}>}
     */
    public function validate(User $actor, array $input): array
    {
        $input['items'] = array_values(array_map(
            fn (mixed $item): mixed => is_array($item)
                ? [...$item, 'planned_quantity' => $this->normalizeQuantity($item['planned_quantity'] ?? null)]
                : $item,
            is_array($input['items'] ?? null) ? $input['items'] : [],
        ));

        $validated = Validator::make($input, [
            'branch_id' => ['required', 'integer'],
            'production_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*' => ['array'],
            'items.*.rnd_bom_catalog_id' => ['required', 'integer', 'distinct'],
            'items.*.planned_quantity' => ['required', 'string', 'regex:'.self::QUANTITY_PATTERN, 'not_regex:/^0+(\.0+)?$/'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'branch_id.required' => 'Pilih Branch.',
            'production_date.required' => 'Tanggal produksi wajib diisi.',
            'production_date.date_format' => 'Tanggal produksi tidak valid.',
            'notes.max' => 'Catatan jadwal maksimal 2.000 karakter.',
            'items.required' => 'Tambahkan minimal satu BOM Assembly.',
            'items.min' => 'Tambahkan minimal satu BOM Assembly.',
            'items.max' => 'Satu jadwal maksimal memuat '.self::MAX_ITEMS.' BOM.',
            'items.*.rnd_bom_catalog_id.distinct' => 'BOM yang sama hanya boleh ditambahkan satu kali.',
            'items.*.planned_quantity.required' => 'Jumlah produksi wajib diisi.',
            'items.*.planned_quantity.regex' => 'Jumlah produksi harus berupa angka dengan maksimal 4 desimal.',
            'items.*.planned_quantity.not_regex' => 'Jumlah produksi harus lebih dari nol.',
            'items.*.notes.max' => 'Catatan BOM maksimal 1.000 karakter.',
        ])->validate();

        $branch = Branch::query()->find($validated['branch_id']);

        if ($branch === null || ! $actor->canAccessBranch($branch->id)) {
            throw new AuthorizationException('Anda tidak memiliki akses ke Branch ini.');
        }

        if (! $branch->is_active) {
            throw ValidationException::withMessages(['branch_id' => 'Branch ini tidak aktif.']);
        }

        if (! $actor->canAccessAllBranches() && ! $branch->hasKitchenBrand()) {
            throw ValidationException::withMessages(['branch_id' => 'Branch harus terhubung ke Brand Kitchen.']);
        }

        $catalogs = RndBomCatalog::query()
            ->whereKey(collect($validated['items'])->pluck('rnd_bom_catalog_id')->all())
            ->get()
            ->keyBy('id');
        $errors = [];
        $items = [];

        foreach ($validated['items'] as $index => $item) {
            $catalog = $catalogs->get((int) $item['rnd_bom_catalog_id']);

            if ($reason = $this->eligibility->reasonFor($catalog)) {
                $errors["items.{$index}.rnd_bom_catalog_id"] = $reason;

                continue;
            }

            $items[] = [
                'catalog' => $catalog,
                'planned_quantity' => $item['planned_quantity'],
                'notes' => filled($item['notes'] ?? null) ? trim($item['notes']) : null,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'branch' => $branch,
            'production_date' => $validated['production_date'],
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            'items' => $items,
        ];
    }

    public function ensureSlotIsFree(int $branchId, string $productionDate, ?int $ignoreScheduleId = null): void
    {
        $taken = KitchenProductionSchedule::query()
            ->where('branch_id', $branchId)
            ->whereDate('production_date', $productionDate)
            ->when($ignoreScheduleId, fn ($query) => $query->whereKeyNot($ignoreScheduleId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'production_date' => 'Jadwal untuk Branch dan tanggal ini sudah ada. Buka jadwal tersebut untuk mengubahnya.',
            ]);
        }
    }

    private function normalizeQuantity(mixed $value): mixed
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $value = str_replace(',', '.', trim($value));

        if (! preg_match(self::QUANTITY_PATTERN, $value)) {
            return $value;
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';

        return $fraction === '' ? $whole : $whole.'.'.$fraction;
    }
}
