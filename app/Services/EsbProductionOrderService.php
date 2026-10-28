<?php

namespace App\Services;

use App\Enums\EsbProductionOrderErrorKind;
use App\Exceptions\Kitchen\EsbProductionOrderException;
use App\Services\Kitchen\EsbProductionOrderNormalizer;
use App\Services\Monitoring\MonitoringSanitizer;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Read-only Production Order integration (docs/products/kitchen/production-schedule/prd.md §27). Two GET
 * endpoints only; authentication, token cache, the single `401` refresh, and timeouts stay in
 * `EsbCoreClient`, or in `EsbGlobalCoreClient` for companies configured to use the global account. Every failure leaves here as an `EsbProductionOrderException` with a class the
 * queue can act on, and a row that does not belong to the requested Branch, window, or order is
 * treated as malformed instead of being stored.
 *
 * Callers are queued jobs and commands only — never a Page, Blade, or Livewire request.
 */
class EsbProductionOrderService
{
    public const LIST_PATH = '/production/production-order';

    public function __construct(
        private readonly EsbCoreClient $client,
        private readonly EsbGlobalCoreClient $globalClient,
        private readonly EsbProductionOrderNormalizer $normalizer,
        private readonly MonitoringSanitizer $sanitizer,
    ) {}

    /**
     * One page of headers for one ESB Branch and production-date window.
     *
     * @return array{page: int, count: int, has_next: bool, orders: list<array<string, mixed>>}
     *
     * @throws EsbProductionOrderException
     */
    public function listPage(string $companyCode, int $esbBranchId, string $dateFrom, string $dateTo, int $page, int $limit): array
    {
        $result = $this->get($companyCode, self::LIST_PATH, [
            'branchID' => $esbBranchId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'page' => $page,
            'limit' => $limit,
            'sort' => 'productionOrderDate',
        ]);

        // An empty window comes back as `data: null` with `count: 0` (verified 27 Oct 2026).
        $rows = $result['data'] ?? null;
        if ($rows === null && array_key_exists('data', $result) && (int) ($result['count'] ?? 0) === 0) {
            $rows = [];
        }
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw EsbProductionOrderException::malformed('Daftar Production Order tidak berisi data halaman.');
        }

        $orders = [];
        foreach ($rows as $row) {
            $order = $this->normalizer->header($row);

            if ($order['esb_branch_id'] !== $esbBranchId) {
                throw EsbProductionOrderException::malformed('Daftar Production Order mengembalikan Branch ESB lain.');
            }
            if ($order['production_order_date'] < $dateFrom || $order['production_order_date'] > $dateTo) {
                throw EsbProductionOrderException::malformed('Daftar Production Order mengembalikan tanggal di luar rentang.');
            }

            $orders[] = $order;
        }

