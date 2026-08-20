@php
    $audits = $this->audits;
@endphp

<div>
<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('filament.casual.pages.launcher-page') }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Riwayat Audit</span>
        </div>

        <p class="text-blue-200">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
        <p class="text-xl font-semibold text-white">{{ $audits->count() }} Audit Selesai</p>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        @if($audits->isEmpty())
            <div class="flex flex-col items-center justify-center px-5 py-16 text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Belum ada audit selesai</p>
                <p class="mt-1 text-xs text-slate-400">Audit yang sudah disubmit akan muncul di sini</p>
                <a href="{{ route('filament.casual.pages.quality-control-audits') }}"
                   class="mt-4 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                    Mulai Audit
                </a>
            </div>
        @else
            <div class="flex flex-col gap-3 px-5">
                @foreach($audits as $audit)
                    @php
                        $ratingConfig = match($audit->rating) {
                            'green'  => ['label' => 'Green',  'bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dark' => 'dark:bg-emerald-900/30 dark:text-emerald-400', 'icon' => 'bg-emerald-100 text-emerald-600'],
                            'yellow' => ['label' => 'Yellow', 'bg' => 'bg-amber-100',   'text' => 'text-amber-700',   'dark' => 'dark:bg-amber-900/30 dark:text-amber-400', 'icon' => 'bg-amber-100 text-amber-600'],
                            'red'    => ['label' => 'Red',     'bg' => 'bg-red-100',    'text' => 'text-red-700',     'dark' => 'dark:bg-red-900/30 dark:text-red-400', 'icon' => 'bg-red-100 text-red-600'],
                            default  => ['label' => 'Belum Dinilai', 'bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'dark' => 'dark:bg-gray-800 dark:text-gray-400', 'icon' => 'bg-gray-100 text-gray-500'],
                        };
                    @endphp

                    <a href="{{ \App\Filament\Casual\Pages\QualityControlAuditDetail::getUrl(['record' => $audit->id], panel: 'casual') }}"
                       class="flex items-start gap-3 overflow-hidden rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-200 transition active:scale-[0.98] dark:bg-gray-900 dark:ring-gray-700">
                        <div class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full {{ $ratingConfig['icon'] }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $audit->branch?->name ?? 'Tanpa Store' }}</p>
                                <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $ratingConfig['bg'] }} {{ $ratingConfig['text'] }} {{ $ratingConfig['dark'] }}">
                                    {{ $ratingConfig['label'] }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ number_format((float) $audit->score, 1) }}% · {{ $audit->earned_points }}/{{ $audit->maximum_points }} poin
                            </p>
                            <p class="mt-0.5 text-xs text-slate-400">
                                {{ $audit->audit_date?->format('d M Y') }} · {{ $audit->audit_number }}
                            </p>
                        </div>
                        <svg class="mt-1 h-4 w-4 flex-shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                        </svg>
                    </a>
                @endforeach
            </div>
        @endif

    </div>

</div>

<x-quality-control.bottom-nav active="history" />
</div>
