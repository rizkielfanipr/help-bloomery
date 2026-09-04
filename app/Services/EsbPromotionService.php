<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EsbPromotionService
{
    /** @return array<string, string> */
    public function branchOptions(array $comcodes): array
    {
        $options = [];

        foreach ($comcodes as $comcode) {
            $token = trim((string) config("esb.tokens.{$comcode}", ''));
            if ($token === '') {
                continue;
            }

            $cacheKey = 'esb.promotion.branches.'.md5($comcode);
            $rows = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($token): array {
                try {
                    $response = Http::acceptJson()
                        ->asJson()
                        ->withToken($token)
                        ->connectTimeout(10)
                        ->timeout((int) config('esb.core.timeout', 60))
                        ->get($this->baseUrl().'/corev1/branch');

                    if ($response->failed()) {
                        return [];
                    }

                    $body = $response->json();
                    if (! is_array($body)) {
                        return [];
                    }

                    $data = data_get($body, 'data', data_get($body, 'result.data', data_get($body, 'result', [])));

                    return is_array($data) ? $data : [];
                } catch (\Throwable) {
                    return [];
                }
            });

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $branchCode = $this->firstFilled($row, ['branchCode', 'branch_code', 'code']);
                $branchName = $this->firstFilled($row, ['branchName', 'branch_name', 'name']);
                if ($branchCode !== '') {
                    $options[$comcode.'|'.$branchCode] = $branchName !== '' ? "{$comcode} - {$branchName} ({$branchCode})" : "{$comcode} - {$branchCode}";
                }
            }
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /** @return array<string, string> */
    public function paymentMethodOptions(array $comcodes, array $branchCodes): array
    {
        return $this->catalogOptions($comcodes, $branchCodes, '/corev1/payment-method', [
            'id' => ['paymentMethodName', 'paymentMethodCode', 'paymentMethodID'],
            'label' => ['paymentMethodName', 'paymentMethodCode'],
        ]);
    }

    /** @return array<string, string> */
    public function selfOrderPaymentMethodOptions(array $comcodes, array $branchCodes): array
    {
        return $this->catalogOptions($comcodes, $branchCodes, '/corev1/self-order/payment-method', [
            'id' => ['paymentMethodCode', 'paymentMethodName', 'paymentMethodID'],
            'label' => ['paymentMethodName', 'paymentMethodCode'],
        ]);
    }

    /** @return array<string, string> */
    public function menuCategoryOptions(array $pairs): array
    {
        $options = [];

        foreach ($pairs as $pair) {
            $categories = $this->menuCategories((string) $pair['comcode'], (string) $pair['branchCode']);
            foreach ($categories as $category) {
                $id = (int) ($category['menuCategoryID'] ?? 0);
                $name = trim((string) ($category['menuCategoryName'] ?? ''));
                if ($id < 1 || $name === '') {
                    continue;
                }

                $options[$pair['comcode'].'|'.$pair['branchCode'].'|'.$id] = $this->optionLabel($pair, $name, $id);
            }
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /** @return array<string, string> */
    public function menuCategoryDetailOptions(array $pairs): array
    {
        $options = [];

        foreach ($pairs as $pair) {
            $categories = $this->menuCategories((string) $pair['comcode'], (string) $pair['branchCode']);
            foreach ($categories as $category) {
                foreach ($category['menuCategoryDetails'] ?? [] as $detail) {
                    if (! is_array($detail)) {
                        continue;
                    }

                    $id = (int) ($detail['menuCategoryDetailID'] ?? 0);
                    $name = trim((string) ($detail['menuCategoryDetailName'] ?? ''));
                    if ($id < 1 || $name === '') {
                        continue;
                    }

                    $categoryName = trim((string) ($category['menuCategoryName'] ?? ''));
                    $label = $categoryName !== '' ? "{$categoryName} / {$name}" : $name;
                    $options[$pair['comcode'].'|'.$pair['branchCode'].'|'.$id] = $this->optionLabel($pair, $label, $id);
                }
            }
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /** @return array<string, string> */
    public function menuOptions(array $pairs): array
    {
        $options = [];

        foreach ($pairs as $pair) {
            $menus = $this->pagedCatalog((string) $pair['comcode'], (string) $pair['branchCode'], '/corev1/master/get-menu', ['flagActive' => 1]);
            foreach ($menus as $menu) {
                if (! is_array($menu)) {
                    continue;
                }

                $id = (int) ($menu['menuID'] ?? 0);
                $name = trim((string) ($menu['menuName'] ?? ''));
                if ($id < 1 || $name === '') {
                    continue;
                }

                $code = trim((string) ($menu['menuCode'] ?? ''));
                $label = $code !== '' ? "{$name} ({$code})" : $name;
                $options[$pair['comcode'].'|'.$pair['branchCode'].'|'.$id] = $this->optionLabel($pair, $label, $id);
            }
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /** @return array{promotionID:int|null,message:string} */
    public function createFreeItem(string $comcode, array $payload): array
    {
        $token = trim((string) config("esb.tokens.{$comcode}", ''));
        if ($token === '') {
            throw new RuntimeException("Token ESB {$comcode} belum dikonfigurasi.");
        }

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->connectTimeout(10)
            ->timeout((int) config('esb.core.timeout', 60))
            ->post($this->baseUrl().'/corev1/promotion/', $payload);

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response, "membuat promotion free item {$comcode}"));
        }

        $body = $response->json();
        if (! is_array($body) || (int) ($body['code'] ?? $response->status()) >= 400) {
            throw new RuntimeException($this->errorMessage($response, "membuat promotion free item {$comcode}"));
        }

        return [
            'promotionID' => filled(data_get($body, 'data.promotionID')) ? (int) data_get($body, 'data.promotionID') : null,
            'message' => (string) ($body['message'] ?? 'Save Data Successfully'),
        ];
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('esb.base_url'), '/');
    }

    private function errorMessage(Response $response, string $action): string
    {
        $body = $response->json();
        $message = is_array($body)
            ? (string) (($body['message'] ?? data_get($body, 'error.message') ?? collect($body['errors'] ?? [])->pluck('message')->filter()->implode('; ')) ?: '')
            : '';

        return 'Gagal '.$action.($message !== '' ? ': '.$message : ' (HTTP '.$response->status().').');
    }

    /** @return array<string, string> */
    private function catalogOptions(array $comcodes, array $branchCodes, string $path, array $keys): array
    {
        $options = [];

        foreach ($comcodes as $comcode) {
            $token = trim((string) config("esb.tokens.{$comcode}", ''));
            if ($token === '') {
                continue;
            }

            foreach ($branchCodes as $branchCode) {
                $cacheKey = 'esb.promotion.catalog.'.md5($path.'|'.$comcode.'|'.$branchCode);
                $rows = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($branchCode, $path, $token): array {
                    try {
                        $response = Http::acceptJson()
                            ->asJson()
                            ->withToken($token)
                            ->connectTimeout(10)
                            ->timeout((int) config('esb.core.timeout', 60))
                            ->get($this->baseUrl().$path, ['branchCode' => $branchCode]);

                        if ($response->failed()) {
                            return [];
                        }

                        $body = $response->json();
                        if (! is_array($body)) {
                            return [];
                        }

                        $data = data_get($body, 'data', data_get($body, 'result.data', data_get($body, 'result', [])));

                        return is_array($data) ? $data : [];
                    } catch (\Throwable) {
                        return [];
                    }
                });

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $id = $this->firstFilled($row, $keys['id']);
                    $label = $this->firstFilled($row, $keys['label']);
                    if ($id !== '' && $label !== '') {
                        $options[$id] = $label;
                    }
                }
            }
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /** @return list<array<string, mixed>> */
    private function menuCategories(string $comcode, string $branchCode): array
    {
        return $this->pagedCatalog($comcode, $branchCode, '/corev1/master/get-menu-category');
    }

    /** @return list<array<string, mixed>> */
    private function pagedCatalog(string $comcode, string $branchCode, string $path, array $params = []): array
    {
        $token = trim((string) config("esb.tokens.{$comcode}", ''));
        if ($token === '') {
            return [];
        }

        $cacheKey = 'esb.promotion.paged_catalog.'.md5($path.'|'.$comcode.'|'.$branchCode.'|'.json_encode($params));

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($branchCode, $params, $path, $token): array {
            $rows = [];
            $page = 1;

            do {
                $hasNext = false;

                try {
                    $response = Http::acceptJson()
                        ->asJson()
                        ->withToken($token)
                        ->connectTimeout(10)
                        ->timeout((int) config('esb.core.timeout', 60))
                        ->get($this->baseUrl().$path, ['page' => $page, 'branchCode' => $branchCode, 'Boolean' => 1] + $params);

                    if ($response->failed()) {
                        break;
                    }

                    $body = $response->json();
                    if (! is_array($body)) {
                        break;
                    }

                    $result = is_array($body['result'] ?? null) ? $body['result'] : [];
                    $data = is_array($result['data'] ?? null) ? $result['data'] : [];
                    array_push($rows, ...$data);

                    $limit = max(1, (int) ($result['limit'] ?? count($data) ?: 10));
                    $count = (int) ($result['count'] ?? count($rows));
                    $hasNext = filled($body['next'] ?? null) || ($page * $limit) < $count;
                    $page++;
                } catch (\Throwable) {
                    break;
                }
            } while ($hasNext && $page <= 100);

            return $rows;
        });
    }

    private function optionLabel(array $pair, string $label, int $id): string
    {
        $branchName = trim((string) ($pair['branchName'] ?? ''));
        $branch = $branchName !== '' ? $branchName : (string) $pair['branchCode'];

        return "{$pair['comcode']} - {$branch} - {$label} (#{$id})";
    }

    private function firstFilled(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) data_get($row, $key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
