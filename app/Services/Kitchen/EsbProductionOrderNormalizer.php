<?php

namespace App\Services\Kitchen;

use App\Exceptions\Kitchen\EsbProductionOrderException;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Maps ESB Production Order rows to the snapshot shape (docs/products/kitchen/production-schedule/prd.md §27.2,
 * §29). Field names were verified against a live BLSS response on 27 Oct 2026 (structure only); every
 * name lives in the constants below, so a contract change only touches this class and its fixtures.
 *
 * Quantities: a detail's UOM is a recipe unit such as `Resep@210GR`; `qty` is the content of that
 * unit (210) and `productionOrderQty` is the number of units to produce — the planned quantity. A
 * material's `totalQty` is its BOM quantity multiplied by that production quantity.
 *
 * A row without an order number, a valid production date, or a numeric Branch ID is malformed and
 * rejected as a whole — a partial row is never stored. Quantities stay decimal strings.
 */
class EsbProductionOrderNormalizer
{
    /** @var array<string, string> snapshot column => ESB field */
    private const HEADER_FIELDS = [
        'production_order_num' => 'productionOrderNum',
        'production_order_date' => 'productionOrderDate',
        'esb_branch_id' => 'branchID',
        'esb_branch_name' => 'branchName',
        'bom_type_id' => 'bomTypeID',
        'bom_type_name' => 'bomTypeName',
        'status_id' => 'statusID',
        'status_name' => 'statusName',
        'additional_info' => 'additionalInfo',
        'created_by_name' => 'createdBy',
        'esb_created_at' => 'createdDate',
        'edited_by_name' => 'editedBy',
        'esb_edited_at' => 'editedDate',
        'authorized_by_name' => 'authorizedBy',
        'esb_authorized_at' => 'authorizedDate',
    ];

    /** @var array<string, string> */
    private const DETAIL_FIELDS = [
        'external_detail_id' => 'ID',
        'bom_id' => 'bomID',
        'bom_name' => 'bomName',
        'bom_type_name' => 'bomTypeName',
        'product_detail_id' => 'productDetailID',
        'product_code' => 'productCode',
        'product_name' => 'productName',
        'quantity' => 'productionOrderQty',
        'uom_name' => 'uomName',
        'notes' => 'notes',
        'is_finished' => 'flagFinished',
    ];

    /** @var array<string, string> */
    private const MATERIAL_FIELDS = [
        'product_code' => 'productCode',
        'product_name' => 'productName',
        'quantity' => 'totalQty',
        'uom_name' => 'uomName',
    ];

    /** @var array<string, string> snapshot actor column => ESB person object with `fullName` */
    private const ACTOR_OBJECTS = [
        'created_by_name' => 'creator',
        'edited_by_name' => 'editor',
        'authorized_by_name' => 'authorizer',
    ];

    private const DETAILS_FIELD = 'productionOrderDetails';

    private const MATERIALS_FIELD = 'productionOrderMaterials';

    /** Header fields whose change means the order must be re-read (§30.2). */
    private const HASHED_FIELDS = ['production_order_date', 'esb_branch_id', 'bom_type_id', 'bom_type_name', 'status_id', 'status_name', 'additional_info'];

    /**
     * @return array{production_order_num: string, production_order_date: string, esb_branch_id: int, esb_branch_name: ?string, bom_type_id: ?string, bom_type_name: ?string, status_id: ?string, status_name: ?string, additional_info: ?string, created_by_name: ?string, esb_created_at: ?string, edited_by_name: ?string, esb_edited_at: ?string, authorized_by_name: ?string, esb_authorized_at: ?string, payload_hash: string}
     *
     * @throws EsbProductionOrderException
     */
    public function header(mixed $row): array
    {
        if (! is_array($row)) {
            throw EsbProductionOrderException::malformed('Baris Production Order bukan objek.');
        }

        $number = $this->text($row, self::HEADER_FIELDS['production_order_num'], 100);
        $date = $this->date($row[self::HEADER_FIELDS['production_order_date']] ?? null);
        $branchId = $row[self::HEADER_FIELDS['esb_branch_id']] ?? null;

        if ($number === null || $date === null || ! is_numeric($branchId) || (int) $branchId <= 0) {
            throw EsbProductionOrderException::malformed('Production Order tanpa nomor, tanggal produksi, atau Branch ID ESB yang valid.');
        }

        $header = [
            'production_order_num' => $number,
            'production_order_date' => $date,
            'esb_branch_id' => (int) $branchId,
            'esb_branch_name' => $this->text($row, self::HEADER_FIELDS['esb_branch_name']),
            'bom_type_id' => $this->identifier($row[self::HEADER_FIELDS['bom_type_id']] ?? null),
            'bom_type_name' => $this->text($row, self::HEADER_FIELDS['bom_type_name']),
            'status_id' => $this->identifier($row[self::HEADER_FIELDS['status_id']] ?? null),
            'status_name' => $this->text($row, self::HEADER_FIELDS['status_name']),
            'additional_info' => $this->text($row, self::HEADER_FIELDS['additional_info'], 2000),
            'created_by_name' => $this->text($row, self::HEADER_FIELDS['created_by_name']),
            'esb_created_at' => $this->dateTime($row[self::HEADER_FIELDS['esb_created_at']] ?? null),
            'edited_by_name' => $this->text($row, self::HEADER_FIELDS['edited_by_name']),
            'esb_edited_at' => $this->dateTime($row[self::HEADER_FIELDS['esb_edited_at']] ?? null),
            'authorized_by_name' => $this->text($row, self::HEADER_FIELDS['authorized_by_name']),
            'esb_authorized_at' => $this->dateTime($row[self::HEADER_FIELDS['esb_authorized_at']] ?? null),
        ];

        // The detail endpoint also sends {creator,editor,authorizer}.fullName; prefer it over the username.
        foreach (self::ACTOR_OBJECTS as $column => $field) {
            if (is_array($row[$field] ?? null) && ($fullName = $this->text($row[$field], 'fullName')) !== null) {
                $header[$column] = $fullName;
            }
        }

        $header['payload_hash'] = hash('sha256', json_encode(array_intersect_key($header, array_flip(self::HASHED_FIELDS)), JSON_THROW_ON_ERROR));

        return $header;
    }

