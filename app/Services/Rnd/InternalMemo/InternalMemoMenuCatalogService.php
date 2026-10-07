<?php

namespace App\Services\Rnd\InternalMemo;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Reads Master Menu for one resolved Company Code and Branch Code. This service is used only by
 * SyncInternalMemoMenuCatalogJob; interactive Livewire requests read the local catalog snapshot.
 */
class InternalMemoMenuCatalogService
{
    /**
     * Fields kept in the stored Menu snapshot. A full ESB Menu row is ~48 KB (templates, packages,
     * extras, images); keeping all of them for ~1.400 Menus exhausted the worker memory and none of
     * those nested fields is read anywhere.
     */
    private const SNAPSHOT_FIELDS = ['menuID', 'menuCode', 'menuName', 'menuShortName', 'categoryDetail', 'bomID', 'bomName', 'flagActive', 'description'];

    private const CONNECT_TIMEOUT = 3;

    private const REQUEST_TIMEOUT = 20;

    /** @return list<array<string, mixed>> */
    public function allForContext(string $companyCode, string $branchCode): array
    {
        $companyCode = mb_strtoupper(trim($companyCode));
        $branchCode = mb_strtoupper(trim($branchCode));

        if ($companyCode === '' || $branchCode === '') {
            throw new RuntimeException('Company Code dan Branch Code wajib tersedia untuk sinkronisasi Master Menu.');
        }

        // Each page is normalized (and its heavy raw rows released) before the next one is fetched,
        // so memory stays flat no matter how many Menus ESB returns.
        $menus = [];
        $page = 1;
        $totalPages = 1;

        do {
            $result = $this->requestPage($companyCode, $branchCode, $page);
            $totalPages = $page === 1 ? min(100, (int) ceil($result['count'] / max(1, $result['limit']))) : $totalPages;

            foreach ($result['data'] as $row) {
                $menu = $this->normalize($row);
                if ($menu['menuID'] > 0 && $menu['flagActive'] && ! isset($menus[$menu['menuID']])) {
                    $menus[$menu['menuID']] = $menu;
                }
            }

            unset($result);
            $page++;
        } while ($page <= $totalPages);

        return array_values($menus);
    }

    /** @return array{data:list<array<string,mixed>>,limit:int,count:int} */
    private function requestPage(string $companyCode, string $branchCode, int $page): array
    {
        $token = trim((string) config("esb.tokens.{$companyCode}", ''));
        if ($token === '') {
            throw new RuntimeException("Static token ESB Master Menu {$companyCode} belum dikonfigurasi.");
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken($token)
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->timeout(self::REQUEST_TIMEOUT)
                ->get(rtrim((string) config('esb.base_url'), '/').'/corev1/master/get-menu', [
                    'page' => $page,
                    'limit' => 20,
                    'branchCode' => $branchCode,
                    'Boolean' => 1,
                ]);
        } catch (Throwable $exception) {
            throw new RuntimeException("Gagal menghubungi ESB Master Menu [{$companyCode}/{$branchCode}]: {$exception->getMessage()}", previous: $exception);
        }

        if ($response->failed()) {
            $message = trim((string) data_get($response->json(), 'message', ''));
            throw new RuntimeException("Gagal memuat Master Menu [{$companyCode}/{$branchCode}]".($message !== '' ? ": {$message}" : " (HTTP {$response->status()})."));
        }

        $result = (array) data_get($response->json(), 'result', []);

        return [
            'data' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'limit' => max(1, (int) ($result['limit'] ?? 20)),
            'count' => max(0, (int) ($result['count'] ?? 0)),
        ];
    }

    /** @return array{menuID:int,menuCode:string,menuName:string,categoryDetail:?string,bomID:int,bomName:?string,flagActive:bool,hasBom:bool,raw:array<string,mixed>} */
    private function normalize(mixed $menu): array
    {
        $menu = is_array($menu) ? $menu : [];
        $bomId = (int) ($menu['bomID'] ?? 0);
        $active = $menu['flagActive'] ?? true;

        return [
            'menuID' => (int) ($menu['menuID'] ?? 0),
            'menuCode' => trim((string) ($menu['menuCode'] ?? '')),
            'menuName' => trim((string) ($menu['menuName'] ?? '')),
            'categoryDetail' => filled($menu['categoryDetail'] ?? null) ? (string) $menu['categoryDetail'] : null,
            'bomID' => $bomId,
            'bomName' => filled($menu['bomName'] ?? null) ? (string) $menu['bomName'] : null,
            'flagActive' => is_bool($active) ? $active : (! is_numeric($active) || (int) $active === 1),
            'hasBom' => $bomId > 0,
            'raw' => array_intersect_key($menu, array_flip(self::SNAPSHOT_FIELDS)),
        ];
    }
}
