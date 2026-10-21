<?php

namespace App\Actions\Ordering;

use App\Enums\OrderingConfidence;
use App\Models\OrderingDailyMetric;
use App\Models\OrderingForecastItem;
use App\Models\OrderingForecastRun;
use App\Models\OrderingSyncRun;
use App\Models\User;
use App\Services\Ordering\OrderingReasonCodes;
use App\Services\Ordering\OrderingRecommendationQuery;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Server-side Ordering Recommendation workbook (docs/ordering-recommendation-prd.md §19): the same
 * scoped query contract, filters, and forecast runs as the page, streamed chunk by chunk into
 * Summary, Recommendations, Daily History, and Data Quality sheets. Codes stay strings (leading
 * zeros survive), text never starts a formula, and no token or raw payload is written.
 */
class ExportOrderingRecommendationsXlsxAction
{
    private const CHUNK = 500;

    /** @var list<string> */
    public const RECOMMENDATION_HEADERS = [
        'Branch', 'Company Code', 'ESB Branch Code', 'Product Detail ID', 'Product Code', 'Product Name', 'Category', 'UOM',
        'Historical Demand', 'Average Daily Demand', 'Trend Percentage', 'Trend Label', 'Waste Quantity', 'Waste Rate',
        'Available Stock', 'Stock Observed At', 'Stock Coverage Days', 'Forecast Demand', 'Expected Waste', 'Safety Buffer',
        'Confirmed Inbound', 'Gross Requirement', 'Recommended Quantity', 'Predicted Stockout Date', 'Confidence', 'Explanation', 'Source State',
    ];

    /** @var list<string> */
    public const HISTORY_HEADERS = ['Branch', 'Company Code', 'ESB Branch Code', 'Product Code', 'Product Name', 'UOM', 'Date', 'Sales-driven Usage', 'POS Validation Usage', 'Waste', 'Goods In', 'Ending Stock', 'Usage Read', 'Stock Read'];

    /** @var list<string> */
    public const QUALITY_HEADERS = ['Branch', 'Company Code', 'ESB Branch Code', 'Source', 'Date', 'Status', 'Pages', 'Rows', 'Last Sync', 'Error Summary'];

    public function __construct(private readonly OrderingRecommendationQuery $query) {}

