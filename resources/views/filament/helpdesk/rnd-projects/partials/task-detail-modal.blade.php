@if($viewingTaskId)
    @php
        $detailTask = $this->viewingTask();
    @endphp
    @if($detailTask)
        <div class="fixed inset-0 z-[130] flex items-center justify-center p-4" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeTaskDetail()">
            <button type="button" wire:click="closeTaskDetail" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup detail Tugas"></button>
            <x-rnd.picker-modal title="{{ $detailTask->title }}" :description="$detailTask->project->name" max-width="4xl">
                <x-slot name="close">
                    <button type="button" wire:click="closeTaskDetail" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Tutup">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </x-slot>
                <div class="space-y-4 overflow-y-auto p-5 text-sm">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Status</p>
                            <p class="mt-1"><x-filament::badge :color="$detailTask->status->getColor()">{{ $detailTask->status->getLabel() }}</x-filament::badge></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Prioritas</p>
                            <p class="mt-1"><x-filament::badge :color="$detailTask->priority->getColor()">{{ $detailTask->priority->getLabel() }}</x-filament::badge></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Deadline</p>
                            <p class="mt-1 font-semibold text-gray-800 dark:text-gray-100">{{ $detailTask->due_date->format('d M Y') }}{{ $detailTask->isOverdue() ? ' · Overdue' : '' }}</p>
                        </div>
                    </div>

                    @if($detailTask->templateApplication || $detailTask->copiedFromTask)
                        <p class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                            <x-heroicon-o-information-circle class="h-4 w-4 shrink-0" />
                            @if($detailTask->templateApplication)
                                Dibuat dari template “{{ $detailTask->templateApplication->template_name }}”.
                            @else
                                Disalin dari Task “{{ $detailTask->copiedFromTask->title }}”.
                            @endif
                        </p>
                    @endif

                    <div class="rounded-xl border border-gray-200 bg-white p-3.5 dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Catatan Task</p>
                        <p class="mt-1.5 leading-6 text-gray-700 dark:text-gray-200">{{ $detailTask->description ?: 'Tidak ada catatan.' }}</p>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">PIC &amp; Riwayat Tindak Lanjut</p>
                        <div class="space-y-3">
                            @foreach($detailTask->assignments as $assignment)
                                <details wire:key="task-assignment-history-{{ $assignment->id }}" @if($assignment->followUps->isNotEmpty() || $detailTask->assignments->count() === 1) open @endif class="group rounded-xl border border-gray-200 dark:border-gray-700">
                                    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 px-3.5 py-3 marker:hidden">
                                        <span class="flex min-w-0 items-center gap-2">
                                            <x-heroicon-o-chevron-right class="h-4 w-4 shrink-0 text-gray-400 transition group-open:rotate-90" />
                                            <span class="min-w-0 truncate text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $assignment->branch->name }} · {{ $assignment->user?->display_username ?? 'Tidak diketahui' }}</span>
                                        </span>
                                        <span class="flex shrink-0 items-center gap-2">
                                            <span class="text-[11px] text-gray-400">{{ $assignment->followUps->count() }} tindak lanjut</span>
                                            <x-filament::badge :color="$assignment->status->getColor()">{{ $assignment->status->getLabel() }}</x-filament::badge>
                                        </span>
                                    </summary>

                                    <div class="space-y-3 border-t border-gray-100 px-3.5 py-3 text-xs dark:border-gray-800">
                                        <dl class="grid gap-x-4 gap-y-1 text-gray-500 sm:grid-cols-3">
                                            <div><dt class="inline">Ditugaskan:</dt> <dd class="inline text-gray-700 dark:text-gray-200">{{ $assignment->assigned_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                                            <div><dt class="inline">Mulai:</dt> <dd class="inline text-gray-700 dark:text-gray-200">{{ $assignment->started_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                                            <div><dt class="inline">Dikirim:</dt> <dd class="inline text-gray-700 dark:text-gray-200">{{ $assignment->submitted_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                                        </dl>

                                        @if($assignment->reviewed_at)
                                            <div @class([
                                                'rounded-lg border px-3 py-2',
                                                'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200' => $assignment->status === \App\Enums\RndProjectTaskAssignmentStatus::Approved,
                                                'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200' => $assignment->status !== \App\Enums\RndProjectTaskAssignmentStatus::Approved,
                                            ])>
                                                <p class="font-semibold">Review terakhir oleh {{ $assignment->reviewedBy?->display_username ?? 'Tidak diketahui' }} · {{ $assignment->reviewed_at->format('d M Y H:i') }}</p>
                                                @if($assignment->review_note)<p class="mt-0.5">{{ $assignment->review_note }}</p>@endif
                                            </div>
                                        @endif

                                        @forelse($assignment->followUps as $followUp)
                                            <div wire:key="task-follow-up-{{ $followUp->id }}" class="relative border-l-2 border-gray-200 pl-3 dark:border-gray-700">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <span class="flex items-center gap-2">
                                                        <x-filament::badge :color="$followUp->follow_up_type === \App\Enums\RndProjectTaskFollowUpType::Submission ? 'primary' : 'gray'">{{ $followUp->follow_up_type->getLabel() }}</x-filament::badge>
                                                        <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $followUp->submittedBy?->display_username ?? 'Tidak diketahui' }}</span>
                                                    </span>
                                                    <span class="text-[11px] text-gray-400">{{ $followUp->created_at->format('d M Y H:i') }}</span>
                                                </div>
                                                <p class="mt-1 leading-5 text-gray-700 dark:text-gray-200">{{ $followUp->notes ?: 'Tidak ada catatan.' }}</p>
                                                @if($followUp->estimated_completion_date)
                                                    <p class="mt-1 text-[11px] text-gray-500">Perkiraan selesai: {{ $followUp->estimated_completion_date->format('d M Y') }}</p>
                                                @endif
                                                @if($followUp->result_attachments)
                                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                                        @foreach($followUp->result_attachments as $attachmentPath)
                                                            <x-rnd.task-attachment-link :path="$attachmentPath" :number="$loop->iteration" />
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @empty
                                            <p class="text-gray-400">Belum ada tindak lanjut dari PIC ini.</p>
                                        @endforelse
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </div>

                    @foreach($detailTask->assignments->where('status', \App\Enums\RndProjectTaskAssignmentStatus::Submitted) as $reviewAssignment)
                        @can('review', $reviewAssignment)
                            <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-3.5 dark:border-amber-900 dark:bg-amber-950/10">
                                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-xs font-bold uppercase tracking-wide text-amber-800 dark:text-amber-300">Review · {{ $reviewAssignment->branch->name }} · {{ $reviewAssignment->user?->display_username ?? 'Tidak diketahui' }}</p>
                                    <x-filament::badge :color="$reviewAssignment->status->getColor()">Menunggu Review</x-filament::badge>
                                </div>
                                @php
                                    $latestSubmission = $reviewAssignment->followUps->firstWhere('follow_up_type', \App\Enums\RndProjectTaskFollowUpType::Submission);
                                @endphp
                                @if($latestSubmission)
                                    <div class="mb-2 rounded-lg bg-white p-2.5 text-xs dark:bg-gray-900">
                                        <p class="text-gray-700 dark:text-gray-200">{{ $latestSubmission->notes ?: 'Tidak ada catatan dari PIC.' }}</p>
                                        @if($latestSubmission->result_attachments)
                                            <div class="mt-1.5 flex flex-wrap gap-2">
                                                @foreach($latestSubmission->result_attachments as $attachmentPath)
                                                    <x-rnd.task-attachment-link :path="$attachmentPath" :number="$loop->iteration" />
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                                <label for="review-note-{{ $reviewAssignment->id }}" class="mb-1 block text-[11px] font-semibold text-gray-600 dark:text-gray-300">Catatan Review</label>
                                <textarea id="review-note-{{ $reviewAssignment->id }}" wire:model="reviewNote" rows="2" class="w-full resize-none rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="Wajib diisi jika meminta revisi..."></textarea>
                                @error('reviewNote')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                                <div class="mt-2 flex justify-end gap-2">
                                    <button type="button" wire:click="requestRevision({{ $reviewAssignment->id }})" wire:loading.attr="disabled" wire:target="requestRevision, approveFollowUp" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Minta Revisi</button>
                                    <button type="button" wire:click="approveFollowUp({{ $reviewAssignment->id }})" wire:loading.attr="disabled" wire:target="requestRevision, approveFollowUp" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50">Approve</button>
                                </div>
                            </div>
                        @endcan
                    @endforeach

                    @foreach($this->myAssignmentsForTask($detailTask) as $myAssignment)
                        @php
                            $followUpLabel = 'mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300';
                            $followUpField = 'w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-slate-700 placeholder-slate-300 focus:border-blue-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-900 dark:text-slate-200';
                        @endphp
                        <div wire:key="my-follow-up-{{ $myAssignment->id }}" class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-3.5 dark:border-gray-700 dark:bg-gray-900">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-600 dark:text-slate-300">Tindak Lanjut Saya · {{ $myAssignment->branch->name }}</p>
                                <x-filament::badge :color="$myAssignment->status->getColor()">{{ $myAssignment->status->getLabel() }}</x-filament::badge>
                            </div>

                            @if($myAssignment->status->value === 'assigned')
                                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 dark:border-blue-900 dark:bg-blue-950/30">
                                    <p class="text-[11px] text-blue-700 dark:text-blue-300">Mulai saat Anda mengerjakan Task ini, lalu laporkan progres di sini.</p>
                                    <button type="button" wire:click="startAssignment({{ $myAssignment->id }})" wire:loading.attr="disabled" wire:target="startAssignment" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-60">Mulai Mengerjakan</button>
                                </div>
                            @elseif(in_array($myAssignment->status->value, ['in_progress', 'revision_required'], true))
                                @if($myAssignment->status->value === 'revision_required' && $myAssignment->review_note)
                                    <div class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 dark:border-amber-800 dark:bg-amber-900/20">
                                        <x-heroicon-o-exclamation-triangle class="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-500" />
                                        <p class="text-[11px] leading-relaxed text-amber-800 dark:text-amber-300"><span class="font-semibold">Catatan Revisi:</span> {{ $myAssignment->review_note }}</p>
                                    </div>
                                @endif

                                <div>
                                    <label for="follow-up-notes-{{ $myAssignment->id }}" class="{{ $followUpLabel }}">Catatan Progress / Kendala</label>
                                    <textarea id="follow-up-notes-{{ $myAssignment->id }}" wire:model="followUpNotes" rows="2" placeholder="Progres, hasil, atau kendala..." class="{{ $followUpField }} resize-none"></textarea>
                                    @error('followUpNotes')<p class="mt-1 text-[11px] text-red-500">{{ $message }}</p>@enderror
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="follow-up-estimate-{{ $myAssignment->id }}" class="{{ $followUpLabel }}">Perkiraan Selesai <span class="font-normal text-slate-400">(opsional)</span></label>
                                        <input id="follow-up-estimate-{{ $myAssignment->id }}" type="date" wire:model="followUpEstimatedDate" class="{{ $followUpField }}">
                                        @error('followUpEstimatedDate')<p class="mt-1 text-[11px] text-red-500">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <p class="{{ $followUpLabel }}">Lampiran Hasil <span class="font-normal text-slate-400">(maks. 5 · 8 MB)</span></p>
                                        <label class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-dashed border-gray-300 px-3 py-2 transition hover:border-blue-300 hover:bg-blue-50 focus-within:border-blue-400 dark:border-gray-700 dark:hover:border-blue-600 dark:hover:bg-blue-900/20">
                                            <x-heroicon-o-arrow-up-tray class="h-3.5 w-3.5 text-gray-400" />
                                            <span class="text-xs text-gray-400" wire:loading.remove wire:target="followUpAttachments">Tambah File / Foto</span>
                                            <span class="text-xs text-blue-600" wire:loading wire:target="followUpAttachments">Mengunggah...</span>
                                            <input type="file" wire:model="followUpAttachments" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="sr-only">
                                        </label>
                                        @error('followUpAttachments')<p class="mt-1 text-[11px] text-red-500">{{ $message }}</p>@enderror
                                        @error('followUpAttachments.*')<p class="mt-1 text-[11px] text-red-500">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                @if(count($followUpAttachments) > 0)
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($followUpAttachments as $index => $file)
                                            <span wire:key="follow-up-file-{{ $index }}" class="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-gray-200 px-2 py-1 dark:border-gray-700">
                                                <x-heroicon-o-paper-clip class="h-3.5 w-3.5 shrink-0 text-blue-400" />
                                                <span class="truncate text-[11px] text-slate-600 dark:text-slate-300">{{ $file->getClientOriginalName() }}</span>
                                                <button type="button" wire:click="removeFollowUpAttachment({{ $index }})" class="shrink-0 text-red-400 transition hover:text-red-600" aria-label="Hapus {{ $file->getClientOriginalName() }}">
                                                    <x-heroicon-o-x-mark class="h-3.5 w-3.5" />
                                                </button>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="flex justify-end gap-2">
                                    <button type="button" wire:click="saveFollowUp({{ $myAssignment->id }}, 'progress')" wire:loading.attr="disabled" wire:target="saveFollowUp, followUpAttachments" title="Laporan berkala, belum diajukan ke reviewer" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-slate-200 dark:hover:bg-gray-800">Simpan Progress</button>
                                    <button type="button" wire:click="saveFollowUp({{ $myAssignment->id }}, 'submission')" wire:loading.attr="disabled" wire:target="saveFollowUp, followUpAttachments" title="Ajukan hasil ke reviewer" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-60">
                                        <span wire:loading.remove wire:target="saveFollowUp">Kirim Tindak Lanjut</span>
                                        <span wire:loading wire:target="saveFollowUp">Menyimpan...</span>
                                    </button>
                                </div>
                            @elseif($myAssignment->status->value === 'submitted')
                                <p class="flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                    <x-heroicon-o-clock class="h-3.5 w-3.5 shrink-0" /> Tindak lanjut sudah dikirim, menunggu review.
                                </p>
                            @elseif($myAssignment->status->value === 'approved')
                                <p class="flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-[11px] text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300">
                                    <x-heroicon-o-check-circle class="h-3.5 w-3.5 shrink-0" /> Hasil sudah disetujui.
                                </p>
                            @endif
                        </div>
                    @endforeach

                    @can('assign', $detailTask)
                        <div class="rounded-xl border border-dashed border-gray-300 p-3.5 dark:border-gray-700">
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Tambah / Alihkan PIC</p>
                            <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                                <select wire:model="assignBranchId" class="rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                    <option value="">Pilih Branch...</option>
                                    @foreach($detailTask->branches as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                <select wire:model="assignUserId" class="rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                    <option value="">Pilih PIC...</option>
                                    @foreach($this->eligiblePicsForBranch($assignBranchId) as $userId => $label)
                                        <option value="{{ $userId }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="assignTaskPic" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700">Simpan</button>
                            </div>
                            @error('assignBranchId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            @error('assignUserId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @endcan
                </div>
                <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 p-5 dark:border-gray-700">
                    @can('copy', $detailTask)
                        <button type="button" wire:click="openCopyTaskModal({{ $detailTask->id }})" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                            <x-heroicon-o-document-duplicate class="h-4 w-4" /> Copy Task
                        </button>
                    @endcan
                    @can('cancel', $detailTask)
                        <button type="button" x-on:click="window.BloomeryConfirm.show({ title: 'Batalkan Tugas?', text: 'Tugas ini akan dibatalkan dan seluruh assignment aktif ikut dibatalkan. Histori tetap tersimpan.', confirmText: 'Batalkan Tugas' }).then((confirmed) => { if (confirmed) $wire.cancelTask({{ $detailTask->id }}) })" class="rounded-lg border border-red-200 px-4 py-2.5 text-sm font-bold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/30">Batalkan Tugas</button>
                    @endcan
                    @can('update', $detailTask)
                        <button type="button" wire:click="openEditTaskModal({{ $detailTask->id }})" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700">Edit Tugas</button>
                    @endcan
                </div>
            </x-rnd.picker-modal>
        </div>
    @endif
@endif
