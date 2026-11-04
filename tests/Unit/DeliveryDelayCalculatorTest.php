<?php

use App\Enums\DeliveryAssessmentStatus;
use App\Services\Purchasing\VendorCompliance\DeliveryDelayCalculator;
use App\Services\Purchasing\VendorCompliance\LateDeliveryScoring;
use Carbon\CarbonImmutable;

it('compares calendar dates in Asia/Jakarta', function (mixed $required, string $received, DeliveryAssessmentStatus $status, ?int $delay, ?string $requiredDate) {
    $result = (new DeliveryDelayCalculator)->assess($required, $received);

    expect($result['status'])->toBe($status)
        ->and($result['delayDays'])->toBe($delay)
        ->and($result['requiredDate'])->toBe($requiredDate);
})->with([
    'received before' => ['2026-11-05', '2026-11-04', DeliveryAssessmentStatus::OnTime, 0, '2026-11-05'],
    'received on the day' => ['2026-11-05', '2026-11-05', DeliveryAssessmentStatus::OnTime, 0, '2026-11-05'],
    'one day late' => ['2026-11-05', '2026-11-06', DeliveryAssessmentStatus::Late, 1, '2026-11-05'],
    'across a leap day' => ['2028-02-28', '2028-03-01', DeliveryAssessmentStatus::Late, 2, '2028-02-28'],
    'leap day itself' => ['2028-02-29', '2028-02-29', DeliveryAssessmentStatus::OnTime, 0, '2028-02-29'],
    'ISO with Jakarta offset' => ['2026-11-05T00:00:00+07:00', '2026-11-05', DeliveryAssessmentStatus::OnTime, 0, '2026-11-05'],
    'UTC evening is the next Jakarta day' => ['2026-11-04T18:00:00Z', '2026-11-05', DeliveryAssessmentStatus::OnTime, 0, '2026-11-05'],
    'time of day is ignored' => ['2026-11-05T23:59:59+07:00', '2026-11-06', DeliveryAssessmentStatus::Late, 1, '2026-11-05'],
    'null' => [null, '2026-11-06', DeliveryAssessmentStatus::NotAssessable, null, null],
    'empty string' => ['', '2026-11-06', DeliveryAssessmentStatus::NotAssessable, null, null],
    'not a date' => ['not-a-date', '2026-11-06', DeliveryAssessmentStatus::NotAssessable, null, null],
    'impossible date' => ['2026-02-30', '2026-03-01', DeliveryAssessmentStatus::NotAssessable, null, null],
    'placeholder year' => ['0001-01-01T00:00:00', '2026-03-01', DeliveryAssessmentStatus::NotAssessable, null, null],
    'array payload' => [['date' => '2026-11-05'], '2026-11-06', DeliveryAssessmentStatus::NotAssessable, null, null],
]);

it('explains why a receipt cannot be assessed without exposing the raw value', function () {
    expect((new DeliveryDelayCalculator)->assess(null, '2026-11-06')['note'])->toBe('Tanggal dibutuhkan PO kosong.')
        ->and((new DeliveryDelayCalculator)->assess('rusak', '2026-11-06')['note'])->toBe('Tanggal dibutuhkan PO tidak valid.');
});

it('accepts date objects', function () {
    $result = (new DeliveryDelayCalculator)->assess(CarbonImmutable::parse('2026-11-01', 'Asia/Jakarta'), CarbonImmutable::parse('2026-11-11', 'Asia/Jakarta'));

    expect($result['delayDays'])->toBe(10);
});

it('scores confirmed delays with the v1 matrix', function (int $days, string $severity, int $points) {
    expect((new LateDeliveryScoring)->score($days))->toBe(['severity' => $severity, 'points' => $points]);
})->with([
    [1, 'low', 5], [2, 'low', 5], [3, 'medium', 10], [7, 'medium', 10], [8, 'high', 20], [30, 'high', 20],
]);
