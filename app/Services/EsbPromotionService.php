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
                    $options[$comcode.'|'.$branchCode] = $branchName !== '' ? "{$branchName} ({$branchCode})" : "{$comcode} - {$branchCode}";
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

    /** @return array<int, string> */
    public function visitPurposeOptions(array $comcodes, array $branchCodes): array
    {
        return collect($this->catalogOptions($comcodes, $branchCodes, '/corev1/visit-purpose', [
            'id' => ['visitPurposeID', 'id'],
            'label' => ['visitPurposeName', 'name'],
        ]))
            ->mapWithKeys(fn (string $label, string $id): array => [(int) $id => $label])
            ->all();
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
