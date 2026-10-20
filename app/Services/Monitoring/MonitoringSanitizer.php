<?php

namespace App\Services\Monitoring;

/**
 * Sanitizes monitoring data before it is persisted (docs/system-monitoring-prd.md §20).
 *
 * Domain payloads are reduced through a per-domain allowlist first, so unknown fields are dropped
 * instead of stored. Every surviving value then passes global deny rules (secret-like keys,
 * binary/base64 blobs, signed URL queries, e-mail addresses, phone numbers) and size limits.
 * Output is deterministic for identical input.
 */
class MonitoringSanitizer
{
    public const REDACTED = '[REDACTED]';

    private const MAX_DEPTH = 5;

    private const MAX_STRING = 300;

    private const MAX_ITEMS = 25;

    private const SECRET_KEY_PATTERN = '/pass(word|wd)?|secret|token|authori[sz]ation|cookie|session|api[_-]?key|credential|signature|private[_-]?key|bearer|x-amz|(^|[_-])(otp|pin)$/i';

    /**
     * Dot-path allowlists; `*` matches every element of a list.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWLISTS = [
        'master_product' => [
            'productCode', 'productName', 'categoryID', 'subCategoryID', 'flagActive', 'isTemp', 'productID',
            'productDetails.*.productDetailID', 'productDetails.*.uomID', 'productDetails.*.qty',
            'productDetails.*.sku', 'productDetails.*.isBase', 'productDetails.*.isStock', 'productDetails.*.flagActive',
        ],
        'bom' => [
            'bomID', 'bomCode', 'bomName', 'bomTypeID', 'productDetailID', 'accessType', 'editedDate',
            'bomDetails.*.ID', 'bomDetails.*.productDetailID', 'bomDetails.*.qty', 'bomDetails.*.yieldPercent', 'bomDetails.*.tolerancePercent',
        ],
        'item_journal' => [
            'itemJournalDate', 'branchID', 'locationID', 'requestTemplateID',
            'itemJournalDetails.*.productDetailID', 'itemJournalDetails.*.purposeID', 'itemJournalDetails.*.qty',
            'files.*.name', 'files.*.mime', 'files.*.size',
        ],
        'goods_receipt' => [
            'goodsReceiptDate', 'branchID', 'locationID', 'deliveryNum', 'deliveryDate',
            'goodsReceiptDetail.*.purchaseDetailID', 'goodsReceiptDetail.*.productDetailID', 'goodsReceiptDetail.*.qty',
        ],
        'promotion' => [
            'promotionMasterCode', 'promotionType', 'branchCode', 'startDate', 'endDate', 'promotionDaysID',
            'allCategories', 'applyTo', 'applyToApplicationID', 'maxUsage', 'maxUsageTotal',
            'menuCategoryID', 'menuCategoryDetailID', 'menuID',
        ],
        'esb_response' => [
            'status', 'code', 'message',
            'errors.*.message', 'errors.*.field',
            'result.productID', 'result.isTemp', 'result.bomID', 'result.itemJournalNum', 'result.goodsReceiptNum',
            'data.promotionID',
        ],
    ];

    /**
     * Reduce a domain payload to its allowlisted summary, then sanitize the remaining values.
     *
     * @return array<string, mixed>
     */
    public function summarize(string $domain, mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $allowed = [];
        foreach (self::ALLOWLISTS[$domain] ?? [] as $path) {
            $this->copyPath($payload, $allowed, explode('.', $path));
        }

        $sanitized = $this->sanitize($allowed);

        return is_array($sanitized) ? $sanitized : [];
    }

