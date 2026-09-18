<?php

namespace App\Services\Esb;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class EsbItemJournalService
{
    /** @return array<int, array<string, mixed>> */
    public function branches(string $companyCode): array
    {
        return Cache::remember("esb_item_journal.branches.{$companyCode}", now()->addMinutes(30), function () use ($companyCode): array {
            $result = $this->successfulResult($this->request($companyCode, 'get', '/branch'), 'mengambil daftar cabang');

            return array_is_list($result) ? $result : [];
        });
    }

    /** @return array<string, mixed> */
    public function branchByCode(string $companyCode, string $branchCode): array
    {
        $branch = collect($this->branches($companyCode))->first(
            fn (array $branch): bool => strcasecmp(trim((string) ($branch['branchCode'] ?? '')), trim($branchCode)) === 0
        );

        if (! is_array($branch)) {
            throw new RuntimeException("ESB Branch Code {$branchCode} tidak ditemukan pada company {$companyCode}.");
        }

        return $branch;
    }

    /** @return array<int, array<string, mixed>> */
    public function locations(string $companyCode, int $branchId): array
    {
        $result = $this->successfulResult($this->request($companyCode, 'get', '/location', ['branchID' => $branchId]), 'mengambil daftar lokasi');

        return array_is_list($result) ? $result : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function purposes(string $companyCode): array
    {
        return Cache::remember("esb_item_journal.purposes.{$companyCode}", now()->addMinutes(30), function () use ($companyCode): array {
            $purposes = [];
            for ($page = 1; $page <= 100; $page++) {
                $result = $this->successfulResult($this->request($companyCode, 'get', '/purpose', [
                    'page' => $page,
                    'limit' => 100,
                    'flagActive' => 1,
                    'sort' => 'purposeName',
                ]), 'mengambil daftar purpose');
                if (! is_array($result['data'] ?? null)) {
                    throw new RuntimeException('Format respons Purpose tidak valid.');
                }
                array_push($purposes, ...$result['data']);
                $hasNext = filled($result['next'] ?? null) || $page * 100 < (int) ($result['count'] ?? 0);
                if (! $hasNext) {
                    break;
                }
                if ($result['data'] === [] || $page === 100) {
                    throw new RuntimeException('Pagination Purpose tidak lengkap.');
                }
            }

            return collect($purposes)->filter(function (array $purpose): bool {
                $appliedTo = collect($purpose['purposeAppliedTo'] ?? [])->map(fn ($value): string => strtoupper((string) $value));

                return (bool) ($purpose['flagActive'] ?? false)
                    && ($appliedTo->isEmpty() || $appliedTo->contains(fn (string $value): bool => str_contains($value, 'ITEM JOURNAL')));
            })->values()->all();
        });
    }

    /** @return array{page:int,limit:int,count:int,data:array<int, mixed>,prev:?string,next:?string} */
    public function products(string $companyCode, array $filters = []): array
    {
        $result = $this->successfulResult($this->request($companyCode, 'get', '/product/list', array_filter([
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'limit' => min(100, max(1, (int) ($filters['limit'] ?? 20))),
            'productName' => $filters['productName'] ?? null,
            'productCode' => $filters['productCode'] ?? null,
            'flagActive' => 1,
        ], fn ($value) => $value !== null && $value !== '')), 'mengambil daftar produk');

        if (! is_array($result['data'] ?? null)) {
            throw new RuntimeException('Format respons Master Product tidak valid.');
        }

        return [
            'page' => (int) ($result['page'] ?? 1),
            'limit' => (int) ($result['limit'] ?? 20),
            'count' => (int) ($result['count'] ?? 0),
            'data' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'prev' => filled($result['prev'] ?? null) ? (string) $result['prev'] : null,
            'next' => filled($result['next'] ?? null) ? (string) $result['next'] : null,
        ];
    }

    /** @return array{itemJournalNum:string,response:array<string,mixed>} */
    public function create(string $companyCode, array $payload): array
    {
        $response = $this->request($companyCode, 'post', '/inventory/item-journal', $payload);
        $result = $this->successfulResult($response, 'membuat Item Journal');
        $number = (string) ($result['itemJournalNum'] ?? '');
        if ($number === '') {
            throw new RuntimeException('ESB tidak mengembalikan nomor Item Journal.');
        }

        return ['itemJournalNum' => $number, 'response' => (array) $response->json()];
    }

    /** @param array<int, array{name:string,contents:string,mime:?string}> $files */
    public function uploadAttachments(string $companyCode, string $journalNumber, array $files): array
    {
        $response = app(EsbCoreClient::class)->upload($companyCode, '/inventory/item-journal/'.rawurlencode($journalNumber).'/attachment', $files);

        return $this->successfulResult($response, 'mengunggah attachment Item Journal')['urls'] ?? [];
    }

    public function deleteAttachments(string $companyCode, string $journalNumber): void
    {
        $this->successfulResult($this->request($companyCode, 'delete', '/inventory/item-journal/'.rawurlencode($journalNumber).'/attachment'), 'menghapus attachment Item Journal');
    }

    protected function request(string $companyCode, string $method, string $path, array $data = [], bool $refreshToken = true): Response
    {
        return app(EsbCoreClient::class)->request($companyCode, $method, $path, $data, $refreshToken);
    }

    protected function successfulResult(Response $response, string $action): array
    {
        return app(EsbCoreClient::class)->successfulResult($response, $action);
    }
}
