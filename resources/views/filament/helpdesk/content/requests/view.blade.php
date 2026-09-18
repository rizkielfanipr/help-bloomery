<x-filament-panels::page>
@php
    $status = $record->status;
    $statusOptions = $this->statusOptions();
@endphp

<div class="mx-auto w-full max-w-6xl space-y-5">
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-700 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="font-mono text-sm font-semibold text-blue-600 dark:text-blue-400">{{ $record->code }}</p>
                <h1 class="mt-1 truncate text-xl font-semibold text-gray-900 dark:text-white">{{ $record->judul_konten }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $record->jenis_konten === 'video' ? 'Video' : 'Foto' }} · {{ $record->platform_tujuan ?? '-' }} · {{ $record->branch?->name ?? 'No Branch' }}</p>
            </div>
            <div class="flex shrink-0 flex-col items-start gap-1 sm:items-end">
                <span @class([
                    'rounded-md px-2.5 py-1 text-xs font-semibold',
                    'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => $status === \App\Enums\ContentRequestStatus::Submitted,
                    'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' => $status === \App\Enums\ContentRequestStatus::InProgress,
                    'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' => $status === \App\Enums\ContentRequestStatus::Approval,
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' => $status === \App\Enums\ContentRequestStatus::Completed,
                    'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' => $status === \App\Enums\ContentRequestStatus::Rejected,
                ])>{{ $status->getLabel() }}</span>
                <span class="text-xs text-gray-400">{{ $record->created_at->format('d M Y, H:i') }}</span>
            </div>
        </div>

        <div class="grid gap-x-8 gap-y-5 px-6 py-5 sm:grid-cols-2 lg:grid-cols-4">
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Pemohon</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->requester?->name ?? '-' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Branch</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->branch?->name ?? '-' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Jenis Konten</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->jenis_konten === 'video' ? 'Video' : 'Foto' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Platform Tujuan</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->platform_tujuan ?? '-' }}</p></div>
            @if($record->link_contoh_konten)
                <div class="sm:col-span-2"><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Link Contoh Konten</p><a href="{{ $record->link_contoh_konten }}" target="_blank" class="mt-1 block truncate text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400">{{ $record->link_contoh_konten }}</a></div>
            @endif
            @if($record->resolved_at)
                <div class="sm:col-span-2"><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Resolved At</p><p class="mt-1 text-sm font-semibold text-emerald-700 dark:text-emerald-400">{{ $record->resolved_at->format('d M Y, H:i') }}</p></div>
            @endif
        </div>

        <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Tujuan Konten</p>
            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $record->tujuan_konten }}</p>
        </div>

    @can('edit content requests')
        @if(count($statusOptions) > 0)
            <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
                @if($status === \App\Enums\ContentRequestStatus::Approval)
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Catatan <span class="font-normal normal-case text-gray-400">(wajib diisi jika ditolak)</span></label>
                        <textarea wire:model="adminNotes" rows="3" class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900" placeholder="Add notes for this status update..."></textarea>
                        @error('adminNotes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div @class(['flex flex-wrap gap-2', 'mt-4' => $status === \App\Enums\ContentRequestStatus::Approval])>
                    @foreach($statusOptions as $value => $label)
                        @if($value === \App\Enums\ContentRequestStatus::Rejected->value)
                            <button type="button" wire:click="transitionTo('{{ $value }}')" wire:loading.attr="disabled" class="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:bg-gray-900 dark:hover:bg-red-950/30">{{ $label }}</button>
                        @else
                            <button type="button" wire:click="transitionTo('{{ $value }}')" wire:loading.attr="disabled" class="rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-600 transition hover:bg-blue-50 disabled:opacity-50 dark:border-blue-900 dark:bg-gray-900 dark:hover:bg-blue-950/30">{{ $label }}</button>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    @endcan

    @php
        $historyByStatus = $record->statusHistories->keyBy(fn ($history) => $history->to_status->value);
        $isRejected = $historyByStatus->has(\App\Enums\ContentRequestStatus::Rejected->value);
        $timelineSteps = [
            ['status' => \App\Enums\ContentRequestStatus::Submitted, 'icon' => 'heroicon-o-paper-airplane'],
            ['status' => \App\Enums\ContentRequestStatus::InProgress, 'icon' => 'heroicon-o-video-camera'],
            ['status' => $isRejected ? \App\Enums\ContentRequestStatus::Rejected : \App\Enums\ContentRequestStatus::Approval, 'icon' => $isRejected ? 'heroicon-o-x-mark' : 'heroicon-o-check'],
            ['status' => \App\Enums\ContentRequestStatus::Completed, 'icon' => 'heroicon-o-check-badge'],
        ];
    @endphp
    <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
        <div class="overflow-x-auto pb-1">
            <div class="flex min-w-[760px] items-start">
                @foreach($timelineSteps as $step)
                    @php
                        $stepStatus = $step['status'];
                        $history = $historyByStatus->get($stepStatus->value);
                        $isReached = $history !== null;
                    @endphp
                    <div class="flex min-w-0 flex-1 items-start">
                        <div class="w-full text-center">
                            <div @class([
                                'mx-auto flex h-9 w-9 items-center justify-center rounded-full border',
                                'border-gray-200 bg-gray-50 text-gray-400 dark:border-gray-700 dark:bg-gray-800' => ! $isReached,
                                'border-blue-200 bg-blue-50 text-blue-600 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300' => $isReached && in_array($stepStatus, [\App\Enums\ContentRequestStatus::Submitted, \App\Enums\ContentRequestStatus::InProgress], true),
                                'border-emerald-200 bg-emerald-50 text-emerald-600 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300' => $isReached && in_array($stepStatus, [\App\Enums\ContentRequestStatus::Approval, \App\Enums\ContentRequestStatus::Completed], true),
                                'border-red-200 bg-red-50 text-red-600 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300' => $isReached && $stepStatus === \App\Enums\ContentRequestStatus::Rejected,
                            ])>
                                <x-dynamic-component :component="$step['icon']" class="h-4 w-4" />
                            </div>
                            <p @class(['mt-2 text-xs font-semibold', 'text-gray-800 dark:text-gray-200' => $isReached, 'text-gray-400' => ! $isReached])>{{ $stepStatus->getLabel() }}</p>
                            @if($history)
                                <p class="mt-1 text-[11px] text-gray-500">{{ $history->created_at->format('d M Y, H:i') }}</p>
                                <p class="mt-0.5 text-[11px] text-gray-400">oleh {{ $history->changedBy?->name ?? 'System' }}</p>
                                @if($history->notes)
                                    <p class="mx-auto mt-1 max-w-36 whitespace-normal text-[11px] leading-4 text-gray-500">{{ $history->notes }}</p>
                                @endif
                            @else
                                <p class="mt-1 text-[11px] text-gray-400">Belum diproses</p>
                            @endif
                        </div>
                        @if(! $loop->last)
                            <div @class(['mt-4 h-px w-10 shrink-0', 'bg-blue-300 dark:bg-blue-800' => $isReached, 'bg-gray-200 dark:bg-gray-700' => ! $isReached])></div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    </section>

    @if($record->attachments)
        <section class="rounded-xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Reference & Attachment</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($record->attachments as $path)
                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}" target="_blank" class="rounded-lg border border-gray-200 p-3 text-sm font-semibold text-gray-700 hover:border-blue-300 hover:text-blue-600 dark:border-gray-700 dark:text-gray-300">↗ {{ basename($path) }}</a>
                @endforeach
            </div>
        </section>
    @endif

</div>
</x-filament-panels::page>
