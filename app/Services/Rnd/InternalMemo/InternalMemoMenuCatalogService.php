<?php

namespace App\Services\Rnd\InternalMemo;

use App\Models\RndInternalMemo;
use App\Services\EsbItemJournalService;
use Illuminate\Http\Client\Response;
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
 */
class InternalMemoMenuCatalogService
{
    public function __construct(private EsbItemJournalService $itemJournal) {}

    /**
     * @return array{rows: list<array<string, mixed>>, page: int, total: int, perPage: int, hasNext: bool}
     */
    public function page(int $page = 1, int $perPage = 10, string $nameSearch = '', string $codeSearch = ''): array
    {
        $token = $this->token();
        $branchCode = $this->branchCode();
        $page = max(1, $page);
        $perPage = max(1, $perPage);

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
                    'menuName' => trim($nameSearch),
                    'menuCode' => trim($codeSearch),
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
        $limit = max(1, (int) ($result['limit'] ?? $perPage));
        $count = (int) ($result['count'] ?? count($data));

        return [
            'rows' => array_map($this->normalize(...), $data),
            'page' => $page,
            'total' => $count,
            'perPage' => $perPage,
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
