<?php

namespace Database\Seeders;

use App\Actions\Rnd\ProjectTask\ReviewProjectTaskFollowUpAction;
use App\Actions\Rnd\ProjectTask\StartProjectTaskAssignmentAction;
use App\Actions\Rnd\ProjectTask\SubmitProjectTaskFollowUpAction;
use App\Enums\RndProjectTaskAssignmentStatus;
use App\Models\RndProject;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Models\User;
use App\Services\Rnd\ProjectTask\ProjectTaskAssigneeResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local demo data: follow-up history with result attachments for every assignment of one R&D
 * Project, driven through the real workflow Actions so assignment and Task statuses stay
 * consistent (approved, revision, submitted, in progress). Assignments that already have
 * follow-ups are skipped, so re-running is safe.
 *
 * Usage: php artisan db:seed --class=RndProjectTaskFollowUpDemoSeeder (asks for the Project ID).
 *
 * Guarded to local/testing and to a local-driver attachment disk, so it never writes demo files
 * to the real R2 bucket — set RND_PROJECT_TASK_ATTACHMENT_DISK=local first.
 */
class RndProjectTaskFollowUpDemoSeeder extends Seeder
{
    private const SCENARIOS = ['approved', 'revision', 'submitted', 'in_progress', 'progress_photos', 'text_only'];

    /**
     * Upper bounds used to place the whole timeline in the past: no scenario has more than five
     * steps, and steps are at most 25 minutes apart.
     */
    private const MAX_STEPS_PER_ASSIGNMENT = 5;

    private const MAX_MINUTES_PER_STEP = 25;

    private Carbon $clock;

    private int $maxMinutesPerStep = self::MAX_MINUTES_PER_STEP;

    public function __construct(
        private readonly StartProjectTaskAssignmentAction $startAssignment,
        private readonly SubmitProjectTaskFollowUpAction $submitFollowUp,
        private readonly ReviewProjectTaskFollowUpAction $reviewFollowUp,
        private readonly ProjectTaskAssigneeResolver $assigneeResolver,
    ) {}

    public function run(?int $projectId = null): void
    {
        $this->assertSafeEnvironment();

        $projectId ??= (int) ($this->command?->ask('ID Project R&D yang akan diisi tindak lanjut', (string) RndProject::query()->latest('id')->value('id'))
            ?? RndProject::query()->latest('id')->value('id'));
        $project = RndProject::query()->findOrFail($projectId);

        $assignments = RndProjectTaskAssignment::query()
            ->with(['task', 'user'])
            ->whereHas('task', fn ($query) => $query->whereBelongsTo($project, 'project'))
            ->whereDoesntHave('followUps')
            ->whereNot('status', RndProjectTaskAssignmentStatus::Cancelled->value)
            ->whereNotNull('user_id')
            ->orderBy('rnd_project_task_id')
            ->orderBy('id')
            ->get();

        Notification::fake();
        $previousTestNow = Carbon::getTestNow();
        $this->planTimeline($assignments->count(), $assignments->max('assigned_at'));

        try {
            foreach ($assignments->values() as $index => $assignment) {
                $this->{'seed'.Str::studly(self::SCENARIOS[$index % count(self::SCENARIOS)])}($assignment);
            }
        } finally {
            Carbon::setTestNow($previousTestNow);
        }

        $this->command?->info("Tindak lanjut demo dibuat untuk {$assignments->count()} assignment pada Project “{$project->name}”.");
    }

    private function seedApproved(RndProjectTaskAssignment $assignment): void
    {
        $this->at(fn () => $this->startAssignment->execute($assignment));
        $this->progress($assignment, 'Uji coba pertama selesai. Tekstur sudah sesuai, rasa manis masih perlu dikurangi sedikit.', 3, ['png']);
        $this->submission($assignment, 'Resep final sudah distandarkan. Terlampir foto hasil dan lembar resep.', ['png', 'pdf']);
        $this->review($assignment, 'approve', 'Hasil sesuai standar. Lanjut ke tahap berikutnya.');
    }

    private function seedRevision(RndProjectTaskAssignment $assignment): void
    {
        $this->at(fn () => $this->startAssignment->execute($assignment));
        $this->submission($assignment, 'Hasil tasting internal terlampir, skor rata-rata 7,8 dari 10.', ['png']);
        $this->review($assignment, 'revision', 'Mohon tambahkan rekap skor per panelis dan foto plating dari dua sisi.');
        $this->at(fn () => $this->startAssignment->execute($assignment->fresh()));
        $this->progress($assignment, 'Sedang melengkapi rekap per panelis sesuai catatan revisi.', 2, ['png']);
    }

    private function seedSubmitted(RndProjectTaskAssignment $assignment): void
    {
        $this->progress($assignment, 'Dokumen pengajuan sedang disiapkan bersama tim finance.', 2, ['pdf']);
        $this->submission($assignment, 'Dokumen approval menu dan perhitungan harga sudah lengkap, menunggu review.', ['png', 'pdf']);
    }

    private function seedInProgress(RndProjectTaskAssignment $assignment): void
    {
        $this->at(fn () => $this->startAssignment->execute($assignment));
        $this->progress($assignment, 'Pengecekan suhu penyimpanan hari pertama sesuai standar.', 4, ['png']);
        $this->progress($assignment, 'Uji shelf life hari ketiga, belum ada perubahan warna maupun aroma.', 3, ['png', 'png']);
    }

