<?php

namespace App\Services;

use App\Services\Monitoring\EsbAttempt;
use App\Services\Monitoring\EsbMutation;
use App\Services\Monitoring\RecordsEsbMutations;
use RuntimeException;

class EsbBillOfMaterialService
{
    use RecordsEsbMutations;

    public function __construct(private readonly EsbGlobalCoreClient $client) {}

    /** @return array{page:int,limit:int,count:int,data:array<int,mixed>,prev:?string,next:?string} */
    public function getBillOfMaterials(array $filters = []): array
    {
        $result = $this->client->successfulResult(
            $this->client->request('get', '/product/bom', array_filter([
                'page' => max(1, (int) ($filters['page'] ?? 1)),
                'limit' => min(5000, max(1, (int) ($filters['limit'] ?? 20))),
                'bomID' => $filters['bomID'] ?? null,
                'productName' => $filters['productName'] ?? null,
                'uomName' => $filters['uomName'] ?? null,
                'sort' => $filters['sort'] ?? null,
                'flagActive' => $filters['flagActive'] ?? 1,
            ], fn ($value) => $value !== null && $value !== '')),
            'mengambil daftar Bill of Material',
        );

        return [
            'page' => (int) ($result['page'] ?? 1),
            'limit' => (int) ($result['limit'] ?? 20),
            'count' => (int) ($result['count'] ?? 0),
            'data' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'prev' => ($result['prev'] ?? null) ?: null,
            'next' => ($result['next'] ?? null) ?: null,
        ];
    }

    /** @return array<int, array> */
    public function getAllBillOfMaterials(): array
    {
        $all = [];
        $page = 1;
        do {
            $result = $this->getBillOfMaterials(['page' => $page, 'limit' => 100]);
            array_push($all, ...$result['data']);
            $page++;
            $hasNext = filled($result['next']) || (($result['page'] * $result['limit']) < $result['count']);
        } while ($hasNext && $page <= 100);

        return $all;
    }

    /**
     * Return the preferred BOM code, or the first free "{code}-{n}" variant when an active or
     * inactive ESB BOM already holds it, because ESB rejects a create whose bomCode is taken.
     */
    public function availableBomCode(string $preferredCode): string
    {
        $baseCode = mb_substr(trim($preferredCode), 0, 90);
        $takenCodes = $this->takenBomCodes();
        $candidate = $baseCode;
        $suffix = 2;

        while (isset($takenCodes[mb_strtoupper($candidate)])) {
            $candidate = $baseCode.'-'.$suffix++;
        }

        return $candidate;
    }

    /** @return array<string, true> */
    private function takenBomCodes(): array
    {
        $taken = [];

        foreach ([1, 0] as $flagActive) {
            $page = 1;
            do {
                $result = $this->getBillOfMaterials(['page' => $page, 'limit' => 5000, 'flagActive' => $flagActive]);
                foreach ($result['data'] as $bom) {
                    $code = mb_strtoupper(trim((string) ($bom['bomCode'] ?? '')));
                    if ($code !== '') {
                        $taken[$code] = true;
                    }
                }
                $page++;
                $hasNext = filled($result['next']) || (($result['page'] * $result['limit']) < $result['count']);
            } while ($hasNext && $page <= 20);
        }

        return $taken;
    }

    /** @return array<string, mixed> */
    public function getBillOfMaterial(int $bomId): array
    {
        return $this->client->successfulResult(
            $this->client->request('get', '/product/bom/'.$bomId),
            'mengambil detail Bill of Material',
        );
    }

    public function createAssembly(array $payload): int
    {
        $payload['bomTypeID'] ??= 1;

        return $this->recordEsbMutation(
            new EsbMutation('POST', '/product/bom', 'create_bom', 'bom', $payload, targetKey: 'bom'),
            function (EsbAttempt $attempt) use ($payload): int {
                $result = $this->client->successfulResult(
                    $this->client->request('post', '/product/bom', $payload),
                    'membuat Bill of Material',
                );
                $bomId = (int) ($result['bomID'] ?? 0);
                if ($bomId < 1) {
                    throw new RuntimeException('ESB tidak mengembalikan ID Bill of Material.');
                }
                $attempt->reference($bomId);

                return $bomId;
            },
        );
    }

    public function updateBillOfMaterial(int $bomId, array $payload): void
    {
        $this->recordEsbMutation(
            new EsbMutation('PUT', '/product/bom/{bomID}', 'update_bom', 'bom', $payload, targetKey: 'bom', externalReference: (string) $bomId),
            fn () => $this->client->successfulResult(
                $this->client->request('put', '/product/bom/'.$bomId, $payload),
                'memperbarui Bill of Material',
            ),
        );
    }
}
