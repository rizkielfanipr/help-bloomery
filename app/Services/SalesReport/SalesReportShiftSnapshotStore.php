<?php

namespace App\Services\SalesReport;

use App\Models\User;

/**
 * Keeps the ESB transactions and payment methods fetched for a shift on the server (the user's
 * session) instead of in public Livewire state, so they are neither sent to the browser nor
 * trusted from it (docs/products/finance/sales-report-compliment-void-remediation/prd.md §9.2, prompt rule 19).
 * Verified Sales Numbers and Payment Methods are resolved against this snapshot only.
 */
class SalesReportShiftSnapshotStore
{
    private const SESSION_KEY = 'sales_report_shift_snapshots';

    private const SEARCH_LIMIT = 20;

    /**
     * @param  list<array<string, mixed>>  $transactions
     * @param  list<string>  $paymentMethods
     */
    public function put(User $user, string $reportDate, int $shiftNumber, array $transactions, array $paymentMethods): void
    {
        session()->put($this->key($user, $reportDate, $shiftNumber), [
            'transactions' => array_values($transactions),
            'payment_methods' => array_values(array_unique($paymentMethods)),
        ]);
    }

    /**
     * @return array{transactions: list<array<string, mixed>>, payment_methods: list<string>}|null
     */
    public function get(User $user, string $reportDate, int $shiftNumber): ?array
    {
        $snapshot = session()->get($this->key($user, $reportDate, $shiftNumber));

        return is_array($snapshot) ? $snapshot : null;
    }

    public function forget(User $user, string $reportDate, int $shiftNumber): void
    {
        session()->forget($this->key($user, $reportDate, $shiftNumber));
    }

    /**
     * Server-side Sales Number search for the picker; only a page of matches leaves the server.
     *
     * @return list<array{sales_number: string, sales_date_out: string, payment_total: float}>
     */
    public function search(User $user, string $reportDate, int $shiftNumber, string $term): array
    {
        $term = mb_strtolower(trim($term));

        return collect($this->get($user, $reportDate, $shiftNumber)['transactions'] ?? [])
            ->filter(fn (array $transaction): bool => $term === '' || str_contains(mb_strtolower((string) $transaction['sales_num']), $term))
            ->sortBy('sales_date_out')
            ->take(self::SEARCH_LIMIT)
            ->map(fn (array $transaction): array => [
                'sales_number' => (string) $transaction['sales_num'],
                'sales_date_out' => (string) $transaction['sales_date_out'],
                'payment_total' => (float) ($transaction['payment_total'] ?? 0),
            ])
            ->values()
            ->all();
    }

    private function key(User $user, string $reportDate, int $shiftNumber): string
    {
        return self::SESSION_KEY.'.'.$user->branch_id.'_'.$reportDate.'_'.$shiftNumber;
    }
}
