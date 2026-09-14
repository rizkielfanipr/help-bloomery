<?php

use App\Services\ItBusinessHoursService;
use Carbon\CarbonImmutable;

it('counts only weekday working seconds', function (string $start, string $end, int $expected) {
    $service = new ItBusinessHoursService;
    $start = CarbonImmutable::parse($start, 'Asia/Jakarta');
    $end = CarbonImmutable::parse($end, 'Asia/Jakarta');

    expect($service->secondsBetween($start, $end))->toBe($expected)
        ->and($start->timezoneName)->toBe('Asia/Jakarta');
})->with([
    'before opening' => ['2026-09-14 07:00:00', '2026-09-14 09:00:00', 3600],
    'after closing' => ['2026-09-14 16:00:00', '2026-09-14 18:00:00', 3600],
    'overnight' => ['2026-09-14 16:00:00', '2026-09-15 09:00:00', 7200],
    'weekend gap' => ['2026-09-11 16:30:00', '2026-09-14 09:30:00', 7200],
    'weekend submission' => ['2026-09-12 10:00:00', '2026-09-14 08:30:00', 1800],
    'entirely weekend' => ['2026-09-12 10:00:00', '2026-09-13 15:00:00', 0],
    'outside working hours' => ['2026-09-14 18:00:00', '2026-09-15 07:00:00', 0],
    'full working day' => ['2026-09-14 08:00:00', '2026-09-14 17:00:00', 32400],
    'opening boundary' => ['2026-09-14 07:00:00', '2026-09-14 08:00:00', 0],
    'closing boundary' => ['2026-09-14 17:00:00', '2026-09-15 08:00:00', 0],
    'same timestamp' => ['2026-09-14 10:00:00', '2026-09-14 10:00:00', 0],
    'seconds precision' => ['2026-09-14 16:59:45', '2026-09-15 08:00:20', 35],
    'lunch is counted' => ['2026-09-14 12:00:00', '2026-09-14 13:00:00', 3600],
    'multiple weeks' => ['2026-09-07 08:00:00', '2026-09-21 08:00:00', 324000],
]);

it('uses WIB regardless of input timezone and does not mutate input timestamps', function () {
    $service = new ItBusinessHoursService;
    $start = CarbonImmutable::parse('2026-09-11 09:30:00', 'UTC');
    $end = CarbonImmutable::parse('2026-09-14 02:30:00', 'UTC');

    expect($service->secondsBetween($start, $end))->toBe(7200)
        ->and($start->format('H:i'))->toBe('09:30')
        ->and($start->timezoneName)->toBe('UTC')
        ->and($service->breakdown($start, $end))->toBe([
            ['date' => '11/09/2026', 'start' => '16:30:00', 'end' => '17:00:00', 'seconds' => 1800],
            ['date' => '14/09/2026', 'start' => '08:00:00', 'end' => '09:30:00', 'seconds' => 5400],
        ]);
});

it('excludes missing and reversed intervals instead of counting them as zero', function () {
    $service = new ItBusinessHoursService;
    $start = CarbonImmutable::parse('2026-09-14 10:00:00', 'Asia/Jakarta');

    expect($service->secondsBetween(null, $start))->toBeNull()
        ->and($service->secondsBetween($start, null))->toBeNull()
        ->and($service->secondsBetween($start, $start->subHour()))->toBeNull();
});

it('formats durations without rounding before aggregation', function () {
    $service = new ItBusinessHoursService;

    expect($service->formatDuration(null))->toBe('Tidak tersedia')
        ->and($service->formatDuration(0))->toBe('0 jam 0 menit')
        ->and($service->formatDuration(7200))->toBe('2 jam 0 menit')
        ->and($service->formatDuration(35.5))->toBe('0 jam 0 menit 36 detik')
        ->and($service->formatDuration(32400))->toBe('9 jam 0 menit');
});
