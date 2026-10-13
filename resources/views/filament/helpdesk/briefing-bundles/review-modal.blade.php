{{-- docs/daily-briefing-bundle-approval-prd.md §13: every value comes from BriefingBundleDetailBuilder; no queries here. --}}
@php
    $bundle = $detail['bundle'];
    $completeness = $detail['completeness'];
    $stateBadge = [
        'complete' => ['Lengkap', 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'],
        'correction' => ['Perlu Diperbaiki', 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'],
        'missing' => ['Belum Diisi', 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'],
    ];
@endphp

<div class="space-y-5">
    <dl class="grid grid-cols-2 gap-3 rounded-xl border border-gray-200 p-4 text-sm sm:grid-cols-4 dark:border-gray-700">
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">Periode</dt>
            <dd class="font-medium text-gray-900 dark:text-white">
                {{ $bundle->period_start?->format('d M Y') }}@if($bundle->period_end && ! $bundle->period_end->equalTo($bundle->period_start)) – {{ $bundle->period_end->format('d M Y') }}@endif
            </dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">Status</dt>
            <dd><x-filament::badge :color="$bundle->status->getColor()">{{ $bundle->status->getLabel() }}</x-filament::badge></dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">Progres Poin</dt>
            <dd class="font-medium text-gray-900 dark:text-white">{{ $completeness->completed }}/{{ $completeness->required }}</dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">Waktu Lengkap</dt>
            <dd class="font-medium text-gray-900 dark:text-white">{{ $bundle->completed_at?->format('d M Y H:i') ?? '-' }}</dd>
        </div>
        <div class="col-span-2">
            <dt class="text-xs text-gray-500 dark:text-gray-400">Kontributor</dt>
            <dd class="text-gray-900 dark:text-white">{{ $detail['contributors'] ? implode(', ', $detail['contributors']) : '-' }}</dd>
        </div>
        <div class="col-span-2">
            <dt class="text-xs text-gray-500 dark:text-gray-400">Reviewer</dt>
            <dd class="text-gray-900 dark:text-white">{{ $bundle->reviewer ? $bundle->reviewer->name.' · '.$bundle->reviewed_at?->format('d M Y H:i') : 'Belum direview' }}</dd>
        </div>
        @if($bundle->review_notes)
            <div class="col-span-2 sm:col-span-4">
                <dt class="text-xs text-gray-500 dark:text-gray-400">Catatan Review</dt>
                <dd class="text-gray-900 dark:text-white">{{ $bundle->review_notes }}</dd>
            </div>
        @endif
    </dl>

    @if(! $completeness->isComplete() && $bundle->status === \App\Enums\BriefingBundleStatus::Draft)
        <p role="status" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
            Bundle belum lengkap, sehingga belum dapat disetujui atau ditolak.
        </p>
    @endif

    <ol class="space-y-3" aria-label="Poin briefing">
        @foreach($detail['points'] as $point)
            <li class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900 dark:text-white">{{ $point['label'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $point['type'] }}@unless($point['required']) · opsional @endunless</p>
                    </div>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $stateBadge[$point['state']][1] }}">{{ $stateBadge[$point['state']][0] }}</span>
                </div>

                @if($point['submittedBy'] || $point['submittedAt'])
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Dikirim{{ $point['submittedBy'] ? ' oleh '.$point['submittedBy'] : '' }}{{ $point['submittedAt'] ? ' · '.$point['submittedAt'] : '' }}
                    </p>
                @endif

                @if($point['correctionNote'] && $point['state'] === 'correction')
                    <p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-800 dark:bg-red-900/30 dark:text-red-200">{{ $point['correctionNote'] }}</p>
                @endif

                @if($point['notes'])
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-800 dark:text-gray-200">{{ $point['notes'] }}</p>
                @endif

                @if($point['photoUrls'] !== [])
                    <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach($point['photoUrls'] as $index => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg bg-black">
                                <img src="{{ $url }}" alt="Foto bukti {{ $point['label'] }} {{ $index + 1 }}" loading="lazy" class="aspect-square w-full object-contain">
                            </a>
                        @endforeach
                    </div>
                @endif
            </li>
        @endforeach
    </ol>

    @if($detail['history'] !== [])
        <details class="rounded-xl border border-gray-200 p-4 text-sm dark:border-gray-700">
            <summary class="cursor-pointer font-medium text-gray-900 dark:text-white">Riwayat status</summary>
            <ol class="mt-3 space-y-2">
                @foreach($detail['history'] as $entry)
                    <li class="text-xs text-gray-600 dark:text-gray-300">
                        <span class="font-medium">{{ $entry['at'] }}</span> · {{ $entry['from'] ?? '—' }} → {{ $entry['to'] }}{{ $entry['actor'] ? ' · '.$entry['actor'] : '' }}
                        @if($entry['reason'])<span class="block text-gray-500 dark:text-gray-400">{{ $entry['reason'] }}</span>@endif
                    </li>
                @endforeach
            </ol>
        </details>
    @endif

    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
        {{ $action->getModalAction('reopen') }}
        {{ $action->getModalAction('reject') }}
        {{ $action->getModalAction('approve') }}
    </div>
</div>
