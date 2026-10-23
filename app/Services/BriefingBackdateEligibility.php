<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class BriefingBackdateEligibility
{
    public const MAX_MONTHS = 1;

    public function __construct(private readonly BriefingAssignmentService $assignments) {}

    public function earliestDate(?CarbonInterface $referenceDate = null): Carbon
    {
        return $this->referenceDate($referenceDate)->subMonthNoOverflow();
    }

    public function latestDate(?CarbonInterface $referenceDate = null): Carbon
    {
        return $this->referenceDate($referenceDate)->subDay();
    }

    public function isWithinWindow(CarbonInterface $date, ?CarbonInterface $referenceDate = null): bool
    {
        $targetDate = Carbon::parse($date->toDateString(), config('app.timezone'))->startOfDay();

        return $targetDate->betweenIncluded(
            $this->earliestDate($referenceDate),
            $this->latestDate($referenceDate),
        );
    }

    public function denialReason(User $user, CarbonInterface $date): ?string
    {
        if (! $this->isWithinWindow($date)) {
            return 'Tanggal backdate hanya dapat dipilih maksimal satu bulan ke belakang dan tidak boleh termasuk hari ini atau tanggal mendatang.';
        }

        if (! $this->assignments->canSubmit($user, $date)) {
            return 'Anda atau branch Anda tidak terdaftar sebagai peserta Daily Briefing pada tanggal tersebut.';
        }

        return null;
    }

    private function referenceDate(?CarbonInterface $referenceDate): Carbon
    {
        return Carbon::parse(
            ($referenceDate ?? now(config('app.timezone')))->toDateString(),
            config('app.timezone'),
        )->startOfDay();
    }
}