    /**
     * The detail endpoint returns the header plus its detail rows.
     *
     * @return array{header: array<string, mixed>, details: list<array{external_detail_id: string, bom_id: ?string, bom_name: ?string, bom_type_name: ?string, product_detail_id: ?string, product_code: ?string, product_name: ?string, quantity: string, uom_name: ?string, notes: ?string, is_finished: ?bool, materials: list<array{product_code: ?string, product_name: ?string, quantity: ?string, uom_name: ?string}>, sequence: int}>}
     *
     * @throws EsbProductionOrderException
     */
    public function order(mixed $result): array
    {
        $header = $this->header($result);
        $rows = $result[self::DETAILS_FIELD] ?? null;

        if (! is_array($rows) || ! array_is_list($rows)) {
            throw EsbProductionOrderException::malformed('Detail Production Order tidak berisi daftar detail.');
        }

        $details = [];
        foreach ($rows as $index => $row) {
            $detail = $this->detail($row, $index + 1);

            if (isset($details[$detail['external_detail_id']])) {
                throw EsbProductionOrderException::malformed('Detail Production Order berisi ID detail ganda.');
            }

            $details[$detail['external_detail_id']] = $detail;
        }

        return ['header' => $header, 'details' => array_values($details)];
    }

    /**
     * @return array{external_detail_id: string, bom_id: ?string, bom_name: ?string, bom_type_name: ?string, product_detail_id: ?string, product_code: ?string, product_name: ?string, quantity: string, uom_name: ?string, notes: ?string, is_finished: ?bool, materials: list<array{product_code: ?string, product_name: ?string, quantity: ?string, uom_name: ?string}>, sequence: int}
     */
    private function detail(mixed $row, int $sequence): array
    {
        if (! is_array($row)) {
            throw EsbProductionOrderException::malformed('Baris detail Production Order bukan objek.');
        }

        $externalId = $this->identifier($row[self::DETAIL_FIELDS['external_detail_id']] ?? null);
        $quantity = $this->quantity($row[self::DETAIL_FIELDS['quantity']] ?? null);

        if ($externalId === null || $quantity === null) {
            throw EsbProductionOrderException::malformed('Detail Production Order tanpa ID detail atau quantity yang valid.');
        }

        $finished = $row[self::DETAIL_FIELDS['is_finished']] ?? null;
        $materials = $row[self::MATERIALS_FIELD] ?? [];

        return [
            'external_detail_id' => $externalId,
            'bom_id' => $this->identifier($row[self::DETAIL_FIELDS['bom_id']] ?? null),
            'bom_name' => $this->text($row, self::DETAIL_FIELDS['bom_name']),
            'bom_type_name' => $this->text($row, self::DETAIL_FIELDS['bom_type_name']),
            'product_detail_id' => $this->identifier($row[self::DETAIL_FIELDS['product_detail_id']] ?? null),
            'product_code' => $this->text($row, self::DETAIL_FIELDS['product_code'], 100),
            'product_name' => $this->text($row, self::DETAIL_FIELDS['product_name']),
            'quantity' => $quantity,
            'uom_name' => $this->text($row, self::DETAIL_FIELDS['uom_name'], 100),
            'notes' => $this->text($row, self::DETAIL_FIELDS['notes'], 2000),
            'is_finished' => is_bool($finished) || in_array($finished, [0, 1, '0', '1'], true) ? (bool) $finished : null,
            'materials' => is_array($materials) && array_is_list($materials)
                ? array_values(array_map(fn (mixed $material): array => $this->material($material), array_filter($materials, 'is_array')))
                : [],
            'sequence' => $sequence,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{product_code: ?string, product_name: ?string, quantity: ?string, uom_name: ?string}
     */
    private function material(array $row): array
    {
        return [
            'product_code' => $this->text($row, self::MATERIAL_FIELDS['product_code'], 100),
            'product_name' => $this->text($row, self::MATERIAL_FIELDS['product_name']),
            'quantity' => $this->quantity($row[self::MATERIAL_FIELDS['quantity']] ?? null),
            'uom_name' => $this->text($row, self::MATERIAL_FIELDS['uom_name'], 100),
        ];
    }

    /** @param array<string, mixed> $row */
    private function text(array $row, string $field, int $limit = 255): ?string
    {
        $value = $row[$field] ?? null;

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    private function identifier(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 100);
    }

    /**
     * Decimal string with at most four fractional digits, never routed through float arithmetic
     * when ESB already sends a string (docs/products/kitchen/production-schedule/prd.md §29.2).
     */
    private function quantity(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            $value = sprintf('%.4F', $value);
        }

        if (! is_string($value) || ! preg_match('/^-?\d{1,14}(\.\d+)?$/', trim($value))) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', trim($value), 2), 2, '');
        $fraction = rtrim(substr($fraction, 0, 4), '0');

        return $fraction === '' ? $whole : $whole.'.'.$fraction;
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));
        } catch (Throwable) {
            return null;
        }

        return $date !== false && $date->format('Y-m-d') === substr($value, 0, 10) ? $date->toDateString() : null;
    }

    private function dateTime(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }
}
