<x-filament-panels::page>
    @php
        $audit = $this->getRecord();
        $sections = $audit->items->groupBy('section_code');
        $answeredCount = $audit->items->whereNotNull('result')->count();
        $sectionClass = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6';
        $labelClass = 'text-xs font-semibold text-slate-500 dark:text-slate-400';
        $valueClass = 'mt-0.5 text-sm font-medium text-gray-900 dark:text-white';
        $ratingLabel = match ($audit->rating) {
            'green' => 'Green', 'yellow' => 'Yellow', 'red' => 'Red', default => 'Belum Dinilai',
        };
        $typeLabel = match ($audit->audit_type) {
            'routine' => 'Rutin', 'follow_up' => 'Follow Up', 'surprise' => 'Surprise Audit', default => $audit->audit_type,
        };
    @endphp

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-5 p-5 sm:p-6 md:flex-row md:items-center md:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                        <x-heroicon-o-shield-check class="h-6 w-6" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Quality Control · Audit</p>
                        <h2 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">Detail Audit Quality Control</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $audit->branch?->name ?? 'Store tidak tersedia' }} · {{ $audit->audit_date?->format('d M Y') ?? 'Tanggal belum diisi' }} · {{ $typeLabel }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-col items-start gap-2 md:items-end">
                    <span class="rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 font-mono text-sm font-semibold text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300">{{ $audit->audit_number }}</span>
                    <a href="{{ \App\Filament\Helpdesk\Resources\QualityControlAudits\QualityControlAuditResource::getUrl('index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"><x-heroicon-o-arrow-left class="h-4 w-4" />Kembali ke Daftar</a>
                </div>
            </div>
            <div class="flex items-start gap-2 border-t border-gray-200 bg-gray-50/60 px-5 py-3 text-xs leading-5 text-gray-500 dark:border-gray-700 dark:bg-gray-800/30 dark:text-gray-400 sm:px-6"><x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" /><span>Rincian hasil audit dan checklist pemeriksaan store. Status audit: <strong>{{ $audit->status === 'submitted' ? 'Submitted' : 'Draft' }}</strong>.</span></div>
        </section>

        <div class="grid gap-4 lg:grid-cols-[1fr_2fr]">
            <section class="rounded-2xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-900 dark:bg-blue-950/30">
                <div class="flex items-center gap-2 text-sm font-semibold text-blue-700 dark:text-blue-300"><x-heroicon-o-presentation-chart-line class="h-5 w-5" />Hasil Penilaian</div>
                <p class="mt-3 text-3xl font-bold tracking-tight text-blue-900 dark:text-blue-100 sm:text-4xl">{{ number_format((float) $audit->score, 2, ',', '.') }}%</p>
                <p class="mt-3 text-xs leading-5 text-blue-700 dark:text-blue-300">{{ $ratingLabel }} · {{ $audit->earned_points ?? 0 }}/{{ $audit->maximum_points ?? 0 }} poin</p>
            </section>
            <div class="grid grid-cols-2 gap-3">
                <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><div class="flex items-start justify-between gap-2"><p class="text-xs text-gray-500 dark:text-gray-400">Poin Dinilai</p><x-heroicon-o-check-circle class="h-4 w-4 shrink-0 text-gray-400" /></div><p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $answeredCount }}/{{ $audit->items->count() }}</p></section>
                <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><div class="flex items-start justify-between gap-2"><p class="text-xs text-gray-500 dark:text-gray-400">Section Audit</p><x-heroicon-o-clipboard-document-list class="h-4 w-4 shrink-0 text-gray-400" /></div><p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $sections->count() }}</p></section>
            </div>
        </div>

        <div class="flex flex-col gap-4">
                <section class="{{ $sectionClass }}">
                    <h2 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Info Audit</h2>
                    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <div><dt class="{{ $labelClass }}">Tanggal</dt><dd class="{{ $valueClass }}">{{ $audit->audit_date?->format('d M Y') ?? '—' }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Auditor</dt><dd class="{{ $valueClass }}">{{ $audit->auditor?->name ?? '—' }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Jenis Audit</dt><dd class="{{ $valueClass }}">{{ $typeLabel }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Store Leader</dt><dd class="{{ $valueClass }}">{{ $audit->store_leader_name ?: '—' }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Kehadiran Store Leader</dt><dd class="{{ $valueClass }}">{{ $audit->store_leader_present ? 'Hadir' : 'Tidak hadir' }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Disubmit</dt><dd class="{{ $valueClass }}">{{ $audit->submitted_at?->format('d M Y, H:i') ?? 'Belum disubmit' }}</dd></div>
                    </dl>
                </section>

                <section class="{{ $sectionClass }}">
                    <h2 class="mb-4 text-base font-bold text-gray-900 dark:text-white">Ringkasan Audit</h2>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div><p class="{{ $labelClass }}">Top 3 Findings</p><p class="{{ $valueClass }} whitespace-pre-line">{{ $audit->top_findings ?: '—' }}</p></div>
                        <div><p class="{{ $labelClass }}">Corrective Action Required</p><p class="{{ $valueClass }} whitespace-pre-line">{{ $audit->corrective_action_required ?: '—' }}</p></div>
                        <div><p class="{{ $labelClass }}">Overall Notes</p><p class="{{ $valueClass }} whitespace-pre-line">{{ $audit->overall_notes ?: '—' }}</p></div>
                    </div>
                </section>

                <section class="{{ $sectionClass }}">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Checklist Audit</h2>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $answeredCount }} dari {{ $audit->items->count() }} poin dinilai</span>
                    </div>
                    <div class="flex flex-col gap-5">
                        @forelse($sections as $sectionCode => $items)
                            <div>
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $sectionCode }} · {{ $items->first()->section_name }}</h3>
                                    <span class="shrink-0 text-xs text-gray-500">{{ $items->whereNotNull('result')->count() }}/{{ $items->count() }}</span>
                                </div>
                                <div class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
                                    @foreach($items as $item)
                                        <details class="group bg-white dark:bg-gray-900" wire:key="audit-item-{{ $item->id }}">
                                            <summary class="flex cursor-pointer list-none items-start gap-3 p-4 marker:content-none hover:bg-gray-50 dark:hover:bg-gray-800/50 [&::-webkit-details-marker]:hidden">
                                                <div @class([
                                                    'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                                    'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' => $item->result !== null,
                                                    'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => $item->result === null,
                                                ])>{{ $loop->iteration }}</div>
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $item->question }}</p>
                                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ $item->result !== null ? $item->earned_points.'/'.$item->maximum_points.' poin' : 'Belum Dinilai' }}</span>
                                                        @if($item->is_critical)<span class="rounded-md bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-300">Critical</span>@endif
                                                        @if($item->requires_photo)<span class="rounded-md bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">Wajib Foto</span>@endif
                                                    </div>
                                                </div>
                                                <x-heroicon-o-chevron-down class="mt-1 h-5 w-5 shrink-0 text-gray-400 transition-transform group-open:rotate-180" />
                                            </summary>

                                            <div class="border-t border-gray-100 bg-gray-50/60 p-4 dark:border-gray-800 dark:bg-gray-800/20 sm:pl-14">
                                                @if(\App\Filament\Helpdesk\Resources\QualityControlAudits\QualityControlAuditResource::canEdit($audit))
                                                    <div class="mb-4 flex justify-end">
                                                        <button type="button" wire:click="mountAction('editItem', { item: {{ $item->id }} })" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700">
                                                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                                                            Edit Poin
                                                        </button>
                                                    </div>
                                                @endif
                                                @if($item->check_procedure)
                                                    <p class="mb-4 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $item->check_procedure }}</p>
                                                @endif

                                                <dl class="grid gap-4 sm:grid-cols-2">
                                                    <div>
                                                        <dt class="{{ $labelClass }}">Poin</dt>
                                                        <dd class="{{ $valueClass }}">{{ $item->result !== null ? $item->earned_points.'/'.$item->maximum_points.' poin' : 'Belum dinilai · Maksimal '.$item->maximum_points.' poin' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="{{ $labelClass }}">Catatan</dt>
                                                        <dd class="{{ $valueClass }} whitespace-pre-line">{{ $item->notes ?: '—' }}</dd>
                                                    </div>
                                                </dl>

                                                @if($item->evidence_photos)
                                                    <div class="mt-5">
                                                        <p class="{{ $labelClass }}">Foto Bukti</p>
                                                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                                            @foreach($item->evidence_photos as $photo)
                                                                @php($photoUrl = \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($photo, now()->addHour()))
                                                                <a href="{{ $photoUrl }}" target="_blank" rel="noopener" class="group/photo relative aspect-square overflow-hidden rounded-xl border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                                                                    <img src="{{ $photoUrl }}" alt="Foto Bukti {{ $loop->iteration }}" loading="lazy" class="h-full w-full object-cover transition group-hover/photo:scale-105" />
                                                                    <span class="absolute inset-x-0 bottom-0 bg-black/60 px-2 py-1.5 text-center text-xs font-semibold text-white">Lihat foto {{ $loop->iteration }} ↗</span>
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="mt-5">
                                                        <p class="{{ $labelClass }}">Foto Bukti</p>
                                                        <p class="{{ $valueClass }}">Tidak ada foto.</p>
                                                    </div>
                                                @endif
                                            </div>
                                        </details>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <p class="rounded-xl bg-gray-50 px-4 py-6 text-center text-sm text-gray-500 dark:bg-gray-800">Belum ada poin audit.</p>
                        @endforelse
                    </div>
                </section>
        </div>
    </div>
</x-filament-panels::page>
