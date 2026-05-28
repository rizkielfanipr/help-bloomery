<?php

namespace App\Filament\Helpdesk\Widgets;

use App\Models\CasualPosition;
use Filament\Widgets\Widget;

class CasualPositionStatsWidget extends Widget
{
    protected string $view = 'filament.helpdesk.widgets.casual-position-stats';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function getStats(): array
    {
        $total = CasualPosition::count();
        $active = CasualPosition::where('is_active', true)->count();
        $staffCount = (int) CasualPosition::withCount('users')->get()->sum('users_count');
        $avgFee = (float) (CasualPosition::avg('fee_per_day') ?? 0);
        $activePercent = $total > 0 ? round(($active / $total) * 100) : 0;

        return [
            [
                'label' => 'Total Posisi',
                'value' => number_format($total),
                'sub' => 'Posisi tersedia',
                'icon' => 'heroicon-o-briefcase',
                'bg' => 'bg-blue-50 dark:bg-blue-500/10',
                'icon_color' => 'text-blue-600 dark:text-blue-400',
                'border' => 'border-blue-100 dark:border-blue-500/10',
            ],
            [
                'label' => 'Staff Aktif',
                'value' => number_format($staffCount),
                'sub' => 'Staff yang aktif',
                'icon' => 'heroicon-o-users',
                'bg' => 'bg-violet-50 dark:bg-violet-500/10',
                'icon_color' => 'text-violet-600 dark:text-violet-400',
                'border' => 'border-violet-100 dark:border-violet-500/10',
            ],
            [
                'label' => 'Rata-rata Fee/Hari',
                'value' => 'IDR '.number_format($avgFee, 0, ',', '.'),
                'sub' => 'Rata-rata per posisi',
                'icon' => 'heroicon-o-banknotes',
                'bg' => 'bg-amber-50 dark:bg-amber-500/10',
                'icon_color' => 'text-amber-600 dark:text-amber-400',
                'border' => 'border-amber-100 dark:border-amber-500/10',
            ],
            [
                'label' => 'Posisi Aktif',
                'value' => number_format($active),
                'sub' => $activePercent.'% dari total',
                'icon' => 'heroicon-o-check-badge',
                'bg' => 'bg-emerald-50 dark:bg-emerald-500/10',
                'icon_color' => 'text-emerald-600 dark:text-emerald-400',
                'border' => 'border-emerald-100 dark:border-emerald-500/10',
            ],
        ];
    }
}
