<?php

namespace App\Console\Commands;

use App\Services\EsbItemJournalService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class EsbNormalizeMilleCrepeWholeMenusCommand extends Command
{
    private const CATEGORY_DETAIL = 'MILLE CREPE - WHOLE';

    /** @var array<string, int> flavor (lowercase) => number used in the MC-WH{NNNN} menu code */
    private const FLAVOR_NUMBERS = [
        'belgian chocolate' => 1,
        'blueberry' => 2,
        'crunchy malted' => 3,
        'lotus biscoff' => 4,
        'matcha' => 5,
        'red velvet' => 7,
        'strawberry cheesecake' => 8,
        'tiramisu' => 9,
        'mathilda choco' => 10,
        'matcha tiramisu' => 11,
        'earl grey lychee' => 12,
        'strawberry mousse' => 13,
        'peach milk tea' => 14,
        'pandan keju' => 15,
    ];

    protected $signature = 'esb:normalize-mille-crepe-whole-menus
        {--company=BLSS : ESB company code}
        {--branch= : Branch code used to read the Master Menu (default: first branch that returns menus)}
        {--include-complex : Also update menus that have package groups, extras, icons, tags or related menus}
        {--execute : Actually update the menus (default is a dry run)}
        {--limit=0 : Only update the first N menus}
        {--report= : Path of the CSV report (default storage/app/esb-normalize-menus-{timestamp}.csv)}';

    protected $description = 'Rename the short name and menu code of the MILLE CREPE - WHOLE menus (Whole {Flavor}, Whole Cake {Flavor} SBY, MC-WH{NNNN}[-SBY]). Every update is verified against the original menu and rolled back on any difference; the run stops at the first problem.';

    public function __construct(private readonly EsbItemJournalService $branches)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        ini_set('memory_limit', '1G');

        $company = mb_strtoupper(trim((string) $this->option('company')));
        $execute = (bool) $this->option('execute');
        $token = trim((string) config("esb.tokens.{$company}", ''));

        if ($token === '') {
            throw new RuntimeException("Static token ESB {$company} belum dikonfigurasi.");
        }

        [$branchCode, $menus] = $this->loadMenus($company, $token);
        $categoryDetailId = $this->categoryDetailId($token);

        $codeOwners = [];
        foreach ($menus as $menu) {
            $code = mb_strtolower(trim((string) ($menu['menuCode'] ?? '')));
            if ($code !== '') {
                $codeOwners[$code][] = (int) $menu['menuID'];
            }
        }

        $scoped = array_values(array_filter($menus, fn (array $menu): bool => ($menu['categoryDetail'] ?? '') === self::CATEGORY_DETAIL));
        usort($scoped, fn (array $a, array $b): int => (int) $a['menuID'] <=> (int) $b['menuID']);

        $reportPath = (string) ($this->option('report') ?: storage_path('app/esb-normalize-menus-'.now()->format('Ymd-His').'.csv'));
        $report = fopen($reportPath, 'w');
        fputcsv($report, ['company', 'menu_id', 'menu_name', 'old_code', 'new_code', 'old_short_name', 'new_short_name', 'status', 'message'], ',', '"', '');

        $this->info(($execute ? 'EXECUTE' : 'DRY RUN')." {$company} (branch {$branchCode}): ".count($menus).' menus, '.count($scoped).' in '.self::CATEGORY_DETAIL);

        $limit = (int) $this->option('limit');
        $changed = 0;
        $tally = [];
        $stop = false;

        foreach ($scoped as $menu) {
            if ($stop || ($limit > 0 && $changed >= $limit)) {
                break;
            }

            $id = (int) $menu['menuID'];
            $oldCode = trim((string) ($menu['menuCode'] ?? ''));
            $oldShort = trim((string) ($menu['menuShortName'] ?? ''));
            [$newShort, $newCode, $problem] = $this->target($menu);
            $message = '';

            if ($problem !== null) {
                $status = 'flavor_unmapped';
                $message = $problem;
            } elseif ($oldShort === $newShort && $oldCode === $newCode) {
                $status = 'already_done';
            } elseif (array_diff($codeOwners[mb_strtolower($newCode)] ?? [], [$id]) !== []) {
                $status = 'code_conflict';
                $message = 'new code already used by menu '.implode(',', $codeOwners[mb_strtolower($newCode)]);
            } elseif (! (bool) $this->option('include-complex') && $this->isComplex($menu)) {
                $status = 'skipped_complex';
                $message = 'has package groups, extras, icons, tags or related menus';
            } elseif (! $execute) {
                $status = 'would_update';
                $changed++;
            } else {
                $changed++;
                [$status, $message] = $this->update($company, $token, $branchCode, $categoryDetailId, $menu, $newCode, $newShort);
                $stop = $status !== 'updated';
            }

            $tally[$status] = ($tally[$status] ?? 0) + 1;
            fputcsv($report, [$company, $id, $menu['menuName'], $oldCode, $newCode ?? '', $oldShort, $newShort ?? '', $status, $message], ',', '"', '');
            $this->line("{$id}  {$status}  {$oldCode} -> {$newCode}  |  {$oldShort} -> {$newShort}".($message !== '' ? "  {$message}" : ''));
        }

        fclose($report);

        foreach ($tally as $status => $count) {
            $this->info("{$status}: {$count}");
        }
        $this->info("Report: {$reportPath}");

        return $stop ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string} new short name, new code, problem
     */
    private function target(array $menu): array
    {
        $short = trim((string) preg_replace('/\s+/', ' ', (string) ($menu['menuShortName'] ?? '')));
        $isSby = preg_match('/\sSBY$/i', $short) === 1 || preg_match('/-SBY$/i', (string) ($menu['menuCode'] ?? '')) === 1;

        $flavor = (string) preg_replace('/\sSBY$/i', '', $short);
        $flavor = (string) preg_replace(['/^Whole Cake\s+/i', '/^Whole\s+/i', '/\s+Whole Cake$/i', '/\s+Whole$/i'], '', $flavor);
        $flavor = trim($flavor);

        $number = self::FLAVOR_NUMBERS[mb_strtolower($flavor)] ?? null;

        if ($number === null || $flavor === '') {
            return [null, null, "flavor '{$flavor}' has no code number"];
        }

        return [
            $isSby ? "Whole Cake {$flavor} SBY" : "Whole {$flavor}",
            'MC-WH'.str_pad((string) $number, 4, '0', STR_PAD_LEFT).($isSby ? '-SBY' : ''),
            null,
        ];
    }

    private function isComplex(array $menu): bool
    {
        $groups = (array) data_get($menu, 'menuPackages.menuGroup', []);

        return $groups !== [] || ! empty($menu['menuExtras']) || ! empty($menu['menuIcons']) || ! empty($menu['menuTags']) || ! empty($menu['relatedMenus']);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function update(string $company, string $token, string $branchCode, int $categoryDetailId, array $menu, string $newCode, string $newShort): array
    {
        $id = (int) $menu['menuID'];

        try {
            $this->post($token, $this->payload($menu, $categoryDetailId, $newCode, $newShort));
            $after = $this->fetchByCode($token, $branchCode, $newCode);
        } catch (Throwable $exception) {
            return ['error', $this->rollback($token, $branchCode, $categoryDetailId, $menu, $exception->getMessage())];
        }

        $differences = $this->differences($menu, $after, $id);

        if ($differences !== []) {
            return ['mismatch', $this->rollback($token, $branchCode, $categoryDetailId, $menu, 'data differs after update: '.implode(', ', $differences))];
        }

        return ['updated', ''];
    }

    private function rollback(string $token, string $branchCode, int $categoryDetailId, array $menu, string $reason): string
    {
        try {
            $this->post($token, $this->payload($menu, $categoryDetailId, trim((string) $menu['menuCode']), trim((string) $menu['menuShortName'])));
            $restored = $this->fetchByCode($token, $branchCode, trim((string) $menu['menuCode']));
            $left = $this->differences($menu, $restored, (int) $menu['menuID'], includeNames: true);

            return $reason.' | rolled back'.($left === [] ? ' and verified identical' : ' but still differs: '.implode(', ', $left));
        } catch (Throwable $exception) {
            return $reason.' | ROLLBACK FAILED: '.$exception->getMessage();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $menu, int $categoryDetailId, string $code, string $shortName): array
    {
        $yes = fn (mixed $value): bool => in_array(mb_strtolower(trim((string) $value)), ['yes', '1', 'true'], true);
        $tax = ['no' => 0, 'yes, tax' => 1, 'yes, vat' => 2][mb_strtolower(trim((string) $menu['flagTax']))] ?? null;

        if ($tax === null) {
            throw new RuntimeException("Unknown flagTax '{$menu['flagTax']}'");
        }

        return [
            'menuID' => (int) $menu['menuID'],
            'menuCategoryDetailID' => $categoryDetailId,
            'bomID' => (int) ($menu['bomID'] ?? 0),
            'menuName' => $menu['menuName'],
            'menuCode' => $code,
            'menuShortName' => $shortName,
            'alternativeMenuName' => (string) ($menu['alternativeMenuName'] ?? ''),
            'flagTax' => $tax,
            'flagOtherTax' => $yes($menu['flagOtherTax']),
            'zeroValueText' => (string) ($menu['zeroValueText'] ?? '0'),
            'salesAccount' => $menu['salesAccount'],
            'cogsAccount' => $menu['cogsAccount'],
            'discountAccount' => $menu['discountAccount'],
            'description' => (string) ($menu['description'] ?? ''),
            'flagOpenPrice' => $yes($menu['flagOpenPrice']),
            'printZeroValue' => $yes($menu['printZeroValue']),
            'themeMenuOnPos' => (string) ($menu['themeMenuOnPos'] ?? ''),
            'notes' => (string) ($menu['notes'] ?? ''),
            'flagSeparatePrintPackage' => $yes(data_get($menu, 'menuPackages.flagSeparatePrintPackage')),
            'flagSeparateTaxCalculation' => $yes(data_get($menu, 'menuPackages.flagSeparateTaxCalculation')),
            'menuTemplates' => array_values(array_map(
                fn (array $template): array => ['menuTemplateID' => (int) $template['menuTemplateID'], 'price' => (float) $template['price']],
                array_filter((array) $menu['menuTemplates'], fn (array $template): bool => (bool) $template['flagActive']),
            )),
            'updateCheckerAndStation' => false,
            'menuPackages' => array_map(fn (array $group): array => [
                'menuGroupID' => $group['menuGroupID'],
                'menuGroupName' => $group['menuGroupName'],
                'minQty' => (float) $group['minQty'],
                'maxQty' => (float) $group['maxQty'],
                'notes' => (string) ($group['notes'] ?? ''),
                'orderID' => (int) $group['orderID'],
                'flagActive' => (bool) $group['flagActive'],
                'menus' => array_map(fn (array $item): array => [
                    'menuID' => (int) $item['menuID'],
                    'menuName' => $item['menuName'],
                    'menuCode' => (string) ($item['menuCode'] ?? ''),
                    'price' => (float) ($item['additionalPrice'] ?? 0),
                    'defaultItem' => $yes($item['defaultItem'] ?? false),
                    'menuTemplatePackages' => array_map(
                        fn (array $package): array => ['menuTemplateID' => (int) $package['menuTemplateID'], 'price' => (float) $package['price']],
                        (array) ($item['menuTemplatePackages'] ?? []),
                    ),
                ], (array) ($group['menus'] ?? [])),
            ], array_values((array) data_get($menu, 'menuPackages.menuGroup', []))),
            'menuExtras' => array_map(fn (array $extra): array => [
                'menuExtraID' => $extra['menuExtraID'],
                'menuID' => (int) ($extra['menuID'] ?? 0),
                'menuName' => $extra['menuExtraName'],
                'price' => (float) $extra['price'],
                'minExtraQty' => (float) $extra['minExtraQty'],
                'maxExtraQty' => (float) $extra['maxExtraQty'],
                'color' => (string) ($extra['color'] ?? ''),
            ], array_values((array) ($menu['menuExtras'] ?? []))),
            'menuIcons' => array_map(fn (array $icon): array => ['menuIconName' => $icon['menuIconName']], array_values((array) ($menu['menuIcons'] ?? []))),
            'menuTags' => array_map(fn (array $tag): array => ['tagName' => $tag['tagName']], array_values((array) ($menu['menuTags'] ?? []))),
            'relatedMenus' => array_map(fn (array $related): array => [
                'menuID' => (int) $related['menuID'], 'menuName' => $related['menuName'], 'menuCode' => (string) ($related['menuCode'] ?? ''),
            ], array_values((array) ($menu['relatedMenus'] ?? []))),
        ];
    }

    /**
     * @return array<int, string> names of the fields that differ (the code and short name are expected to change)
     */
    private function differences(array $before, ?array $after, int $id, bool $includeNames = false): array
    {
        if ($after === null || (int) ($after['menuID'] ?? 0) !== $id) {
            return ['menu not found by its code after the update'];
        }

        $skip = $includeNames ? [] : ['menuCode', 'menuShortName'];
        $differences = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            if (in_array($key, $skip, true)) {
                continue;
            }

            if ($this->canonical($before[$key] ?? null) !== $this->canonical($after[$key] ?? null)) {
                $differences[] = $key;
            }
        }

        return $differences;
    }

    private function canonical(mixed $value): string
    {
        $normalize = function (mixed $item) use (&$normalize): mixed {
            if (is_array($item)) {
                $item = array_map($normalize, $item);
                if (array_is_list($item)) {
                    usort($item, fn ($a, $b): int => strcmp(json_encode($a), json_encode($b)));
                } else {
                    ksort($item);
                }

                return $item;
            }

            return is_numeric($item) ? (string) (float) $item : $item;
        };

        return (string) json_encode($normalize($value));
    }

    private function post(string $token, array $payload): void
    {
        $response = Http::acceptJson()->asJson()->withToken($token)->timeout(60)
            ->post(rtrim((string) config('esb.base_url'), '/').'/corev1/master/update-menu', [$payload]);

        $this->assertOk($response, 'update-menu');
    }

    private function assertOk(Response $response, string $action): void
    {
        if ($response->failed() || $response->json('status') !== 'ok') {
            throw new RuntimeException("{$action} gagal: ".substr((string) $response->body(), 0, 600));
        }
    }

    private function fetchByCode(string $token, string $branchCode, string $code): ?array
    {
        $response = Http::acceptJson()->withToken($token)->timeout(60)
            ->get(rtrim((string) config('esb.base_url'), '/').'/corev1/master/get-menu', ['page' => 1, 'branchCode' => $branchCode, 'menuCode' => $code]);
        $this->assertOk($response, 'get-menu');
        $rows = (array) $response->json('result.data', []);

        return count($rows) === 1 ? $rows[0] : null;
    }

    private function categoryDetailId(string $token): int
    {
        [$category, $detail] = array_map('trim', explode(' - ', self::CATEGORY_DETAIL));

        for ($page = 1; $page <= 50; $page++) {
            $response = Http::acceptJson()->withToken($token)->timeout(60)
                ->get(rtrim((string) config('esb.base_url'), '/').'/corev1/master/get-menu-category', ['page' => $page]);
            $this->assertOk($response, 'get-menu-category');
            $rows = (array) $response->json('result.data', []);

            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                if (mb_strtoupper(trim((string) $row['menuCategoryName'])) !== $category) {
                    continue;
                }
                foreach ((array) ($row['menuCategoryDetails'] ?? []) as $item) {
                    if (mb_strtoupper(trim((string) $item['menuCategoryDetailName'])) === $detail) {
                        return (int) $item['menuCategoryDetailID'];
                    }
                }
            }
        }

        throw new RuntimeException('Category detail '.self::CATEGORY_DETAIL.' tidak ditemukan.');
    }

    /**
     * @return array{0: string, 1: array<int, array<string, mixed>>}
     */
    private function loadMenus(string $company, string $token): array
    {
        $candidates = $this->option('branch') !== null && $this->option('branch') !== ''
            ? [mb_strtoupper(trim((string) $this->option('branch')))]
            : array_slice(collect($this->branches->branches($company))
                ->map(fn ($branch): string => mb_strtoupper(trim((string) ($branch['branchCode'] ?? ''))))
                ->filter()->unique()->sort()->values()->all(), 0, 6);

        foreach ($candidates as $branchCode) {
            $menus = [];
            for ($page = 1; $page <= 300; $page++) {
                $response = Http::acceptJson()->withToken($token)->timeout(60)
                    ->get(rtrim((string) config('esb.base_url'), '/').'/corev1/master/get-menu', ['page' => $page, 'branchCode' => $branchCode]);

                if ($response->failed() || $response->json('status') !== 'ok') {
                    break;
                }

                $rows = (array) $response->json('result.data', []);
                if ($rows === []) {
                    break;
                }

                foreach ($rows as $row) {
                    $menus[(int) $row['menuID']] = $row;
                }
            }

            if ($menus !== []) {
                return [$branchCode, array_values($menus)];
            }
        }

        throw new RuntimeException("Master Menu {$company} tidak dapat dibaca dari cabang mana pun.");
    }
}
