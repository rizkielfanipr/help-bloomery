<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class EsbItemJournalService
{
    public function __construct(protected readonly EsbCoreClient $client) {}

    /** @return array<int, array<string, mixed>> */
    public function branches(string $companyCode): array
    {
        return Cache::remember("esb_item_journal.branches.{$companyCode}", now()->addMinutes(30), function () use ($companyCode): array {
            $result = $this->requestResult($companyCode, 'get', '/branch', [], 'mengambil daftar cabang');

            return array_is_list($result) ? $result : [];
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function refreshBranches(string $companyCode): array
    {
        Cache::forget("esb_item_journal.branches.{$companyCode}");

        return $this->branches($companyCode);
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
        $result = $this->requestResult($companyCode, 'get', '/location', ['branchID' => $branchId], 'mengambil daftar lokasi');

        return array_is_list($result) ? $result : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function purposes(string $companyCode): array
    {
        return Cache::remember("esb_item_journal.purposes.{$companyCode}", now()->addMinutes(30), function () use ($companyCode): array {
            $purposes = [];
            $page = 1;

            do {
                $result = $this->requestResult($companyCode, 'get', '/purpose', [
                    'page' => $page,
                    'limit' => 100,
                    'flagActive' => 1,
                    'sort' => 'purposeName',
                ], 'mengambil daftar purpose');
                array_push($purposes, ...(is_array($result['data'] ?? null) ? $result['data'] : []));
                $page++;
            } while (filled($result['next'] ?? null) && $page <= 100);

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
        $result = $this->requestResult($companyCode, 'get', '/product/list', array_filter([
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'limit' => min(100, max(1, (int) ($filters['limit'] ?? 20))),
            'productName' => $filters['productName'] ?? null,
            'productCode' => $filters['productCode'] ?? null,
            'flagActive' => 1,
        ], fn ($value) => $value !== null && $value !== ''), 'mengambil daftar produk');

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
        $result = $this->client->successfulResult($response, 'membuat Item Journal', $companyCode, '/inventory/item-journal');
        $number = (string) ($result['itemJournalNum'] ?? '');
        if ($number === '') {
            throw new RuntimeException('ESB tidak mengembalikan nomor Item Journal.');
        }

        return ['itemJournalNum' => $number, 'response' => (array) $response->json()];
    }

    /** @param array<int, array{name:string,contents:string,mime:?string}> $files */
    public function uploadAttachments(string $companyCode, string $journalNumber, array $files): array
    {
        $path = '/inventory/item-journal/'.rawurlencode($journalNumber).'/attachment';
        $response = $this->client->multipart($companyCode, 'patch', $path, $files);

        return $this->client->successfulResult($response, 'mengunggah attachment Item Journal', $companyCode, $path)['urls'] ?? [];
    }

    public function deleteAttachments(string $companyCode, string $journalNumber): void
    {
        $path = '/inventory/item-journal/'.rawurlencode($journalNumber).'/attachment';
        $this->requestResult($companyCode, 'delete', $path, [], 'menghapus attachment Item Journal');
    }

    protected function request(string $companyCode, string $method, string $path, array $data = []): Response
    {
        return $this->client->request($companyCode, $method, $path, $data);
    }

    /** @return array<string, mixed> */
    protected function requestResult(string $companyCode, string $method, string $path, array $data, string $action): array
    {
        return $this->client->successfulResult(
            $this->request($companyCode, $method, $path, $data),
            $action,
            $companyCode,
            $path,
        );
    }
}
