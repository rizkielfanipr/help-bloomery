@php
    $record = $this->record;
    $status = $record->status->value;
    $canStart = in_array($status, ['submitted', 'scheduled', 're_submitted']);
    $canComplete = $status === 'in_progress' && $record->technician_id === auth()->id();
    $secondaryClass = 'inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-3 text-xs font-semibold text-slate-700 transition hover:border-blue-300 hover:bg-blue-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800';
    $statusClass = match($status) {
        'in_progress' => 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300',
        'scheduled' => 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300',
        're_submitted' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300',
        'warranty' => 'border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-800 dark:bg-purple-950/40 dark:text-purple-300',
        'completed', 'awaiting_verification' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
        default => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
    };
@endphp

<div class="min-h-dvh bg-slate-50 pb-28 dark:bg-gray-950">
    <header class="relative overflow-hidden bg-blue-600 px-5 pb-5 pt-6 text-white">
        <div class="pointer-events-none absolute -right-12 -top-16 h-40 w-40 rounded-full border-[32px] border-white/5" aria-hidden="true"></div>
        <div class="relative flex items-center justify-between gap-3">
            <a href="{{ \App\Filament\Casual\Resources\ServiceRequests\ServiceRequestResource::getUrl('index') }}" aria-label="Kembali ke Logbook Teknisi" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-white/20 bg-white/10 transition hover:bg-white/20"><x-heroicon-o-arrow-left class="h-5 w-5" /></a>
            <span class="font-mono text-xs font-semibold text-blue-100">{{ $record->code }}</span>
        </div>
        <div class="relative mt-4 flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white/15"><x-heroicon-o-wrench-screwdriver class="h-5 w-5" /></div>
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-blue-200">Technician Workspace</p>
                <h1 class="mt-1 text-xl font-bold tracking-tight">Detail Pekerjaan</h1>
                <p class="mt-1 text-xs leading-5 text-blue-100">Informasi kendala, tindak lanjut, dan dokumentasi perbaikan.</p>
            </div>
        </div>
    </header>

    <main class="space-y-5 px-5 py-6">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="p-4">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Informasi Asset</span>
                    <span class="rounded-md border px-2 py-1 text-[10px] font-bold {{ $statusClass }}">{{ $record->status->getLabel() }}</span>
                </div>
                <div class="mt-4 flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 dark:bg-gray-800"><x-heroicon-o-cube class="h-5 w-5" /></div>
                    <div class="min-w-0"><h2 class="text-base font-bold text-slate-900 dark:text-white">{{ $record->asset?->name ?? 'Permintaan Perbaikan' }}</h2><p class="mt-1 font-mono text-[11px] text-slate-500">{{ $record->asset?->asset_number ?? 'Tanpa Asset' }}</p></div>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-4 border-t border-slate-100 pt-4 dark:border-gray-800">
                    <div><dt class="text-[10px] font-semibold text-slate-400">Cabang</dt><dd class="mt-1 text-xs font-semibold text-slate-700 dark:text-gray-200">{{ $record->branch?->name ?? 'Belum Diatur' }}</dd></div>
                    <div><dt class="text-[10px] font-semibold text-slate-400">Status Asset</dt><dd class="mt-1 text-xs font-semibold text-slate-700 dark:text-gray-200">{{ $record->asset?->status ?? 'Tidak Terkait Asset' }}</dd></div>
                    <div><dt class="text-[10px] font-semibold text-slate-400">Jadwal Pengerjaan</dt><dd class="mt-1 text-xs font-semibold text-slate-700 dark:text-gray-200">{{ $record->scheduled_date?->format('d M Y') ?? 'Belum Dijadwalkan' }}</dd></div>
                    <div><dt class="text-[10px] font-semibold text-slate-400">Prioritas</dt><dd class="mt-1 text-xs font-semibold {{ in_array($record->priority, ['high', 'urgent']) ? 'text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-gray-200' }}">{{ ucfirst($record->priority ?? 'normal') }}</dd></div>
                    <div><dt class="text-[10px] font-semibold text-slate-400">Pelapor</dt><dd class="mt-1 text-xs font-semibold text-slate-700 dark:text-gray-200">{{ $record->scheduledBy?->name ?? 'Belum Diatur' }}</dd></div>
                    <div><dt class="text-[10px] font-semibold text-slate-400">Teknisi</dt><dd class="mt-1 text-xs font-semibold text-slate-700 dark:text-gray-200">{{ $record->technician?->name ?? 'Belum Ditugaskan' }}</dd></div>
                </dl>
            </div>
            @if($record->warranty_expires_at)
                <div class="flex items-center gap-2 border-t border-purple-100 bg-purple-50 px-4 py-3 text-xs text-purple-700 dark:border-purple-900 dark:bg-purple-950/30 dark:text-purple-300"><x-heroicon-o-shield-check class="h-4 w-4 shrink-0" />Garansi Berakhir {{ $record->warranty_expires_at->format('d M Y') }}</div>
            @endif
        </section>

        @if($canStart || $canComplete || in_array($status, ['awaiting_parts', 'awaiting_verification']))
            <section class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white"><x-heroicon-o-wrench-screwdriver class="h-4 w-4 text-blue-600" />Tindak Lanjut</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">{{ $canStart ? 'Atur jadwal atau mulai pekerjaan dengan mengisi diagnosis dan foto kondisi awal.' : 'Perbarui proses dan dokumentasikan hasil pengerjaan.' }}</p>
                <div class="mt-4 grid gap-2">
                    @if($canStart)<button type="button" wire:click="mountAction('mulai_kerjakan')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white hover:bg-blue-700"><x-heroicon-o-play class="h-4 w-4" />{{ $status === 're_submitted' ? 'Tangani Pengaduan' : 'Mulai Kerjakan' }}</button>@endif
                    @if($canComplete)<button type="button" wire:click="mountAction('selesai_kerjakan')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white hover:bg-blue-700"><x-heroicon-o-check-circle class="h-4 w-4" />Selesai Kerjakan</button>@endif
                    @if($status === 'awaiting_parts')<button type="button" wire:click="mountAction('resume')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white hover:bg-blue-700"><x-heroicon-o-play class="h-4 w-4" />Lanjutkan Pengerjaan</button>@endif
                    @if($status === 'awaiting_verification')<button type="button" wire:click="mountAction('verify_outsource')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white hover:bg-blue-700"><x-heroicon-o-check-badge class="h-4 w-4" />Verifikasi Report Vendor</button>@endif
                </div>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    @if($canStart)<button type="button" wire:click="mountAction('schedule')" class="{{ $secondaryClass }}"><x-heroicon-o-calendar-days class="h-4 w-4 shrink-0" />Atur Jadwal</button>@endif
                    @if(in_array($status, ['submitted', 'scheduled', 're_submitted', 'in_progress', 'awaiting_parts']))<button type="button" wire:click="mountAction('outsource')" class="{{ $secondaryClass }}"><x-heroicon-o-building-office class="h-4 w-4 shrink-0" />Perlu Outsource</button>@endif
                    @if($canComplete)<button type="button" wire:click="mountAction('awaiting_parts')" class="{{ $secondaryClass }}"><x-heroicon-o-clock class="h-4 w-4 shrink-0" />Menunggu Sparepart</button>@endif
                </div>
            </section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white"><x-heroicon-o-chat-bubble-left-ellipsis class="h-4 w-4 text-blue-600" />Detail Kendala</h2>
            <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-gray-300">{{ $record->requestor_notes ?: 'Belum ada deskripsi kendala.' }}</p>
            <p class="mt-3 text-[10px] text-slate-400">Dilaporkan {{ $record->created_at->format('d M Y, H:i') }}</p>
            @if(! empty($record->attachments))
                <div class="mt-4 grid grid-cols-3 gap-2">
                    @foreach($record->attachments as $attachment)
                        <a href="{{ \Storage::disk('b2')->temporaryUrl($attachment, now()->addHour()) }}" target="_blank" class="aspect-square overflow-hidden rounded-xl border border-slate-200 dark:border-gray-700"><img src="{{ \Storage::disk('b2')->temporaryUrl($attachment, now()->addHour()) }}" alt="Foto kendala {{ $loop->iteration }}" class="h-full w-full object-cover"></a>
                    @endforeach
                </div>
            @endif
            <div class="mt-4 border-t border-slate-100 pt-3 dark:border-gray-800"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Diagnosis Teknisi</p><p class="mt-2 text-sm leading-6 text-slate-600 dark:text-gray-300">{{ $record->diagnosis ?: 'Belum diperiksa. Diagnosis dicatat saat pekerjaan dimulai.' }}</p></div>
        </section>

        @if($record->outsource_reason || $record->outsource_report)
            <section class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white"><x-heroicon-o-building-office class="h-4 w-4 text-blue-600" />Perbaikan Vendor</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-gray-300">{{ $record->outsource_reason }}</p>
                @if($status === 'outsource')<p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs leading-5 text-amber-700 dark:bg-amber-950/30 dark:text-amber-300">Menunggu pelapor melampirkan report hasil perbaikan vendor.</p>@endif
                @if($record->outsource_report)
                    <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-slate-100 pt-3 dark:border-gray-800">
                        <div><dt class="text-[10px] text-slate-400">Nama Vendor</dt><dd class="mt-1 text-xs font-semibold text-slate-700 dark:text-gray-200">{{ $record->outsource_report['vendor'] }}</dd></div>
                        <div><dt class="text-[10px] text-slate-400">Tanggal Perbaikan</dt><dd class="mt-1 text-xs font-semibold text-slate-700 dark:text-gray-200">{{ $record->outsource_report['date'] }}</dd></div>
                        <div><dt class="text-[10px] text-slate-400">Biaya Perbaikan</dt><dd class="mt-1 text-xs font-semibold text-slate-700 dark:text-gray-200">Rp {{ number_format($record->outsource_report['cost'] ?? 0, 0, ',', '.') }}</dd></div>
                    </dl>
                    <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-gray-300">{{ $record->outsource_report['notes'] ?? '' }}</p>
                    <div class="mt-3 space-y-2">@foreach($record->outsource_report['files'] as $file)<a class="flex items-center gap-2 rounded-lg border border-slate-200 p-3 text-xs font-semibold text-blue-600 dark:border-gray-700 dark:text-blue-400" target="_blank" href="{{ \Storage::disk('b2')->temporaryUrl($file, now()->addHour()) }}"><x-heroicon-o-document-text class="h-4 w-4 shrink-0" />Report Vendor {{ $loop->iteration }}<x-heroicon-o-arrow-up-right class="ml-auto h-3.5 w-3.5" /></a>@endforeach</div>
                @endif
                @if($record->verification_notes)<p class="mt-3 border-t border-slate-100 pt-3 text-xs leading-5 text-slate-500 dark:border-gray-800">Catatan Verifikasi: {{ $record->verification_notes }}</p>@endif
            </section>
        @endif
            @if($record->repairs->isNotEmpty() || $record->warranty_claim_notes)
                <div>
                    <p class="mb-3 text-[11px] font-bold uppercase tracking-widest text-gray-400">Riwayat Perbaikan</p>

                    <div class="space-y-0 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-gray-800 dark:bg-gray-900">
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
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white">1</div>
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
                                                <a href="{{ \Storage::disk('b2')->temporaryUrl($attachment, now()->addHour()) }}" target="_blank"
                                                   class="aspect-square overflow-hidden rounded-xl bg-red-100 dark:bg-red-900/20">
                                                    <img src="{{ \Storage::disk('b2')->temporaryUrl($attachment, now()->addHour()) }}" class="h-full w-full object-cover" alt="">
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
                                @if($repair->before_photos !== [])
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        @foreach($repair->before_photos as $photo)
                                            <a href="{{ \Storage::disk('b2')->temporaryUrl($photo, now()->addHour()) }}" target="_blank" class="block aspect-[4/3] overflow-hidden rounded-xl border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                                                <img src="{{ \Storage::disk('b2')->temporaryUrl($photo, now()->addHour()) }}" class="h-full w-full object-cover" alt="Foto Sebelum">
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            {{-- Hasil Perbaikan --}}
                            @if($repair->completed_at)
                                <div class="border-t border-gray-100 px-4 py-4 dark:border-gray-800">
                                    <div class="mb-3 flex items-center gap-2">
                                        <div class="h-2 w-2 rounded-full bg-blue-600"></div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-blue-600">Hasil Perbaikan</p>
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
                                    @if($repair->after_photos !== [])
                                        <div class="mt-3 grid grid-cols-2 gap-2">
                                            @foreach($repair->after_photos as $photo)
                                                <a href="{{ \Storage::disk('b2')->temporaryUrl($photo, now()->addHour()) }}" target="_blank" class="block aspect-[4/3] overflow-hidden rounded-xl border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                                                    <img src="{{ \Storage::disk('b2')->temporaryUrl($photo, now()->addHour()) }}" class="h-full w-full object-cover" alt="Foto Setelah">
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="border-t border-gray-100 px-4 py-4 dark:border-gray-800">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-2 animate-pulse rounded-full bg-blue-400"></div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-blue-500">In Progress</p>
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
                                            <a href="{{ \Storage::disk('b2')->temporaryUrl($attachment, now()->addHour()) }}" target="_blank"
                                               class="aspect-square overflow-hidden rounded-xl bg-red-100 dark:bg-red-900/20">
                                                <img src="{{ \Storage::disk('b2')->temporaryUrl($attachment, now()->addHour()) }}" class="h-full w-full object-cover" alt="">
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

    </main>
    <x-technician.bottom-nav active="jobs" />
    <x-filament-actions::modals />
</div>
