<?php

namespace App\Services\Rnd\InternalMemo;

use App\Models\RndInternalMemo;
use App\Services\EsbItemJournalService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ESB Master Menu catalog for BLSS only (docs/rnd-internal-memo-prd.md §8.2, §13). Extracts the
 * server-side pagination/search pattern already proven by App\Services\EsbPromotionService
 * instead of copying its raw HTTP calls; this service is intentionally BLSS-only and never
 * accepts a Company Code parameter.
 *
 * `/corev1/master/get-menu` requires a `branchCode` query parameter server-side, even though the
 * user never picks a branch (§2.4). Since Menu Master is a company-wide catalog, this resolves
 * one representative branch for BLSS automatically. The branch list is fetched through
 * App\Services\EsbItemJournalService (ESB Core, per-company login) rather than the legacy
 * `/corev1/branch` path — that path does not exist on this ESB host (confirmed via a direct,
 * live request: HTTP 404 "Page not found", not an auth failure), while EsbItemJournalService's
 * `/branch` call is the one already proven to return real BLSS branches in production.
 *
 * Confirmed response fields (Phase 0 contract report + user confirmation): `menuID`, `menuName`,
 * `menuCode`, `flagActive`, `bomID` (present per Menu, but not always > 0). `categoryDetail` and
 * `bomName` are read defensively since they were not independently proven.
 *
 * Two more contract facts proven via a direct live request against the real API (reported by the
 * user as "search doesn't find every Menu" and "Halaman 1 dari 139" looking wrong):
 * - `limit` is ignored server-side; every page always returns exactly 20 rows regardless of what
 *   is requested. The response's own `limit` field is trusted instead of the requested value.
 * - `menuCode` genuinely filters server-side (proven: searching an exact code returned exactly
 *   one matching row), but `menuName` is silently ignored — the endpoint returns its full
 *   unfiltered catalog no matter what name is sent. Name search is therefore done by fetching the
 *   whole catalog once (cached, same "fetch every page, cache, filter locally" pattern
 *   EsbBillOfMaterialService::getAllBillOfMaterials() already uses for a listing with no reliable
 *   server-side filter) and filtering/paginating it in this class instead.
 */
class InternalMemoMenuCatalogService
{
    public function __construct(private EsbItemJournalService $itemJournal) {}

    /**
     * @return array{rows: list<array<string, mixed>>, page: int, total: int, perPage: int, hasNext: bool}
     */
    public function page(int $page = 1, int $perPage = 10, string $nameSearch = '', string $codeSearch = ''): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $nameSearch = trim($nameSearch);
        $codeSearch = trim($codeSearch);

        if ($nameSearch !== '') {
            return $this->searchByNameLocally($page, $perPage, $nameSearch, $codeSearch);
        }

