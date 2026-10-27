<?php

namespace App\Actions\Ordering;

use App\Enums\OrderingSource;
use App\Enums\OrderingStockLocationMode;
use App\Jobs\Ordering\GenerateOrderingForecastJob;
use App\Jobs\Ordering\SyncOrderingSourceJob;
use App\Models\BranchEsbCode;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaveOrderingStockLocationSettingsAction
{
    /**
     * @param  list<array{key: string, name: string}>  $locations
     * @return array{pair: BranchEsbCode, resync_jobs: int}
     */
    public function execute(User $actor, int $pairId, OrderingStockLocationMode $mode, array $locations): array
    {
        abort_unless($actor->can('manage ordering recommendation settings'), 403);

        Validator::make(
            ['mode' => $mode->value, 'locations' => $locations],
            [
                'mode' => ['required', Rule::enum(OrderingStockLocationMode::class)],
                'locations' => [Rule::requiredIf($mode === OrderingStockLocationMode::Selected), 'array', $mode === OrderingStockLocationMode::Selected ? 'min:1' : 'max:0'],
                'locations.*.key' => ['required', 'string', 'max:255', 'distinct'],
                'locations.*.name' => ['required', 'string', 'max:255'],
            ],
        )->validate();

        $pair = DB::transaction(function () use ($pairId, $mode, $locations): BranchEsbCode {
            $pair = BranchEsbCode::query()->where('is_active', true)->lockForUpdate()->findOrFail($pairId);
            $pair->update([
                'ordering_stock_location_mode' => $mode,
                'ordering_stock_locations' => $mode === OrderingStockLocationMode::Selected ? array_values($locations) : null,
                'ordering_stock_locations_updated_at' => now(),
            ]);

            return $pair->refresh();
        });

        Log::info('Ordering stock locations updated.', [
            'actor_id' => $actor->id,
            'branch_id' => $pair->branch_id,
            'pair_id' => $pair->id,
            'company_code' => $pair->esb_comcode,
            'esb_branch_code' => $pair->esb_branch_code,
            'mode' => $mode->value,
            'location_keys' => array_column($locations, 'key'),
        ]);

        return ['pair' => $pair, 'resync_jobs' => $this->dispatchResync($pair)];
    }

    private function dispatchResync(BranchEsbCode $pair): int
    {
        $cutoff = CarbonImmutable::yesterday();
        $from = $cutoff->subDays((int) config('ordering_recommendation.history_weeks') * 7 - 1);
        $jobs = [];

        foreach (CarbonPeriod::create($from, $cutoff) as $date) {
            $jobs[] = new SyncOrderingSourceJob(OrderingSource::DailyUsage->value, $pair->id, $date->toDateString());
            $jobs[] = new SyncOrderingSourceJob(OrderingSource::StockMovement->value, $pair->id, $date->toDateString(), true);
        }
        foreach (config('ordering_recommendation.horizons') as $horizon) {
            $jobs[] = new GenerateOrderingForecastJob($pair->branch_id, $cutoff->toDateString(), (int) $horizon);
        }

        Bus::chain($jobs)->dispatch();

        return count($jobs);
    }
}
