<?php

namespace Database\Seeders;

use App\Enums\BriefingPeriod;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BriefingItemsSeeder extends Seeder
{
    public function run(): void
    {
        $staffData = [
            ['branch_id' => 6,  'name' => 'Staff Balcos',  'username' => 'staff.balcos',  'email' => 'staff.balcos@bloomery.test'],
            ['branch_id' => 20, 'name' => 'Staff Atelier', 'username' => 'staff.atelier', 'email' => 'staff.atelier@bloomery.test'],
        ];

        $taskKeys = ['daily_selfie_pagi', 'daily_selfie_sore', 'daily_detail_briefing'];

        foreach ($staffData as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'password' => Hash::make('password123'),
                    'branch_id' => $data['branch_id'],
                    'is_active' => true,
                ]
            );

            if (! $user->hasRole('CASUAL_STAFF')) {
                $user->assignRole('CASUAL_STAFF');
            }

            foreach (range(0, 4) as $daysAgo) {
                $date = today()->subDays($daysAgo);

                $record = BriefingRecord::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'record_date' => $date->toDateString(),
                        'period' => BriefingPeriod::Daily->value,
                    ],
                    ['submitted_at' => $date->copy()->setTime(16, 0)]
                );

                foreach ($taskKeys as $key) {
                    BriefingItem::firstOrCreate(
                        [
                            'briefing_record_id' => $record->id,
                            'task_key' => $key,
                        ],
                        [
                            'is_completed' => true,
                            'completed_at' => $date->copy()->setTime(16, 0),
                            'notes' => $key === 'daily_detail_briefing' ? 'Briefing berjalan lancar, semua poin dibahas.' : null,
                            'review_status' => 'supervisor_review',
                        ]
                    );
                }
            }
        }

        $this->command->info('Seeded briefing items for Bloomery Balcos & Atelier (5 days × 2 staff × 3 tasks = 30 items).');
    }
}
