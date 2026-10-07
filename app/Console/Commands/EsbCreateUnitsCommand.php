<?php

namespace App\Console\Commands;

use App\Services\EsbCoreClient;
use Illuminate\Console\Command;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;

class EsbCreateUnitsCommand extends Command
{
    private const MAX_NAME_LENGTH = 50;

    protected $signature = 'esb:create-units
        {file : Path to an .xlsx with a column of unit names such as Resep@100GR}
        {--company=BLSS : ESB Core company code}
        {--column=Unit UOM : Header of the column holding the unit names}
        {--sheet= : Sheet name to read (default: the first sheet)}
        {--execute : Actually POST the units (default is a dry run)}
        {--limit=0 : Only create the first N missing units}
        {--report= : Path of the CSV report (default storage/app/esb-create-units-{timestamp}.csv)}';

    protected $description = 'Create the units listed in an Excel file (e.g. Resep@100GR) in ESB Core, skipping the ones that already exist. The metric follows the base unit after the @.';

    public function handle(EsbCoreClient $esb): int
    {
        $company = mb_strtoupper(trim((string) $this->option('company')));
        $execute = (bool) $this->option('execute');
        $names = $this->readUnitNames((string) $this->argument('file'), (string) $this->option('column'));

        $existing = $esb->successfulResult(
            $esb->request($company, 'get', '/units', ['limit' => 1000, 'page' => 1]),
            'mengambil daftar unit', $company, '/units',
        );
        $units = (array) ($existing['data'] ?? []);

        if ((int) ($existing['count'] ?? count($units)) > count($units)) {
            throw new RuntimeException('Daftar unit ESB lebih besar dari satu halaman; hentikan agar tidak membuat duplikat.');
        }

        $existingNames = array_map(fn (array $unit): string => mb_strtolower(trim((string) $unit['uomName'])), $units);
        $metricByBaseUnit = [];
        foreach ($units as $unit) {
            if (! str_contains((string) $unit['uomName'], '@')) {
                $metricByBaseUnit[mb_strtoupper(trim((string) $unit['uomName']))] = (int) $unit['metricID'];
            }
        }

        $reportPath = (string) ($this->option('report') ?: storage_path('app/esb-create-units-'.now()->format('Ymd-His').'.csv'));
        $report = fopen($reportPath, 'w');
        fputcsv($report, ['uom_name', 'metric_id', 'status', 'message'], ',', '"', '');

        $this->info(($execute ? 'EXECUTE' : 'DRY RUN')." {$company}: ".count($names).' unique unit names');

        $limit = (int) $this->option('limit');
        $created = 0;
        $tally = [];

        foreach ($names as $name) {
            $metricId = $this->metricFor($name, $metricByBaseUnit);
            $message = '';

            if (in_array(mb_strtolower($name), $existingNames, true)) {
                $status = 'already_exists';
            } elseif ($metricId === null) {
                $status = 'unknown_base_unit';
                $message = 'base unit after @ is not an existing plain unit';
            } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
                $status = 'name_too_long';
            } elseif ($limit > 0 && $created >= $limit) {
                continue;
            } elseif (! $execute) {
                $status = 'would_create';
                $created++;
            } else {
                try {
                    $esb->successfulResult(
                        $esb->request($company, 'post', '/units', ['metricID' => $metricId, 'uomName' => $name]),
                        'membuat unit', $company, '/units',
                    );
                    $status = 'created';
                    $created++;
                } catch (Throwable $exception) {
                    $status = 'error';
                    $message = $exception->getMessage();
                }
            }

            $tally[$status] = ($tally[$status] ?? 0) + 1;
            fputcsv($report, [$name, $metricId, $status, $message], ',', '"', '');
            $this->line("{$name}  {$status}".($message !== '' ? "  {$message}" : ''));
        }

        fclose($report);

        foreach ($tally as $status => $count) {
            $this->info("{$status}: {$count}");
        }
        $this->info("Report: {$reportPath}");

        return ($tally['error'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $metricByBaseUnit
     */
    private function metricFor(string $name, array $metricByBaseUnit): ?int
    {
        if (preg_match('/@[0-9]+(?:\.[0-9]+)?(.+)$/', $name, $matches) !== 1) {
            return null;
        }

        return $metricByBaseUnit[mb_strtoupper(trim($matches[1]))] ?? null;
    }

    /**
     * @return array<int, string>
     */
    private function readUnitNames(string $file, string $column): array
    {
        if (! is_file($file)) {
            throw new RuntimeException("File tidak ditemukan: {$file}");
        }

        $reader = new Reader;
        $reader->open($file);

        $names = [];
        $index = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            if ((string) $this->option('sheet') !== '' && $sheet->getName() !== (string) $this->option('sheet')) {
                continue;
            }

            foreach ($sheet->getRowIterator() as $row) {
                $values = array_map(fn ($value): string => trim((string) $value), $row->toArray());

                if ($index === null) {
                    $found = array_search(mb_strtolower($column), array_map('mb_strtolower', $values), true);
                    $index = $found === false ? null : $found;

                    continue;
                }

                if (($values[$index] ?? '') !== '') {
                    $names[$values[$index]] = $values[$index];
                }
            }
            break;
        }
        $reader->close();

        if ($index === null) {
            throw new RuntimeException("Kolom {$column} tidak ditemukan.");
        }

        return array_values($names);
    }
}
