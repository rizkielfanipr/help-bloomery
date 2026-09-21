<?php

namespace App\Console\Commands;

use App\Enums\BriefingReviewStatus;
use App\Models\Branch;
use App\Models\BriefingItem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('briefing:approve-existing {branches* : Nama cabang persis seperti di aplikasi (huruf besar/kecil diabaikan)} {--dry-run : Hanya tampilkan jumlah item tanpa mengubah data}')]
#[Description('One-off: approve existing briefing submissions of the given branches that are awaiting review or were auto-rejected by the system')]
class BriefingApproveExistingCommand extends Command
{
    public function handle(): int
    {
        $branches = $this->resolveBranches();

        if ($branches === null) {
            return Command::FAILURE;
        }

        $branchIds = $branches->pluck('id');

        $this->table(
            ['Cabang', 'Menunggu review', 'Ditolak sistem (sudah diinput)', 'Tidak diubah: tanpa input', 'Tidak diubah: ditolak manual'],
            $branches->map(fn (Branch $branch): array => [
                $branch->name,
                $this->awaitingReview(collect([$branch->id]))->count(),
                $this->rejectedBySystem(collect([$branch->id]))->count(),
                $this->missedDeadline(collect([$branch->id]))->count(),
                $this->rejectedByReviewer(collect([$branch->id]))->count(),
            ])->all(),
        );

        $toApprove = $this->awaitingReview($branchIds)->count() + $this->rejectedBySystem($branchIds)->count();

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$toApprove} item akan di-approve. Tidak ada data yang diubah.");

            return Command::SUCCESS;
        }

        if ($toApprove === 0) {
            $this->info('Tidak ada item yang perlu di-approve.');

            return Command::SUCCESS;
        }

        if (! $this->confirm("Approve {$toApprove} item briefing di {$branches->count()} cabang?")) {
            $this->warn('Dibatalkan.');

            return Command::SUCCESS;
        }

        $approved = DB::transaction(fn (): int => $this->approve($this->awaitingReview($branchIds))
            + $this->approve($this->rejectedBySystem($branchIds)));

        $this->info("{$approved} item briefing di-approve.");
        $this->line('Hitung ulang skor bulan yang terdampak: php artisan briefing:compute-scores --year=YYYY --month=M --branch=ID');

        return Command::SUCCESS;
    }

    /**
     * @return Collection<int, Branch>|null
     */
    private function resolveBranches(): ?Collection
    {
        $requested = collect($this->argument('branches'))->map(fn (string $name): string => mb_strtolower(trim($name)));
        $all = Branch::query()->get(['id', 'name']);
        $matched = $all->filter(fn (Branch $branch): bool => $requested->contains(mb_strtolower($branch->name)));
        $unknown = $requested->diff($matched->map(fn (Branch $branch): string => mb_strtolower($branch->name)));

        if ($unknown->isNotEmpty()) {
            $this->error('Cabang tidak ditemukan: '.$unknown->implode(', '));
            $this->line('Cabang yang tersedia: '.$all->pluck('name')->implode(', '));

            return null;
        }

        return $matched->values();
    }

    /**
     * @param  Collection<int, int>  $branchIds
     * @return Builder<BriefingItem>
     */
    private function forBranches(Collection $branchIds): Builder
    {
        return BriefingItem::query()->whereHas('record', fn (Builder $record): Builder => $record
            ->where(fn (Builder $query): Builder => $query
                ->whereIn('branch_id', $branchIds)
                ->orWhere(fn (Builder $legacy): Builder => $legacy
                    ->whereNull('branch_id')
                    ->whereHas('user', fn (Builder $user): Builder => $user->whereIn('branch_id', $branchIds)))));
    }

    /**
     * @param  Collection<int, int>  $branchIds
     * @return Builder<BriefingItem>
     */
    private function awaitingReview(Collection $branchIds): Builder
    {
        return $this->forBranches($branchIds)->whereIn('review_status', [
            BriefingReviewStatus::LegacyPending->value,
            BriefingReviewStatus::SupervisorReview->value,
        ]);
    }

    /**
     * Auto-rejected items keep no reviewer, unlike rejections made by a person.
     *
     * @param  Collection<int, int>  $branchIds
     * @return Builder<BriefingItem>
     */
    private function rejectedBySystem(Collection $branchIds): Builder
    {
        return $this->forBranches($branchIds)
            ->where('review_status', BriefingReviewStatus::Rejected->value)
            ->whereNull('reviewed_by')
            ->where('is_completed', true);
    }

    /**
     * @param  Collection<int, int>  $branchIds
     * @return Builder<BriefingItem>
     */
    private function missedDeadline(Collection $branchIds): Builder
    {
        return $this->forBranches($branchIds)
            ->where('review_status', BriefingReviewStatus::Rejected->value)
            ->whereNull('reviewed_by')
            ->where('is_completed', false);
    }

    /**
     * @param  Collection<int, int>  $branchIds
     * @return Builder<BriefingItem>
     */
    private function rejectedByReviewer(Collection $branchIds): Builder
    {
        return $this->forBranches($branchIds)
            ->where('review_status', BriefingReviewStatus::Rejected->value)
            ->whereNotNull('reviewed_by');
    }

    /**
     * @param  Builder<BriefingItem>  $items
     */
    private function approve(Builder $items): int
    {
        return $items->update([
            'review_status' => BriefingReviewStatus::Approved->value,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => now(),
        ]);
    }
}
