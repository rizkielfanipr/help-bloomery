@php
    $record        = $this->record;
    $isResubmitted = $record->status->value === 're_submitted';
    $canStart      = in_array($record->status->value, ['submitted', 're_submitted']);
    $canComplete   = $record->status->value === 'in_progress' && $record->technician_id === auth()->id();

    $statusConfig = match($record->status->value) {
        'submitted'    => ['label' => 'Menunggu',        'bg' => 'bg-amber-100',   'text' => 'text-amber-700'],
        'in_progress'  => ['label' => 'Dikerjakan',      'bg' => 'bg-blue-100',    'text' => 'text-blue-700'],
        'warranty'     => ['label' => 'Garansi',         'bg' => 'bg-purple-100',  'text' => 'text-purple-700'],
        're_submitted' => ['label' => 'Pengaduan Ulang', 'bg' => 'bg-red-100',     'text' => 'text-red-700'],
        'completed'    => ['label' => 'Selesai',         'bg' => 'bg-emerald-100', 'text' => 'text-emerald-700'],
        default        => ['label' => $record->status->getLabel(), 'bg' => 'bg-gray-100', 'text' => 'text-gray-600'],
    };
@endphp

<div>
<div class="flex flex-col bg-orange-500" style="min-height:100dvh">

    {{-- HEADER --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ \App\Filament\Casual\Resources\ServiceRequests\ServiceRequestResource::getUrl('index') }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Detail Pekerjaan</span>
        </div>
        <p class="text-sm text-orange-200">SR-{{ str_pad($record->id, 4, '0', STR_PAD_LEFT) }}</p>
        <div class="mt-1 flex items-center gap-2">
            <p class="text-xl font-semibold text-white">{{ $record->scheduled_date?->format('d M Y') ?? '-' }}</p>
            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }}">
                {{ $statusConfig['label'] }}
            </span>
        </div>
    </div>

    {{-- CONTENT --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 dark:bg-gray-950">
        <div class="space-y-6 px-5 pb-28 pt-6">

            {{-- Action buttons --}}
            @if($canStart || $canComplete)
                <div class="flex gap-3">
                    @if($canStart)
                        <button wire:click="mountAction('mulai_kerjakan')"
                                class="flex flex-1 items-center justify-center gap-2 rounded-2xl py-3.5 text-sm font-semibold text-white transition active:scale-95
                                    {{ $isResubmitted ? 'bg-red-600 active:bg-red-700' : 'bg-amber-500 active:bg-amber-600' }}">
                            @if($isResubmitted)
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                                </svg>
                                Tangani Pengaduan
                            @else
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z"/>
                                </svg>
                                Mulai Kerjakan
                            @endif
                        </button>
                    @endif
                    @if($canComplete)
                        <button wire:click="mountAction('selesai_kerjakan')"
                                class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-emerald-600 py-3.5 text-sm font-semibold text-white transition active:scale-95 active:bg-emerald-700">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                            Selesai Kerjakan
                        </button>
                    @endif
                </div>
            @endif

            {{-- ══════════════════════════════════
                 SECTION 1 — DETAIL PERMINTAAN
            ══════════════════════════════════ --}}
            <div>
                <p class="mb-3 text-[11px] font-bold uppercase tracking-widest text-gray-400">Detail Permintaan</p>

                <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">

                    {{-- Key info grid --}}
                    <div class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800">
                        <div class="bg-white px-4 py-3 dark:bg-gray-900">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Dijadwalkan Oleh</p>
                            <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ $record->scheduledBy?->name ?? '-' }}</p>
                        </div>
                        <div class="bg-white px-4 py-3 dark:bg-gray-900">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Tanggal</p>
                            <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ $record->scheduled_date?->format('d M Y') ?? '-' }}</p>
                        </div>
                        <div class="bg-white px-4 py-3 dark:bg-gray-900">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Teknisi</p>
                            <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ $record->technician?->name ?? 'Belum ditugaskan' }}</p>
                        </div>
                        @if($record->warranty_expires_at)
                            <div class="bg-white px-4 py-3 dark:bg-gray-900">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Garansi Berakhir</p>
                                <p class="mt-0.5 text-sm font-semibold text-purple-700 dark:text-purple-400">{{ $record->warranty_expires_at->format('d M Y') }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Catatan Pemohon --}}
                    @if($record->requestor_notes)
                        <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Catatan Pemohon</p>
                            <p class="mt-1 text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $record->requestor_notes }}</p>
                        </div>
                    @endif

                    {{-- Foto Lampiran --}}
                    @if(!empty($record->attachments))
                        <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                            <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Foto Lampiran</p>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach($record->attachments as $attachment)
                                    <a href="{{ \Storage::url($attachment) }}" target="_blank"
                                       class="aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                                        <img src="{{ \Storage::url($attachment) }}" class="h-full w-full object-cover" alt="">
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

            </div>

            {{-- ══════════════════════════════════
                 SECTION 2 — TINDAK LANJUT
            ══════════════════════════════════ --}}
            @if($record->repairs->isNotEmpty() || $record->warranty_claim_notes)
                <div>
                    <p class="mb-3 text-[11px] font-bold uppercase tracking-widest text-gray-400">Tindak Lanjut</p>

                    <div class="space-y-0 overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                        @foreach($record->repairs as $repair)
                            @php
                                $isFirst = $repair->cycle === 1;
                                $cycleNum = $repair->cycle;
                            @endphp

                            {{-- Separator label siklus --}}
                            @if(!$loop->first)
                                <div class="h-px bg-gray-100 dark:bg-gray-800"></div>
                            @endif
                            <div class="flex items-center gap-3 bg-gray-50 px-4 py-3 dark:bg-gray-800/50">
                                @if($isFirst)
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-orange-500 text-[10px] font-bold text-white">1</div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Perbaikan Pertama</p>
                                @else
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">{{ $cycleNum }}</div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-red-500">Pengaduan Ulang #{{ $cycleNum - 1 }}</p>
                                @endif
                                @if($repair->technician)
                                    <p class="ml-auto text-xs text-gray-400">{{ $repair->technician->name }}</p>
                                @endif
                            </div>

                            {{-- Kendala garansi user (siklus > 1) --}}
                            @if($repair->warranty_claim_notes)
                                <div class="bg-red-50 px-4 py-3 dark:bg-red-900/10">
                                    <div class="mb-2 flex items-center gap-1.5">
                                        <svg class="h-3.5 w-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                                        </svg>
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-red-600">Kendala yang Dilaporkan</p>
                                    </div>
                                    <p class="text-sm leading-relaxed text-red-800 dark:text-red-300">{{ $repair->warranty_claim_notes }}</p>
                                    @if(!empty($repair->warranty_claim_attachments))
                                        <div class="mt-2 grid grid-cols-3 gap-2">
                                            @foreach($repair->warranty_claim_attachments as $attachment)
                                                <a href="{{ \Storage::url($attachment) }}" target="_blank"
                                                   class="aspect-square overflow-hidden rounded-xl bg-red-100 dark:bg-red-900/20">
                                                    <img src="{{ \Storage::url($attachment) }}" class="h-full w-full object-cover" alt="">
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Pengecekan Awal --}}
                            <div class="px-4 py-4">
                                <div class="mb-3 flex items-center gap-2">
                                    <div class="h-2 w-2 rounded-full bg-amber-400"></div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-amber-600">Pengecekan Awal</p>
                                    @if($repair->started_at)
                                        <p class="ml-auto text-xs text-gray-400">{{ $repair->started_at->format('d M, H:i') }}</p>
                                    @endif
                                </div>
                                @if($repair->before_notes)
                                    <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $repair->before_notes }}</p>
                                @endif
                                @if($repair->before_photo)
                                    <a href="{{ \Storage::url($repair->before_photo) }}" target="_blank"
                                       class="mt-3 block overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                                        <img src="{{ \Storage::url($repair->before_photo) }}" class="h-44 w-full object-cover" alt="Foto Sebelum">
                                    </a>
                                @endif
                            </div>

                            {{-- Hasil Perbaikan --}}
                            @if($repair->completed_at)
                                <div class="border-t border-gray-100 px-4 py-4 dark:border-gray-800">
                                    <div class="mb-3 flex items-center gap-2">
                                        <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Hasil Perbaikan</p>
                                        <p class="ml-auto text-xs text-gray-400">{{ $repair->completed_at->format('d M, H:i') }}</p>
                                    </div>
                                    @if($repair->warranty_expires_at)
                                        <div class="mb-3 inline-flex items-center gap-1.5 rounded-lg bg-purple-50 px-3 py-1.5 dark:bg-purple-900/20">
                                            <svg class="h-3.5 w-3.5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                                            </svg>
                                            <p class="text-xs font-semibold text-purple-700 dark:text-purple-400">Garansi s/d {{ $repair->warranty_expires_at->format('d M Y') }}</p>
                                        </div>
                                    @endif
                                    @if($repair->after_notes)
                                        <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $repair->after_notes }}</p>
                                    @endif
                                    @if($repair->after_photo)
                                        <a href="{{ \Storage::url($repair->after_photo) }}" target="_blank"
                                           class="mt-3 block overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                                            <img src="{{ \Storage::url($repair->after_photo) }}" class="h-44 w-full object-cover" alt="Foto Setelah">
                                        </a>
                                    @endif
                                </div>
                            @else
                                <div class="border-t border-gray-100 px-4 py-4 dark:border-gray-800">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-2 animate-pulse rounded-full bg-blue-400"></div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-blue-500">Sedang Dikerjakan</p>
                                    </div>
                                </div>
                            @endif

                        @endforeach

                        {{-- Pengaduan ulang pending (belum ditangani teknisi) --}}
                        @if($record->warranty_claim_notes && $record->status->value === 're_submitted')
                            @php $nextNum = $record->repairs->count() + 1; @endphp
                            <div class="h-px bg-gray-100 dark:bg-gray-800"></div>
                            <div class="flex items-center gap-3 bg-gray-50 px-4 py-3 dark:bg-gray-800/50">
                                <div class="flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">{{ $nextNum }}</div>
                                <p class="text-xs font-bold uppercase tracking-wider text-red-500">Pengaduan Ulang #{{ $nextNum - 1 }}</p>
                            </div>
                            <div class="bg-red-50 px-4 py-3 dark:bg-red-900/10">
                                <div class="mb-2 flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                                    </svg>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-red-600">Kendala yang Dilaporkan</p>
                                </div>
                                <p class="text-sm leading-relaxed text-red-800 dark:text-red-300">{{ $record->warranty_claim_notes }}</p>
                                @if(!empty($record->warranty_claim_attachments))
                                    <div class="mt-2 grid grid-cols-3 gap-2">
                                        @foreach($record->warranty_claim_attachments as $attachment)
                                            <a href="{{ \Storage::url($attachment) }}" target="_blank"
                                               class="aspect-square overflow-hidden rounded-xl bg-red-100 dark:bg-red-900/20">
                                                <img src="{{ \Storage::url($attachment) }}" class="h-full w-full object-cover" alt="">
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-2 animate-pulse rounded-full bg-amber-400"></div>
                                    <p class="text-xs font-semibold text-amber-600">Menunggu teknisi mulai mengerjakan</p>
                                </div>
                            </div>
                        @endif

                    </div>
                </div>
            @endif

        </div>
    </div>

    <x-technician.bottom-nav active="jobs" />

</div>

<x-filament-actions::modals />
</div>
