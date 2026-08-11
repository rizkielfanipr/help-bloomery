<?php

namespace Database\Seeders;

use App\Enums\BriefingReviewStatus;
use App\Models\Branch;
use App\Models\BriefingItem;
use App\Models\BriefingPeriodWeight;
use App\Models\BriefingRecord;
use App\Models\BriefingScore;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class BriefingPeriodWeightDemoSeeder extends Seeder
{
    /** Task global yang dipakai untuk demo (semua sudah include_in_score = true, lihat BriefingTasksSeeder). */
    private const DAILY_TASKS = ['daily_selfie_pagi', 'daily_selfie_sore'];

    private const WEEKLY_TASKS = ['weekly_wm_pic'];

    private const MONTHLY_TASKS = ['monthly_gm_kpi', 'monthly_cleaning_chiller'];

    public function run(): void
    {
        $this->call(BriefingTasksSeeder::class);

        $branch = Branch::firstOrCreate(
            ['name' => 'Bloomery Demo Bobot Penilaian'],
            ['is_active' => true, 'sales_shift_count' => 1],
        );

        // Bobot berbeda dari Default (40/30/30) supaya kelihatan jelas override-nya jalan.
        BriefingPeriodWeight::updateOrCreate(
            ['branch_id' => $branch->id],
            ['daily_weight' => 50, 'weekly_weight' => 30, 'monthly_weight' => 20],
        );

        $staff = User::firstOrCreate(
            ['username' => 'DemoBobotStaff'],
            [
                'name' => 'Demo Staff Bobot Penilaian',
                'email' => null,
                'password' => Hash::make('DemoBobot123!'),
                'branch_id' => $branch->id,
                'is_active' => true,
            ],
        );
        $staff->update(['branch_id' => $branch->id, 'is_active' => true]);
        if (! $staff->hasRole('CASUAL_STAFF')) {
            $staff->assignRole('CASUAL_STAFF');
        }

        $year = (int) now()->year;
        $month = (int) now()->month;
        $today = now()->day;

        // Daily: approved untuk setiap hari yang sudah lewat bulan ini (rate < 100% kalau baru tanggal muda).
        for ($d = 1; $d <= $today; $d++) {
            $date = Carbon::create($year, $month, $d);
            foreach (self::DAILY_TASKS as $taskKey) {
                $this->approve($staff, 'daily', $date, $taskKey);
            }
        }

        // Weekly: approved untuk tiap Senin yang sudah lewat bulan ini.
        $monday = Carbon::create($year, $month, 1);
        if (! $monday->isMonday()) {
            $monday->next(Carbon::MONDAY);
        }
        while ($monday->month === $month && $monday->day <= $today) {
            foreach (self::WEEKLY_TASKS as $taskKey) {
                $this->approve($staff, 'weekly', $monday->copy(), $taskKey);
            }
            $monday->addWeek();
        }

        // Monthly: satu-satunya poin bulanan langsung di-approve.
        $monthStart = Carbon::create($year, $month, 1);
        foreach (self::MONTHLY_TASKS as $taskKey) {
            $this->approve($staff, 'monthly', $monthStart, $taskKey);
        }

        Artisan::call('briefing:compute-scores', [
            '--year' => $year,
            '--month' => $month,
            '--branch' => $branch->id,
        ]);

        $score = BriefingScore::where('branch_id', $branch->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($score) {
            $this->command?->info(sprintf(
                'Demo Bobot Penilaian: branch #%d "%s" -> nilai %s/%s = %s%% (%s)',
                $branch->id,
                $branch->name,
                $year,
                $month,
                number_format($score->score, 2),
                $score->isPassing() ? 'Achieve' : 'Tidak Achieve',
            ));
        } else {
            $this->command?->warn('Demo Bobot Penilaian: tidak ada BriefingScore yang terbentuk — cek data task/record.');
        }
    }

    private function approve(User $staff, string $period, Carbon $recordDate, string $taskKey): void
    {
        $record = BriefingRecord::firstOrCreate(
            ['user_id' => $staff->id, 'period' => $period, 'record_date' => $recordDate->toDateString()],
            ['submitted_at' => $recordDate],
        );

        BriefingItem::updateOrCreate(
            ['briefing_record_id' => $record->id, 'task_key' => $taskKey],
            [
                'is_completed' => true,
                'completed_at' => $recordDate,
                'review_status' => BriefingReviewStatus::Approved->value,
                'reviewed_at' => $recordDate,
            ],
        );
    }
}
