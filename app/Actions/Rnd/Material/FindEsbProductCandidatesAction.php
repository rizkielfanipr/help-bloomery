<?php

namespace App\Actions\Rnd\Material;

use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\EsbMasterProductService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Read-only ESB lookup for IT (§13.2): exact product code first, then exact name as a weaker
 * indicator. It never links or creates anything; the result is shown to IT for a decision.
 */
class FindEsbProductCandidatesAction
{
    public function __construct(private readonly EsbMasterProductService $esb) {}

    /**
     * @return list<array{productID: int, productCode: string, productName: string, match: string}>
     *
     * @throws AuthorizationException|ValidationException
     */
    public function execute(RndProductEsbMaterial $material, User $actor): array
    {
        if (! Gate::forUser($actor)->allows('reviewAsIt', $material)) {
            throw new AuthorizationException('Anda tidak berhak mencari produk ESB untuk review.');
        }

        try {
            $byCode = $this->esb->getProducts(['limit' => 20, 'productCode' => $material->product_code])['data'];
            $byName = $this->esb->getProducts(['limit' => 20, 'productName' => $material->product_name])['data'];
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['esb' => 'Master Product ESB belum dapat dihubungi. Coba lagi beberapa saat lagi.']);
        }

        $normalize = fn (?string $value): string => mb_strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $value)));

        return collect($byCode)->map(fn (array $product): array => $product + ['match' => 'kode'])
            ->merge(collect($byName)->map(fn (array $product): array => $product + ['match' => 'nama']))
            ->filter(fn (array $product): bool => ($product['match'] === 'kode' && mb_strtoupper(trim((string) ($product['productCode'] ?? ''))) === mb_strtoupper($material->product_code))
                || ($product['match'] === 'nama' && $normalize($product['productName'] ?? '') === $normalize($material->product_name)))
            ->unique(fn (array $product): int => (int) ($product['productID'] ?? 0))
            ->map(fn (array $product): array => [
                'productID' => (int) ($product['productID'] ?? 0),
                'productCode' => (string) ($product['productCode'] ?? ''),
                'productName' => (string) ($product['productName'] ?? ''),
                'match' => $product['match'],
            ])
            ->values()
            ->take(10)
            ->all();
    }
}