    /**
     * Apply global deny rules and size limits recursively to an arbitrary value.
     */
    public function sanitize(mixed $value, int $depth = 0, ?string $key = null): mixed
    {
        if ($key !== null && preg_match(self::SECRET_KEY_PATTERN, $key) === 1) {
            return self::REDACTED;
        }

        if (is_array($value)) {
            if ($depth >= self::MAX_DEPTH) {
                return '[MAX DEPTH]';
            }

            $result = [];
            $count = 0;
            foreach ($value as $childKey => $childValue) {
                if ($count >= self::MAX_ITEMS) {
                    $result['_truncated'] = count($value) - self::MAX_ITEMS;
                    break;
                }

                $result[$childKey] = $this->sanitize($childValue, $depth + 1, is_string($childKey) ? $childKey : null);
                $count++;
            }

            return $result;
        }

        if (is_string($value)) {
            return $this->sanitizeString($value);
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }

        return '['.get_debug_type($value).']';
    }

    /**
     * Sanitize free text such as exception or ESB messages.
     */
    public function message(?string $message, int $limit = 500): ?string
    {
        if ($message === null || trim($message) === '') {
            return null;
        }

        $message = preg_replace('/Bearer\s+[A-Za-z0-9._\-~+\/]+=*/i', 'Bearer '.self::REDACTED, $message) ?? '';
        $message = preg_replace('/\b(password|passwd|secret|token|api[_-]?key|authorization)\b(\s*[:=]\s*)("[^"]*"|\'[^\']*\'|\S+)/i', '$1$2'.self::REDACTED, $message) ?? '';
        $message = preg_replace_callback('#\bhttps?://[^\s"\'<>]+#i', fn (array $match): string => $this->url($match[0]), $message) ?? '';
        $message = $this->maskPersonalData($message);
        $message = preg_replace('/[A-Za-z0-9+\/]{120,}={0,2}/', '[BINARY]', $message) ?? '';
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');

        return mb_strimwidth($message, 0, $limit, '…');
    }

    /**
     * Keep scheme, host, and path only; signed/query parameters and credentials are dropped.
     */
    public function url(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['host'])) {
            return '[URL]';
        }

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '');
    }

    private function sanitizeString(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x08\x0E-\x1F]/', $value) === 1) {
            return '[BINARY '.strlen($value).' bytes]';
        }

        if (str_starts_with($value, 'data:') || (strlen($value) >= 120 && preg_match('/^[A-Za-z0-9+\/\r\n]+={0,2}$/', $value) === 1)) {
            return '[BINARY '.strlen($value).' bytes]';
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            return $this->url($value);
        }

        return mb_strimwidth($this->maskPersonalData($value), 0, self::MAX_STRING, '…');
    }

    private function maskPersonalData(string $value): string
    {
        $value = preg_replace_callback(
            '/\b([A-Za-z0-9._%+-])[A-Za-z0-9._%+-]*@([A-Za-z0-9.-]+\.[A-Za-z]{2,})\b/',
            fn (array $match): string => $match[1].'***@'.$match[2],
            $value,
        ) ?? '';

        return preg_replace_callback(
            '/(?<!\d)(\+?62|0)8\d{7,11}(?!\d)/',
            fn (array $match): string => substr($match[0], 0, 4).'****'.substr($match[0], -2),
            $value,
        ) ?? '';
    }

    /**
     * @param  array<mixed>  $source
     * @param  array<mixed>  $target
     * @param  list<string>  $segments
     */
    private function copyPath(array $source, array &$target, array $segments): void
    {
        $segment = array_shift($segments);
        if ($segment === null) {
            return;
        }

        if ($segment === '*') {
            if (! array_is_list($source)) {
                return;
            }

            foreach (array_slice($source, 0, self::MAX_ITEMS) as $index => $item) {
                if ($segments === []) {
                    $target[$index] = $item;

                    continue;
                }

                if (is_array($item)) {
                    $target[$index] ??= [];
                    $this->copyPath($item, $target[$index], $segments);
                }
            }

            return;
        }

        if (! array_key_exists($segment, $source)) {
            return;
        }

        if ($segments === []) {
            $target[$segment] = $source[$segment];

            return;
        }

        if (is_array($source[$segment])) {
            $target[$segment] ??= [];
            $this->copyPath($source[$segment], $target[$segment], $segments);
        }
    }
}