        return [
            'page' => (int) ($result['page'] ?? $page),
            'count' => is_numeric($result['count'] ?? null) ? (int) $result['count'] : count($orders),
            'has_next' => filled($result['next'] ?? null) && $orders !== [],
            'orders' => $orders,
        ];
    }

    /**
     * Full header, details, and materials of one order.
     *
     * @return array{header: array<string, mixed>, details: list<array<string, mixed>>}
     *
     * @throws EsbProductionOrderException
     */
    public function order(string $companyCode, int $esbBranchId, string $productionOrderNum): array
    {
        $order = $this->normalizer->order(
            $this->get($companyCode, self::LIST_PATH.'/'.rawurlencode($productionOrderNum)),
        );

        if ($order['header']['production_order_num'] !== trim($productionOrderNum) || $order['header']['esb_branch_id'] !== $esbBranchId) {
            throw EsbProductionOrderException::malformed('Detail Production Order tidak cocok dengan nomor atau Branch yang diminta.');
        }

        return $order;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws EsbProductionOrderException
     */
    private function get(string $companyCode, string $path, array $query = []): array
    {
        try {
            $response = $this->usesGlobalCredential($companyCode)
                ? $this->globalClient->request('get', $path, $query)
                : $this->client->request($companyCode, 'get', $path, $query);
        } catch (ConnectionException $exception) {
            throw new EsbProductionOrderException(EsbProductionOrderErrorKind::Retryable, "Gagal menghubungi ESB Core [{$companyCode} {$path}].", previous: $exception);
        } catch (LockTimeoutException $exception) {
            throw new EsbProductionOrderException(EsbProductionOrderErrorKind::Retryable, 'Login ESB sedang diproses request lain.', previous: $exception);
        } catch (RuntimeException $exception) {
            throw $this->transportFailure($exception);
        }

        $status = $response->status();
        if ($status === 401) {
            throw new EsbProductionOrderException(EsbProductionOrderErrorKind::Authentication, "ESB menolak autentikasi Production Order [{$companyCode}].", 401);
        }
        if ($status === 403) {
            throw new EsbProductionOrderException(EsbProductionOrderErrorKind::Authentication, "Credential ESB tidak mempunyai akses Production Order [{$companyCode}].", 403);
        }
        if ($status === 429 || $status >= 500) {
            throw new EsbProductionOrderException(EsbProductionOrderErrorKind::Retryable, "ESB Production Order HTTP {$status} [{$companyCode}].", $status);
        }
        if ($response->failed()) {
            throw new EsbProductionOrderException(EsbProductionOrderErrorKind::Validation, $this->errorMessage($response, "ESB Production Order HTTP {$status} [{$companyCode}]"), $status);
        }

        $payload = $response->json();
        if (! is_array($payload) || ! array_key_exists('status', $payload)) {
            throw EsbProductionOrderException::malformed("Respons Production Order tidak valid [{$companyCode}].");
        }
        if ($payload['status'] !== 'ok') {
            throw new EsbProductionOrderException(EsbProductionOrderErrorKind::Validation, $this->errorMessage($response, "ESB menolak permintaan Production Order [{$companyCode}]"), $status);
        }
        if (! is_array($payload['result'] ?? null)) {
            throw EsbProductionOrderException::malformed("Respons Production Order tanpa result [{$companyCode}].");
        }

        return $payload['result'];
    }

    /**
     * `EsbCoreClient` wraps connection failures and login problems in a RuntimeException; the
     * original connection exception is kept as `previous`.
     */
    private function transportFailure(RuntimeException $exception): EsbProductionOrderException
    {
        $message = (string) $this->sanitizer->message($exception->getMessage(), 300);

        return match (true) {
            $exception->getPrevious() instanceof ConnectionException => new EsbProductionOrderException(EsbProductionOrderErrorKind::Retryable, $message, previous: $exception),
            str_contains($exception->getMessage(), 'belum dikonfigurasi')
                || str_contains($exception->getMessage(), 'belum lengkap')
                || str_contains($exception->getMessage(), 'wajib diisi') => EsbProductionOrderException::configuration($message),
            default => new EsbProductionOrderException(EsbProductionOrderErrorKind::Authentication, $message, previous: $exception),
        };
    }

    /**
     * Companies listed in `kitchen.production_orders.global_credential_companies` are read with the
     * global ESB Core account, which was verified to be that company (prd.md §27.4).
     */
    private function usesGlobalCredential(string $companyCode): bool
    {
        return in_array(mb_strtoupper(trim($companyCode)), (array) config('kitchen.production_orders.global_credential_companies', []), true);
    }

    private function errorMessage(Response $response, string $prefix): string
    {
        $payload = $response->json();
        $messages = collect(is_array($payload) ? ($payload['errors'] ?? []) : [])
            ->map(fn (mixed $error): mixed => is_array($error) ? ($error['message'] ?? null) : null)
            ->filter(fn (mixed $message): bool => is_string($message) && $message !== '');

        return (string) $this->sanitizer->message($prefix.($messages->isNotEmpty() ? ': '.$messages->implode('; ') : '.'), 300);
    }
}
