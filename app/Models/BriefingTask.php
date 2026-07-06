<?php

namespace App\Models;

use App\Enums\BriefingPeriod;
use App\Enums\BriefingSubmissionType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class BriefingTask extends Model
{
    protected $fillable = [
        'branch_id', 'key', 'label', 'period', 'submission_type', 'note_type',
        'group', 'group_label', 'sort_order', 'is_active',
        'deadline_time', 'deadline_day', 'deadline_enabled', 'weight',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'period' => BriefingPeriod::class,
            'submission_type' => BriefingSubmissionType::class,
            'is_active' => 'boolean',
            'deadline_enabled' => 'boolean',
            'sort_order' => 'integer',
            'deadline_day' => 'integer',
            'weight' => 'float',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function deadlineAt(): ?Carbon
    {
        if (! $this->deadline_enabled || ! $this->deadline_time) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $this->deadline_time));

        return match ($this->period) {
            BriefingPeriod::Daily => today()->setHour($hour)->setMinute($minute)->setSecond(0),

            BriefingPeriod::Weekly => now()->startOfWeek()
                ->addDays(max(0, ($this->deadline_day ?? 5) - 1))
                ->setHour($hour)->setMinute($minute)->setSecond(0),

            BriefingPeriod::Monthly => ($this->deadline_day === 0 || $this->deadline_day === null)
                ? now()->endOfMonth()->setHour($hour)->setMinute($minute)->setSecond(0)
                : now()->startOfMonth()->addDays(($this->deadline_day) - 1)->setHour($hour)->setMinute($minute)->setSecond(0),
        };
    }

    public function isPastDeadline(): bool
    {
        $deadline = $this->deadlineAt();

        return $deadline !== null && now()->isAfter($deadline);
    }

    public function deadlineLabel(): ?string
    {
        if (! $this->deadline_enabled || ! $this->deadline_time) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $this->deadline_time));
        $time = sprintf('%02d.%02d', $hour, $minute);

        return match ($this->period) {
            BriefingPeriod::Daily => "Batas: {$time}",
            BriefingPeriod::Weekly => 'Batas: '.($this->indonesianDay($this->deadline_day ?? 5))." {$time}",
            BriefingPeriod::Monthly => ($this->deadline_day === 0 || $this->deadline_day === null)
                ? "Batas: Akhir bulan {$time}"
                : "Batas: Tgl. {$this->deadline_day} pukul {$time}",
        };
    }

    private function indonesianDay(int $day): string
    {
        return ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'][max(0, $day - 1)];
    }

    /** @return Collection<int, self> */
    public static function forPeriod(BriefingPeriod $period, ?int $branchId = null): Collection
    {
        return static::cached()
            ->where('period', $period)
            ->where('is_active', true)
            ->filter(fn (self $t) => $t->branch_id === $branchId)
            ->sortBy('sort_order')
            ->values();
    }

    /** @return Collection<int, self> */
    public static function cached(): Collection
    {
        $rows = Cache::remember('briefing_tasks_v1', 300, function () {
            return static::orderBy('sort_order')->get()
                ->map(fn (self $t) => $t->getAttributes())
                ->all();
        });

        return new Collection(array_map(function (array $attrs): self {
            $model = new self;
            $model->setRawAttributes($attrs);
            $model->exists = true;

            return $model;
        }, $rows));
    }

    public static function clearCache(): void
    {
        Cache::forget('briefing_tasks_v1');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