    /**
     * @param  list<int>  $branchIds
     * @param  array<string, mixed>  $filters
     *
     * @throws AuthorizationException
     */
    public function execute(User $user, array $branchIds, int $horizon, array $filters): BinaryFileResponse
    {
        if (! $user->can('export ordering recommendations')) {
            throw new AuthorizationException('Anda tidak berhak mengekspor Ordering Recommendation.');
        }

        $branchIds = $this->query->resolveBranchIds($user, $branchIds);
        $runs = $this->query->latestRuns($branchIds, $horizon);
        $runIds = $runs->pluck('id')->all();
        $historyFrom = $runs->min(fn (OrderingForecastRun $run): string => $run->history_from->toDateString());
        $historyTo = $runs->max(fn (OrderingForecastRun $run): string => $run->history_to->toDateString());
        $states = $runs->map(fn (OrderingForecastRun $run): array => $this->query->sourceState($run));

        $path = tempnam(sys_get_temp_dir(), 'ordering_recommendation_');
        $writer = new Writer;
        $writer->openToFile($path);
        $header = (new Style)->setFontBold()->setBackgroundColor('2563EB')->setFontColor(Color::WHITE);

        $writer->getCurrentSheet()->setName('Summary');
        $writer->addRow(Row::fromValues(['Branch', 'Generated At', 'History Range', 'Forecast Range', 'Algorithm Version', 'Data Completeness', 'Products', 'Need Order', 'Stockout Risk', 'Trend Up', 'High Waste', 'Incomplete'], $header));
        foreach ($runs as $run) {
            $kpis = $this->query->kpis([$run->id], $filters);
            $writer->addRow(Row::fromValues([
                $this->text($run->branch?->name), $run->generated_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '',
                $run->history_from->toDateString().' – '.$run->history_to->toDateString(), $run->forecast_from->toDateString().' – '.$run->forecast_to->toDateString(),
                $this->text($run->algorithm_version), ucfirst($states[$run->branch_id]['state']),
                $kpis['products'], $kpis['need_order'], $kpis['stockout'], $kpis['trend_up'], $kpis['high_waste'], $kpis['incomplete'],
            ]));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Recommendations');
        $writer->addRow(Row::fromValues(self::RECOMMENDATION_HEADERS, $header));
        $branchNames = $runs->mapWithKeys(fn (OrderingForecastRun $run): array => [$run->branch_id => $run->branch?->name]);
        $this->stream($this->query->items($runIds, $filters), $writer, fn (OrderingForecastItem $item): array => [
            $this->text($branchNames[$item->branch_id] ?? ''), $this->text($item->company_code), $this->text($item->esb_branch_code),
            $item->product_detail_id !== null ? (string) $item->product_detail_id : '', $this->text($item->product_code), $this->text($item->product_name),
            $this->text($item->category_name), $this->text($item->uom),
            $this->number($item->historical_demand), $this->number($item->average_daily_demand),
            $item->trendPercent() ?? '', $item->trend_label->getLabel(),
            $this->number($item->historical_waste), $item->waste_rate_capped !== null ? round((float) $item->waste_rate_capped * 100, 2) : '',
            $this->number($item->available_stock), $item->stock_observed_at?->format('Y-m-d H:i:s') ?? '', $this->number($item->coverage_days),
            $this->number($item->forecast_demand), $this->number($item->expected_waste), $this->number($item->safety_buffer),
            $item->confirmed_inbound === null ? 'Not available' : (float) $item->confirmed_inbound,
            $this->number($item->gross_requirement), $this->number($item->final_recommendation),
            $item->predicted_stockout_date?->toDateString() ?? '', $item->confidence->getLabel(),
            $this->text($item->explanation.($item->confidence === OrderingConfidence::Blocked ? '' : ' '.implode('; ', array_map(fn (string $code): string => OrderingReasonCodes::label($code), $item->reason_codes ?? [])))),
            ucfirst($states[$item->branch_id]['state'] ?? 'unknown'),
        ]);

        $writer->addNewSheetAndMakeItCurrent()->setName('Daily History');
        $writer->addRow(Row::fromValues(self::HISTORY_HEADERS, $header));
        if ($historyFrom !== null) {
            $this->stream($this->query->dailyHistory($branchIds, $historyFrom, $historyTo), $writer, fn (OrderingDailyMetric $metric): array => [
                $this->text($metric->branch?->name), $this->text($metric->company_code), $this->text($metric->esb_branch_code), $this->text($metric->product_code),
                $this->text($metric->product_name), $this->text($metric->uom), $metric->metric_date->toDateString(),
                $this->number($metric->sales_driven_usage), $this->number($metric->pos_sales_usage), $this->number($metric->waste_quantity),
                $this->number($metric->goods_in_quantity), $this->number($metric->ending_stock),
                $metric->usage_sync_run_id !== null ? 'Yes' : 'No', $metric->stock_sync_run_id !== null ? 'Yes' : 'No',
            ]);
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Data Quality');
        $writer->addRow(Row::fromValues(self::QUALITY_HEADERS, $header));
        if ($historyFrom !== null) {
            $this->stream($this->query->dataQuality($branchIds, $historyFrom, $historyTo), $writer, fn (OrderingSyncRun $run): array => [
                $this->text($run->branch?->name), $this->text($run->company_code), $this->text($run->esb_branch_code), $run->source->label(),
                $run->period_from->toDateString(), $run->status->getLabel(), $run->pages_fetched, $run->rows_stored,
                $run->finished_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '', $this->text($run->error_summary),
            ]);
        }

        $writer->close();

        return response()->download($path, sprintf('ordering-recommendation-%s-%s-h%d.xlsx', $historyFrom ?? 'none', $historyTo ?? 'none', $horizon), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** @param Closure(mixed): array<int, float|int|string> $row */
    private function stream(Builder $query, Writer $writer, Closure $row): void
    {
        // chunkById pages by key; the read model's own sort is cleared so no row is skipped or repeated.
        $query->reorder()->chunkById(self::CHUNK, function ($records) use ($writer, $row): void {
            foreach ($records as $record) {
                $writer->addRow(Row::fromValues($row($record)));
            }
        });
    }

    private function text(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $text) === 1 ? "'{$text}" : $text;
    }

    private function number(mixed $value): float|string
    {
        return $value === null ? '' : (float) $value;
    }
}