    private function seedProgressPhotos(RndProjectTaskAssignment $assignment): void
    {
        $this->at(fn () => $this->startAssignment->execute($assignment));
        $this->progress($assignment, 'Sesi foto produk tahap pertama selesai, terlampir beberapa alternatif angle.', 2, ['png', 'png', 'png']);
    }

    private function seedTextOnly(RndProjectTaskAssignment $assignment): void
    {
        $this->at(fn () => $this->startAssignment->execute($assignment));
        $this->progress($assignment, 'Koordinasi jadwal launching dengan tim Branch sudah dimulai.', 5, []);
    }

    /**
     * @param  list<string>  $attachmentTypes
     */
    private function progress(RndProjectTaskAssignment $assignment, string $notes, int $estimatedInDays, array $attachmentTypes): void
    {
        $this->at(fn () => $this->submitFollowUp->execute($assignment->fresh(), [
            'follow_up_type' => 'progress',
            'notes' => $notes,
            'estimated_completion_date' => $this->clock->copy()->addDays($estimatedInDays)->toDateString(),
            'result_attachments' => $this->storeAttachments($assignment, $attachmentTypes),
        ], $assignment->user));
    }

    /**
     * @param  list<string>  $attachmentTypes
     */
    private function submission(RndProjectTaskAssignment $assignment, string $notes, array $attachmentTypes): void
    {
        $this->at(fn () => $this->submitFollowUp->execute($assignment->fresh(), [
            'follow_up_type' => 'submission',
            'notes' => $notes,
            'estimated_completion_date' => null,
            'result_attachments' => $this->storeAttachments($assignment, $attachmentTypes),
        ], $assignment->user));
    }

    private function review(RndProjectTaskAssignment $assignment, string $decision, string $note): void
    {
        $this->at(fn () => $this->reviewFollowUp->execute($assignment->fresh(), $decision, $note, $this->reviewerFor($assignment)));
    }

    /**
     * Runs one workflow step at the next point of a realistic, strictly increasing timeline.
     */
    private function at(callable $step): void
    {
        $this->clock = $this->clock->copy()->addMinutes(random_int(max(1, intdiv($this->maxMinutesPerStep, 2)), $this->maxMinutesPerStep));
        Carbon::setTestNow($this->clock);

        $step();
    }

    /**
     * Places every step after the newest assignment was made and before now, shrinking the gap
     * between steps when there is little time to spread them over.
     */
    private function planTimeline(int $assignmentCount, ?Carbon $latestAssignedAt): void
    {
        $maxSteps = max(1, $assignmentCount * self::MAX_STEPS_PER_ASSIGNMENT);
        $start = now()->subMinutes($maxSteps * self::MAX_MINUTES_PER_STEP + 30);

        if ($latestAssignedAt !== null && $latestAssignedAt->gt($start)) {
            $start = $latestAssignedAt->copy();
        }

        $this->clock = $start;
        $this->maxMinutesPerStep = max(1, min(self::MAX_MINUTES_PER_STEP, intdiv((int) $start->diffInMinutes(now()) - 1, $maxSteps)));
    }

    private function reviewerFor(RndProjectTaskAssignment $assignment): User
    {
        return $this->assigneeResolver->reviewersForBranch($assignment->branch_id)->first()
            ?? User::role('SUPERADMIN')->first()
            ?? User::query()->whereKeyNot($assignment->user_id)->firstOrFail();
    }

    /**
     * @param  list<string>  $types
     * @return list<string>|null
     */
    private function storeAttachments(RndProjectTaskAssignment $assignment, array $types): ?array
    {
        $paths = [];

        foreach ($types as $position => $type) {
            $label = "{$assignment->task->title} · Lampiran ".($position + 1);
            $path = "rnd/project-tasks/{$assignment->rnd_project_task_id}/assignments/{$assignment->id}/results/demo-".Str::lower(Str::random(10)).".{$type}";

            Storage::disk(RndProjectTask::attachmentDisk())->put($path, $type === 'pdf' ? $this->pdf($label) : $this->png($label));
            $paths[] = $path;
        }

        return $paths !== [] ? $paths : null;
    }

    private function png(string $label): string
    {
        $image = imagecreatetruecolor(640, 400);
        $palette = [[219, 234, 254], [220, 252, 231], [254, 243, 199], [243, 232, 255]];
        [$red, $green, $blue] = $palette[array_rand($palette)];
        imagefill($image, 0, 0, imagecolorallocate($image, $red, $green, $blue));
        $ink = imagecolorallocate($image, 31, 41, 55);
        imagestring($image, 5, 32, 170, Str::ascii($label), $ink);
        imagestring($image, 3, 32, 200, 'Demo attachment - '.now()->format('d M Y H:i'), $ink);

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * Minimal single-page PDF with a valid cross-reference table.
     */
    private function pdf(string $label): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], Str::ascii($label));
        $stream = "BT /F1 18 Tf 72 760 Td ({$text}) Tj 0 -28 Td /F1 11 Tf (Demo attachment - ".now()->format('d M Y H:i').') Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($number + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= 'xref
0 '.(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer
<< /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";
    }

    private function assertSafeEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Seeder demo tindak lanjut hanya boleh dijalankan di environment local/testing.');
        }

        $disk = RndProjectTask::attachmentDisk();

        if (config("filesystems.disks.{$disk}.driver") !== 'local' && ! app()->environment('testing')) {
            throw new RuntimeException("Disk attachment Task \"{$disk}\" bukan disk lokal. Set RND_PROJECT_TASK_ATTACHMENT_DISK=local agar file demo tidak terunggah ke R2.");
        }
    }
}
