<?php

namespace App\Services\Purchasing\VendorCompliance;

use App\Enums\DeliveryAssessmentStatus;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Compares the physical receipt date with the PO `requiredDate` in calendar days, as local dates in
 * Asia/Jakarta (PRD §11.4-7, §26.1 no. 1). Pure: no database or network access. A required date that is
 * missing or cannot be read safely is never guessed and yields `not_assessable`.
 */
class DeliveryDelayCalculator
{
    public const TIMEZONE = 'Asia/Jakarta';

    /**
     * @return array{status: DeliveryAssessmentStatus, requiredDate: ?string, delayDays: ?int, note: ?string}
     */
    public function assess(mixed $requiredDate, string|DateTimeInterface $receiptDate): array
    {
        $required = $this->localDate($requiredDate);
        if ($required === null) {
            return [
                'status' => DeliveryAssessmentStatus::NotAssessable,
                'requiredDate' => null,
                'delayDays' => null,
                'note' => blank($requiredDate) ? 'Tanggal dibutuhkan PO kosong.' : 'Tanggal dibutuhkan PO tidak valid.',
            ];
        }

        $received = $this->localDate($receiptDate);
        if ($received === null) {
            return ['status' => DeliveryAssessmentStatus::NotAssessable, 'requiredDate' => $required->toDateString(), 'delayDays' => null, 'note' => 'Tanggal penerimaan tidak valid.'];
        }

        $delay = $received->greaterThan($required) ? (int) $required->diffInDays($received) : 0;

        return [
            'status' => $delay > 0 ? DeliveryAssessmentStatus::Late : DeliveryAssessmentStatus::OnTime,
            'requiredDate' => $required->toDateString(),
            'delayDays' => $delay,
            'note' => null,
        ];
    }

    /**
     * The local calendar date of a `Y-m-d` value or an ISO datetime (converted to Asia/Jakarta), or null.
     * Impossible dates such as 2026-02-30 and placeholder years before 2000 are rejected, not rolled over.
     */
    public function localDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone(self::TIMEZONE)->startOfDay();
        }

        if (! is_string($value) || ! preg_match('/^\s*(\d{4})-(\d{2})-(\d{2})(.*)$/', $value, $parts)) {
            return null;
        }

        [, $year, $month, $day, $rest] = $parts;
        if ((int) $year < 2000 || ! checkdate((int) $month, (int) $day, (int) $year)) {
            return null;
        }

        try {
            $rest = trim($rest);
            $date = $rest === ''
                ? CarbonImmutable::createFromFormat('!Y-m-d', "{$year}-{$month}-{$day}", self::TIMEZONE)
                : CarbonImmutable::parse(trim($value))->setTimezone(self::TIMEZONE);
        } catch (Throwable) {
            return null;
        }

        return $date instanceof CarbonImmutable ? $date->startOfDay() : null;
    }
}
