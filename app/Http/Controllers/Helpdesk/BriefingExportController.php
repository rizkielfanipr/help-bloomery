<?php

namespace App\Http\Controllers\Helpdesk;

use App\Enums\BriefingPeriod;
use App\Enums\BriefingTaskKey;
use App\Models\Branch;
use App\Models\BriefingRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BriefingExportController
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        abort_unless(auth()->check(), 403);

        $period = BriefingPeriod::from($request->input('period', 'daily'));
        $branchId = $request->integer('branch_id');
        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);

        $branch = Branch::findOrFail($branchId);
        $taskKeys = BriefingTaskKey::forPeriod($period);
        [$columns, $dateMap] = $this->buildColumns($period, $year, $month);

        $title = match ($period) {
            BriefingPeriod::Daily => 'Daily Briefing Reports',
            BriefingPeriod::Weekly => 'Weekly Briefing Reports',
            BriefingPeriod::Monthly => 'Monthly Briefing Reports',
        };

        $users = User::where('branch_id', $branchId)
            ->whereIn('id', function ($q) use ($period, $year, $month): void {
                $q->select('user_id')
                    ->from('briefing_records')
                    ->where('period', $period->value)
                    ->whereYear('record_date', $year)
                    ->when($period !== BriefingPeriod::Monthly, fn ($q2) => $q2->whereMonth('record_date', $month));
            })
            ->orderBy('name')
            ->get();

        $tempPath = tempnam(sys_get_temp_dir(), 'briefing_export_').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($tempPath);

        $styleTitle = (new Style)
            ->setFontBold()
            ->setFontSize(14)
            ->setBackgroundColor('5B21B6')
            ->setFontColor(Color::WHITE);

        $styleInfo = (new Style)
            ->setFontSize(10)
            ->setBackgroundColor('EDE9FE');

        $styleHeader = (new Style)
            ->setFontBold()
            ->setFontSize(10)
            ->setBackgroundColor('7C3AED')
            ->setFontColor(Color::WHITE);

        $styleOne = (new Style)
            ->setFontBold()
            ->setFontSize(10)
            ->setBackgroundColor('DCFCE7');

        $styleZero = (new Style)
            ->setFontSize(10)
            ->setBackgroundColor('FEE2E2');

        $styleIndicator = (new Style)
            ->setFontSize(10);

        $totalColumns = count($columns);
        $isFirst = true;

        foreach ($users as $user) {
            if ($isFirst) {
                $writer->getCurrentSheet()->setName($this->safeSheetName($user->name));
                $isFirst = false;
            } else {
                $sheet = $writer->addNewSheetAndMakeItCurrent();
                $sheet->setName($this->safeSheetName($user->name));
            }

            $records = BriefingRecord::where('user_id', $user->id)
                ->where('period', $period->value)
                ->whereYear('record_date', $year)
                ->when($period !== BriefingPeriod::Monthly, fn ($q) => $q->whereMonth('record_date', $month))
                ->with('items')
                ->get()
                ->keyBy(fn (BriefingRecord $r) => $r->record_date->toDateString());

            // Title row
            $writer->addRow(Row::fromValues(
                array_merge([$title], array_fill(0, $totalColumns + 1, '')),
                $styleTitle
            ));

            // Info rows
            $writer->addRow(Row::fromValues(
                array_merge(['Branch: '.$branch->name], array_fill(0, $totalColumns + 1, '')),
                $styleInfo
            ));
            $writer->addRow(Row::fromValues(
                array_merge(['User: '.$user->name], array_fill(0, $totalColumns + 1, '')),
                $styleInfo
            ));
            $writer->addRow(Row::fromValues(
                array_merge(['Export Date: '.now()->isoFormat('D MMMM Y')], array_fill(0, $totalColumns + 1, '')),
                $styleInfo
            ));

            // Empty row
            $writer->addRow(Row::fromValues(array_fill(0, $totalColumns + 2, '')));

            // Header row: No | Indikator | col1 | col2 | ...
            $writer->addRow(Row::fromValues(
                array_merge(['No', 'Indikator'], $columns),
                $styleHeader
            ));

            // Data rows
            foreach ($taskKeys as $index => $taskKey) {
                $values = [(string) ($index + 1), $taskKey->getLabel()];

                foreach ($dateMap as $dateKey => $colIndex) {
                    $record = $records->get($dateKey);
                    if (! $record) {
                        $values[] = 0;

                        continue;
                    }
                    $item = $record->items->firstWhere('task_key', $taskKey->value);
                    $values[] = ($item && $item->review_status?->value === 'approved') ? 1 : 0;
                }

                // Build row with per-cell styling
                $cells = [];
                foreach ($values as $i => $value) {
                    if ($i < 2) {
                        $cells[] = Cell::fromValue($value, $styleIndicator);
                    } elseif ($value === 1) {
                        $cells[] = Cell::fromValue(1, $styleOne);
                    } else {
                        $cells[] = Cell::fromValue(0, $styleZero);
                    }
                }

                $writer->addRow(new Row($cells));
            }
        }

        if ($isFirst) {
            // No users found — write an empty sheet with a message
            $writer->getCurrentSheet()->setName('Tidak Ada Data');
            $writer->addRow(Row::fromValues(['Tidak ada data untuk cabang dan periode yang dipilih.']));
        }

        $writer->close();

        $periodSlug = $period->value;
        $filename = "briefing-{$periodSlug}-{$branch->name}-{$year}"
            .($period !== BriefingPeriod::Monthly ? "-{$month}" : '')
            .'.xlsx';

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** @return array{0: string[], 1: array<string, int>} */
    private function buildColumns(BriefingPeriod $period, int $year, int $month): array
    {
        if ($period === BriefingPeriod::Daily) {
            $daysInMonth = Carbon::create($year, $month)->daysInMonth;
            $columns = [];
            $dateMap = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $columns[] = (string) $d;
                $dateMap[Carbon::create($year, $month, $d)->toDateString()] = $d - 1;
            }

            return [$columns, $dateMap];
        }

        if ($period === BriefingPeriod::Weekly) {
            $columns = [];
            $dateMap = [];
            $current = Carbon::create($year, $month, 1)->startOfMonth();
            if (! $current->isMonday()) {
                $current->next(Carbon::MONDAY);
            }
            while ($current->month === $month) {
                $columns[] = $current->isoFormat('D MMM');
                $dateMap[$current->toDateString()] = count($columns) - 1;
                $current->addWeek();
            }

            return [$columns, $dateMap];
        }

        // Monthly — columns = months of the year
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
        $columns = $monthNames;
        $dateMap = [];
        for ($m = 1; $m <= 12; $m++) {
            $dateMap[Carbon::create($year, $m, 1)->toDateString()] = $m - 1;
        }

        return [$columns, $dateMap];
    }

    private function safeSheetName(string $name): string
    {
        $name = preg_replace('/[\/\\\?\*\[\]:]/', '-', $name);

        return substr($name, 0, 31);
    }
}
