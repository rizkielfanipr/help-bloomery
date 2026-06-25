@php
    $record = $this->record;
    $statusConfig = match($record->status->value) {
        'submitted'   => ['label' => 'Menunggu',   'bg' => 'bg-amber-100',   'text' => 'text-amber-700',   'dark' => 'dark:bg-amber-900/30 dark:text-amber-400'],
        'in_progress' => ['label' => 'Dikerjakan', 'bg' => 'bg-blue-100',    'text' => 'text-blue-700',    'dark' => 'dark:bg-blue-900/30 dark:text-blue-400'],
        'warranty'    => ['label' => 'Garansi',    'bg' => 'bg-purple-100',  'text' => 'text-purple-700',  'dark' => 'dark:bg-purple-900/30 dark:text-purple-400'],
        'completed'   => ['label' => 'Selesai',    'bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dark' => 'dark:bg-emerald-900/30 dark:text-emerald-400'],
        default       => ['label' => $record->status->getLabel(), 'bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'dark' => ''],
    };
    $canStart    = $record->status->value === 'submitted';
    $canComplete = $record->status->value === 'in_progress' && $record->technician_id === auth()->id();
@endphp

<div>
<div class="flex flex-col bg-orange-500" style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ \App\Filament\Technician\Resources\ServiceRequests\ServiceRequestResource::getUrl('index') }}"
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

        <p class="text-orange-200">SR-{{ str_pad($record->id, 4, '0', STR_PAD_LEFT) }}</p>
        <div class="flex items-center gap-2">
            <p class="text-xl font-semibold text-white">{{ $record->scheduled_date?->format('d M Y') ?? '-' }}</p>
            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }}">
                {{ $statusConfig['label'] }}
            </span>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        {{-- Action buttons --}}
        @if($canStart || $canComplete)
            <div class="mx-5 mb-4 flex gap-3">
                @if($canStart)
                    <button wire:click="mountAction('mulai_kerjakan')"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-2xl bg-amber-500 py-3.5 text-sm font-semibold text-white transition active:scale-95 active:bg-amber-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z"/>
                        </svg>
                        Mulai Kerjakan
                    </button>
                @endif
                @if($canComplete)
                    <button wire:click="mountAction('selesai_kerjakan')"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-2xl bg-emerald-600 py-3.5 text-sm font-semibold text-white transition active:scale-95 active:bg-emerald-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                        Selesai Kerjakan
                    </button>
                @endif
            </div>
        @endif

        {{-- Detail card --}}
        <div class="mx-5 overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <p class="font-semibold text-gray-900 dark:text-white">Detail Permintaan</p>
            </div>
            <div class="space-y-4 px-5 py-4">
                @php
                    $infoRows = [
                        [
                            'icon' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                            'label' => 'Status',
                            'value' => $statusConfig['label'],
                        ],
                        [
                            'icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
                            'label' => 'Dijadwalkan Oleh',
                            'value' => $record->scheduledBy?->name ?? '-',
                        ],
                        [
                            'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
                            'label' => 'Tanggal',
                            'value' => $record->scheduled_date?->format('d M Y') ?? '-',
                        ],
                        [
                            'icon' => 'M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z',
                            'label' => 'Teknisi',
                            'value' => $record->technician?->name ?? 'Belum ditugaskan',
                        ],
                        [
                            'icon' => 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
                            'label' => 'Garansi Berakhir',
                            'value' => $record->warranty_expires_at?->format('d M Y H:i') ?? '-',
                        ],
                    ];
                @endphp

                @foreach($infoRows as $row)
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-900/20">
                            <svg class="h-4 w-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $row['icon'] }}"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-400">{{ $row['label'] }}</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $row['value'] }}</p>
                        </div>
                    </div>
                @endforeach

                @if($record->requestor_notes)
                    <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800">
                        <p class="text-xs font-medium text-gray-400">Catatan Pemohon</p>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $record->requestor_notes }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Lampiran --}}
        @if(!empty($record->attachments))
            <div class="mx-5 mt-4 overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <p class="font-semibold text-gray-900 dark:text-white">Lampiran</p>
                </div>
                <div class="flex flex-wrap gap-3 px-5 py-4">
                    @foreach((array) $record->attachments as $attachment)
                        <a href="{{ \Storage::url($attachment) }}" target="_blank"
                           class="block h-24 w-24 overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                            <img src="{{ \Storage::url($attachment) }}" class="h-full w-full object-cover" alt="">
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Riwayat Perbaikan --}}
        @if($record->repairs->isNotEmpty())
            <div class="mx-5 mt-4">
                <p class="mb-3 font-semibold text-gray-900 dark:text-white">Riwayat Perbaikan</p>
                <div class="space-y-3">
                    @foreach($record->repairs as $repair)
                        <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $repair->cycle_label ?? 'Tahap '.$repair->cycle }}</p>
                                @if($repair->completed_at)
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">Selesai</span>
                                @else
                                    <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Dikerjakan</span>
                                @endif
                            </div>
                            <div class="space-y-4 px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-900/20">
                                        <svg class="h-4 w-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-gray-400">Teknisi</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $repair->technician?->name ?? '-' }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-900/20">
                                        <svg class="h-4 w-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-gray-400">Mulai Dikerjakan</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $repair->started_at?->format('d M Y H:i') ?? '-' }}</p>
                                    </div>
                                </div>
                                @if($repair->before_notes)
                                    <div class="rounded-xl bg-amber-50 p-3 dark:bg-amber-900/20">
                                        <p class="text-xs font-medium text-amber-600 dark:text-amber-400">Kondisi Sebelum</p>
                                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $repair->before_notes }}</p>
                                    </div>
                                @endif
                                @if($repair->before_photo)
                                    <a href="{{ \Storage::url($repair->before_photo) }}" target="_blank"
                                       class="block h-40 overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                                        <img src="{{ \Storage::url($repair->before_photo) }}" class="h-full w-full object-cover" alt="Foto Sebelum">
                                    </a>
                                @endif
                                @if($repair->completed_at)
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-900/20">
                                            <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-xs font-medium text-gray-400">Selesai Dikerjakan</p>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $repair->completed_at->format('d M Y H:i') }}</p>
                                        </div>
                                    </div>
                                    @if($repair->warranty_expires_at)
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-900/20">
                                                <svg class="h-4 w-4 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-gray-400">Garansi Hingga</p>
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $repair->warranty_expires_at->format('d M Y') }}</p>
                                            </div>
                                        </div>
                                    @endif
                                    @if($repair->after_notes)
                                        <div class="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-900/20">
                                            <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Kondisi Setelah</p>
                                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $repair->after_notes }}</p>
                                        </div>
                                    @endif
                                    @if($repair->after_photo)
                                        <a href="{{ \Storage::url($repair->after_photo) }}" target="_blank"
                                           class="block h-40 overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                                            <img src="{{ \Storage::url($repair->after_photo) }}" class="h-full w-full object-cover" alt="Foto Setelah">
                                        </a>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>

    <x-technician.bottom-nav active="jobs" />

</div>

{{-- Required for Filament action modals to render --}}
<x-filament-actions::modals />
</div>
