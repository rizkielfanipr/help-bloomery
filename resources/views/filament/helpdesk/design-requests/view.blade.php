<x-filament-panels::page>
@php
    $status = $record->status;
    $activities = \Spatie\Activitylog\Models\Activity::forSubject($record)->latest()->get();
@endphp
<x-helpdesk.desktop-detail-shell
    :code="'DS-'.str_pad((string) $record->id, 6, '0', STR_PAD_LEFT)"
    :title="$record->judul_permintaan"
    :subtitle="($record->category?->name ?? 'Design').' · '.($record->branch?->name ?? 'No Branch')"
    :status="$status->getLabel()"
    :status-color="$status->getColor()"
    meta-label="Tanggal Pengajuan"
    :meta-value="$record->created_at->format('d M Y, H:i')"
>
    <x-slot:main>
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            <h2 class="mb-5 font-bold">Design Brief</h2>
            <dl class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-xs text-gray-400">Pemohon</dt><dd class="mt-1 font-semibold">{{ $record->requester?->name ?? '-' }}</dd></div>
                <div><dt class="text-xs text-gray-400">Branch</dt><dd class="mt-1 font-semibold">{{ $record->branch?->name ?? '-' }}</dd></div>
                <div><dt class="text-xs text-gray-400">Kategori</dt><dd class="mt-1 font-semibold">{{ $record->category?->name ?? '-' }}</dd></div>
                <div><dt class="text-xs text-gray-400">PIC</dt><dd class="mt-1 font-semibold">{{ $record->assignee?->name ?? 'Belum ditugaskan' }}</dd></div>
            </dl>
            <div class="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Ringkasan Brief</p>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $record->ringkasan_brief }}</p>
            </div>
        </section>

        @if($record->attachments)
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                <h2 class="mb-4 font-bold">Reference & Attachment</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($record->attachments as $path)
                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}" target="_blank" class="group rounded-xl border border-gray-200 p-4 transition hover:border-blue-400 hover:bg-blue-50 dark:border-gray-700 dark:hover:bg-blue-950/30">
                            <p class="truncate text-sm font-semibold group-hover:text-blue-700">↗ {{ basename($path) }}</p>
                            <p class="mt-1 text-xs text-gray-400">Open attachment</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            <h2 class="mb-5 font-bold">Activity Timeline</h2>
            <div class="space-y-4">
                @forelse($activities as $activity)
                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800/60">
                        <div class="flex justify-between gap-3">
                            <p class="text-sm font-semibold">{{ ucfirst($activity->description) }}</p>
                            <time class="text-xs text-gray-400">{{ $activity->created_at->format('d M Y, H:i') }}</time>
                        </div>
                        @if($activity->properties->isNotEmpty())<p class="mt-2 text-xs text-gray-500">Data permintaan diperbarui.</p>@endif
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Belum ada aktivitas.</p>
                @endforelse
            </div>
        </section>
    </x-slot:main>

    <x-slot:aside>
        <section class="rounded-2xl bg-white p-6 shadow-lg ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            <h2 class="font-bold">Tindak Lanjut Design</h2>
            <p class="mt-1 text-xs text-gray-400">Atur PIC dan status pengerjaan langsung dari halaman ini.</p>
            @can('edit design requests')
                <div class="mt-5 space-y-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">PIC Design</label>
                        <select wire:model="assigneeId" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900">
                            <option value="">Belum ditugaskan</option>
                            @foreach($this->assigneeOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Status</label>
                        <select wire:model="status" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900">
                            @foreach(\App\Enums\RequestStatus::cases() as $option)<option value="{{ $option->value }}">{{ $option->getLabel() }}</option>@endforeach
                        </select>
                    </div>
                    <button wire:click="saveFollowUp" wire:loading.attr="disabled" class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">Simpan Tindak Lanjut</button>
                </div>
            @endcan
            <div class="mt-5 rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                <p class="text-xs text-gray-400">Current PIC</p>
                <p class="mt-1 font-semibold">{{ $record->assignee?->name ?? 'Unassigned' }}</p>
                @if($record->resolved_at)<p class="mt-3 text-xs text-emerald-600">Resolved {{ $record->resolved_at->format('d M Y, H:i') }}</p>@endif
            </div>
        </section>
        <section class="rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 p-5 text-white shadow-lg shadow-blue-900/15">
            <p class="text-xs text-blue-100">Workflow</p>
            <div class="mt-4 space-y-3">
                @foreach([\App\Enums\RequestStatus::Submitted, \App\Enums\RequestStatus::InReview, \App\Enums\RequestStatus::InProgress, \App\Enums\RequestStatus::Completed] as $step)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="h-2.5 w-2.5 rounded-full {{ $status === $step ? 'bg-white ring-4 ring-white/20' : 'bg-blue-400/60' }}"></span>
                        <span class="{{ $status === $step ? 'font-semibold' : 'text-blue-100/70' }}">{{ $step->getLabel() }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </x-slot:aside>
</x-helpdesk.desktop-detail-shell>
</x-filament-panels::page>