        return $this->fetchRemotePage($page, $perPage, $codeSearch);
    }

    /**
     * Populates the full-catalog cache allMenus() relies on, without returning it — called when
     * the Menu picker opens so the unavoidable cold-cache cost lands once at that moment instead
     * of hitting the user again the first time they type a Name search.
     */
    public function warmCache(): void
    {
        $this->allMenus();
    }

    /**
     * `menuCode` is proven to filter server-side, so a Code-only (or no) search stays on the
     * cheap, server-paginated path — only Name search needs the full-catalog fallback below.
     *
     * @return array{rows: list<array<string, mixed>>, page: int, total: int, perPage: int, hasNext: bool}
     */
    private function fetchRemotePage(int $page, int $perPage, string $codeSearch): array
    {
        $cacheKey = sprintf(
            'rnd.internal-memo.menu-catalog.%s.%d.%d.%s',
            RndInternalMemo::COMPANY_CODE,
            $page,
            $perPage,
            md5($codeSearch),
        );

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($page, $perPage, $codeSearch): array {
            $raw = $this->requestRawPage($page, $perPage, '', $codeSearch);

            return [
                'rows' => array_map($this->normalize(...), $raw['data']),
                'page' => $page,
                'total' => $raw['count'],
                'perPage' => $raw['limit'],
                'hasNext' => $raw['hasNext'],
            ];
        });
    }

    /**
     * @return array{rows: list<array<string, mixed>>, page: int, total: int, perPage: int, hasNext: bool}
     */
    private function searchByNameLocally(int $page, int $perPage, string $nameSearch, string $codeSearch): array
    {
        $needle = mb_strtolower($nameSearch);
        $codeNeedle = mb_strtolower($codeSearch);

        $filtered = array_values(array_filter(
            $this->allMenus(),
            function (array $menu) use ($needle, $codeNeedle): bool {
                if (! str_contains(mb_strtolower((string) ($menu['menuName'] ?? '')), $needle)) {
                    return false;
                }

                return $codeNeedle === '' || str_contains(mb_strtolower((string) ($menu['menuCode'] ?? '')), $codeNeedle);
            },
        ));

        $total = count($filtered);
        $slice = array_slice($filtered, ($page - 1) * $perPage, $perPage);

        return [
            'rows' => array_map($this->normalize(...), $slice),
            'page' => $page,
            'total' => $total,
            'perPage' => $perPage,
            'hasNext' => ($page * $perPage) < $total,
        ];
    }

    /**
     * The full BLSS Menu catalog (~1,400 Menus / ~70 pages of 20 as of this writing), cached 15
     * minutes; only built when a Name search actually needs it. The remaining pages are fetched
     * in parallel (same `Http::pool()` pattern already used by
     * App\Services\EsbGlobalCoreClient::poolGet() / EsbMasterProductService) after the first page
     * reveals the total page count — sequentially, a ~70-page catalog took over a minute against
     * the real ESB host and once exhausted CLI memory outright; pooled, it is bound by the
     * slowest single request instead of their sum.
     *
     * @return list<array<string, mixed>>
     */
    private function allMenus(): array
    {
        $cacheKey = 'rnd.internal-memo.menu-catalog.all.'.RndInternalMemo::COMPANY_CODE;

        return Cache::remember($cacheKey, now()->addMinutes(15), function (): array {
            $first = $this->requestRawPage(1, 20, '', '');
            $all = $first['data'];
            $totalPages = min(100, (int) ceil($first['count'] / max(1, $first['limit'])));

            if ($totalPages > 1) {
                foreach ($this->fetchRemainingPagesInParallel(range(2, $totalPages)) as $data) {
                    array_push($all, ...$data);
                }
            }

            return $all;
        });
    }

    /**
     * @param  list<int>  $pages
     * @return array<int, list<array<string, mixed>>> keyed by page number, in no particular order
     */
    private function fetchRemainingPagesInParallel(array $pages): array
    {
        $token = $this->token();
        $branchCode = $this->branchCode();
        $url = $this->baseUrl().'/corev1/master/get-menu';
        $timeout = (int) config('esb.core.timeout', 60);

        $responses = Http::pool(fn ($pool) => collect($pages)
            ->map(fn (int $page) => $pool
                ->as((string) $page)
                ->acceptJson()
                ->asJson()
                ->withToken($token)
                ->connectTimeout(10)
                ->timeout($timeout)
                ->get($url, ['page' => $page, 'limit' => 20, 'branchCode' => $branchCode, 'Boolean' => 1]))
            ->all());

        $pagesData = [];

        foreach ($pages as $page) {
            $response = $responses[(string) $page] ?? null;
            // Best-effort: one page failing (timeout, transient 5xx) should not blank out a
            // catalog search entirely — a cold cache rebuilds again in 15 minutes regardless.
            if (! $response instanceof Response || $response->failed()) {
                continue;
            }

            $body = $response->json();
            $result = is_array($body) && is_array($body['result'] ?? null) ? $body['result'] : [];
            $pagesData[$page] = is_array($result['data'] ?? null) ? $result['data'] : [];
        }

        return $pagesData;
    }

    /** @return array{data: list<array<string, mixed>>, limit: int, count: int, hasNext: bool} */
    private function requestRawPage(int $page, int $perPage, string $nameSearch, string $codeSearch): array
    {
        $token = $this->token();
        $branchCode = $this->branchCode();

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken($token)
                ->connectTimeout(10)
                ->timeout((int) config('esb.core.timeout', 60))
                ->get($this->baseUrl().'/corev1/master/get-menu', array_filter([
                    'page' => $page,
                    'limit' => $perPage,
                    'branchCode' => $branchCode,
                    'menuName' => $nameSearch,
                    'menuCode' => $codeSearch,
                    'Boolean' => 1,
                ], fn (string|int $value): bool => (string) $value !== ''));
        } catch (\Throwable $exception) {
            throw new RuntimeException('Gagal menghubungi ESB Master Menu [BLSS]: '.$exception->getMessage(), previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response));
        }

        $body = $response->json();
        $result = is_array($body) && is_array($body['result'] ?? null) ? $body['result'] : [];
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        // The real endpoint ignores `limit` and always returns a fixed page size (proven live:
        // 20) — trust the response's own `limit` field, only falling back when it is absent.
        $limit = max(1, (int) ($result['limit'] ?? $perPage));
        $count = (int) ($result['count'] ?? count($data));

        return [
            'data' => $data,
            'limit' => $limit,
            'count' => $count,
            'hasNext' => filled($body['next'] ?? null) || ($page * $limit) < $count,
        ];
    }

    /** @return array{menuID:int,menuCode:string,menuName:string,categoryDetail:?string,bomID:int,bomName:?string,flagActive:bool,hasBom:bool,raw:array<string,mixed>} */
    private function normalize(mixed $menu): array
    {
        $menu = is_array($menu) ? $menu : [];
        $bomId = (int) ($menu['bomID'] ?? 0);

        return [
            'menuID' => (int) ($menu['menuID'] ?? 0),
            'menuCode' => trim((string) ($menu['menuCode'] ?? '')),
            'menuName' => trim((string) ($menu['menuName'] ?? '')),
            'categoryDetail' => filled($menu['categoryDetail'] ?? null) ? (string) $menu['categoryDetail'] : null,
            'bomID' => $bomId,
            'bomName' => filled($menu['bomName'] ?? null) ? (string) $menu['bomName'] : null,
            'flagActive' => $this->isActive($menu),
            'hasBom' => $bomId > 0,
            'raw' => $menu,
        ];
    }

    private function isActive(array $menu): bool
    {
        $value = $menu['flagActive'] ?? null;
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return true;
    }

    private function token(): string
    {
        $token = trim((string) config('esb.tokens.'.RndInternalMemo::COMPANY_CODE, ''));
        if ($token === '') {
            throw new RuntimeException('Static token ESB Master Menu BLSS belum dikonfigurasi.');
        }

        return $token;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('esb.base_url'), '/');
    }

    /**
     * Resolves one branch code for BLSS to satisfy `/corev1/master/get-menu`'s required
     * `branchCode` parameter, sourced from every branch code ESB has for BLSS
     * (EsbItemJournalService::branches(), itself cached 30 minutes) and using the first one.
     */
    private function branchCode(): string
    {
        $branches = $this->itemJournal->branches(RndInternalMemo::COMPANY_CODE);

        foreach ($branches as $branch) {
            $code = trim((string) ($branch['branchCode'] ?? ''));
            if ($code !== '') {
                return $code;
            }
        }

        throw new RuntimeException('Tidak menemukan Branch Code untuk BLSS di ESB; Master Menu tidak dapat diambil.');
    }

    private function errorMessage(Response $response): string
    {
        $body = $response->json();
        $message = is_array($body) ? (string) ($body['message'] ?? '') : '';

        return 'Gagal memuat Master Menu BLSS'.($message !== '' ? ': '.$message : ' (HTTP '.$response->status().').');
    }
}
