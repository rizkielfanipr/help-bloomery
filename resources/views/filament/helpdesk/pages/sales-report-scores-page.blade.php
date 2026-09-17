<x-filament-panels::page>
    @php
        $scores = $this->scores;
        $totals = collect($scores);
        $requiredTotal = $totals->sum('required');
        $passedTotal = $totals->sum('passed');
        $overallScore = $requiredTotal ? number_format($passedTotal / $requiredTotal * 100, 2, ',', '.') . '%' : '—';
        $periodLabel = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month)->translatedFormat('F Y') : 'Pilih Bulan';
        $inputClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
    @endphp
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-5 p-5 sm:p-6 md:flex-row md:items-center md:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300"><x-heroicon-o-chart-bar class="h-6 w-6" /></div>
                    <div><p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Finance · Reporting Compliance</p><h2 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">Sales Report Scores</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">Pantau kepatuhan laporan harian setiap cabang, lihat hasil approval, dan kelola periode penilaian dalam satu workspace.</p></div>
                </div>
                <div class="flex shrink-0 items-center gap-2 self-start rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300"><x-heroicon-o-calendar-days class="h-4 w-4" />{{ $periodLabel }}</div>
            </div>
            <div class="flex items-start gap-2 border-t border-gray-200 bg-gray-50/60 px-5 py-3 text-xs leading-5 text-gray-500 dark:border-gray-700 dark:bg-gray-800/30 dark:text-gray-400 sm:px-6"><x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" /><span>Approved Supervisor atau Completed bernilai 100 per hari. Rejected, belum approved, dan tidak input bernilai 0. Bulan berjalan dihitung sampai kemarin.</span></div>
        </section>

        <section class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:grid-cols-2 sm:p-5 lg:grid-cols-[1fr_1fr_2fr]">
            <div><label for="score-month" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Bulan Penilaian</label><input id="score-month" aria-label="Bulan Penilaian" type="month" wire:model.live="month" class="{{ $inputClass }}"></div>
            <div><label for="score-branch" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Cabang</label><select id="score-branch" aria-label="Cabang" wire:model.live="branchFilter" class="{{ $inputClass }}"><option value="">Semua Cabang</option>@foreach($this->branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
            <div class="flex items-center gap-3 rounded-xl bg-gray-50 p-3 text-sm text-gray-500 dark:bg-gray-800/50 dark:text-gray-400 sm:col-span-2 lg:col-span-1"><x-heroicon-o-calculator class="h-5 w-5 shrink-0 text-blue-500" /><div><p class="font-semibold text-gray-700 dark:text-gray-200">Hari Berhasil ÷ Hari Wajib × 100%</p><p class="mt-1 text-xs leading-5">Weekend tetap dihitung. Tanggal tutup dan tanggal sebelum mulai penilaian dikecualikan.</p></div></div>
        </section>

        <section class="grid gap-4 lg:grid-cols-[1fr_2fr]">
            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-900 dark:bg-blue-950/30"><div class="flex items-center gap-2 text-sm font-semibold text-blue-700 dark:text-blue-300"><x-heroicon-o-presentation-chart-line class="h-5 w-5" />Nilai Gabungan</div><p class="mt-3 text-4xl font-bold tracking-tight text-blue-900 dark:text-blue-100">{{ $overallScore }}</p><p class="mt-3 text-xs leading-5 text-blue-700 dark:text-blue-300">{{ $passedTotal }} hari berhasil dari {{ $requiredTotal }} hari wajib pada cabang yang dipilih. Dihitung dari total hari, bukan rata-rata persentase cabang.</p></div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach([['Hari Wajib',$requiredTotal,'calendar-days'],['Approved / Completed',$passedTotal,'check-circle'],['Rejected',$totals->sum('rejected'),'x-circle'],['Tidak Input',$totals->sum('missing'),'document-minus'],['Belum Approved',$totals->sum('pending'),'clock'],['Cabang',$totals->count(),'building-storefront']] as [$label,$value,$icon])
                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><div class="flex items-start justify-between gap-2"><p class="text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $label }}</p><x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4 shrink-0 text-gray-400" /></div><p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $value }}</p></div>
                @endforeach
            </div>
        </section>
        <div class="flex items-center justify-between gap-3"><div><h3 class="font-bold text-gray-900 dark:text-white">Penilaian Per Cabang</h3><p class="mt-1 text-sm text-gray-500">Lihat rincian harian atau atur tanggal mulai dan pengecualian cabang.</p></div><span wire:loading wire:target="month,branchFilter" class="text-xs text-blue-600">Memuat penilaian…</span></div>
        <div class="grid gap-4 xl:grid-cols-2">
            @forelse($scores as $score)
                <section wire:key="score-branch-{{ $score['branch_id'] }}" class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-4 p-5"><div class="flex min-w-0 items-start gap-3"><div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-50 text-blue-600 dark:bg-gray-800 dark:text-blue-300"><x-heroicon-o-building-storefront class="h-5 w-5" /></div><div class="min-w-0"><h4 class="break-words font-bold text-gray-900 dark:text-white">{{ $score['name'] }}</h4><p class="mt-1 text-xs text-gray-500">Mulai Penilaian · {{ $score['started_at'] ? \Carbon\CarbonImmutable::parse($score['started_at'])->translatedFormat('d M Y') : 'Belum Diatur' }}</p></div></div><div class="shrink-0 text-right"><p class="text-xs text-gray-500">Nilai</p><p class="mt-1 text-xl font-bold text-blue-700 dark:text-blue-300">{{ $score['score'] !== null ? number_format($score['score'],2,',','.').'%' : '—' }}</p></div></div>
                    <div class="px-5 pb-5"><div class="h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800" role="progressbar" aria-label="Nilai {{ $score['name'] }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $score['score'] ?? 0 }}"><div class="h-full rounded-full bg-blue-500" style="width: {{ $score['score'] ?? 0 }}%"></div></div><p class="mt-2 text-xs text-gray-500">{{ $score['passed'] }} hari berhasil / {{ $score['required'] }} hari wajib</p>
                        <div class="mt-4 grid grid-cols-3 gap-2 rounded-xl bg-gray-50 p-3 dark:bg-gray-800/40">@foreach(['rejected'=>'Rejected','missing'=>'Tidak Input','pending'=>'Belum Approved'] as $key=>$label)<div><p class="text-xs leading-5 text-gray-500">{{ $label }}</p><p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $score[$key] }}</p></div>@endforeach</div>
                        @if($score['started_at'] === null)<p class="mt-3 text-xs text-amber-700 dark:text-amber-300">Atur Mulai Penilaian agar nilai cabang dapat dihitung.</p>@elseif($score['required'] === 0)<p class="mt-3 text-xs text-gray-500">Belum ada hari wajib pada periode ini.</p>@endif
                    </div>
                    <div class="flex flex-wrap items-center gap-2 border-t border-gray-200 px-5 py-3 dark:border-gray-700"><button type="button" wire:click="showDetail({{ $score['branch_id'] }})" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"><x-heroicon-o-calendar-days class="h-4 w-4" />Detail Harian</button>@can('edit sales report assessment settings')<button type="button" wire:click="openSettings({{ $score['branch_id'] }})" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold text-blue-600 transition hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-950/30"><x-heroicon-o-adjustments-horizontal class="h-4 w-4" />Pengaturan</button>@endcan</div>
                </section>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 xl:col-span-2"><x-heroicon-o-building-storefront class="mx-auto mb-3 h-8 w-8 text-gray-400" />Tidak ada cabang atau periode belum valid.</div>
            @endforelse
        </div>
    </div>

    @if(isset($scores[$detailBranchId]))
        @php
            $detail = $scores[$detailBranchId];
        @endphp
        <div data-testid="daily-score-modal" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeDetail()" class="fixed inset-0 z-[130] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Detail Harian">
            <button type="button" wire:click="closeDetail" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup modal detail harian"></button>
            <x-rnd.picker-modal title="Rincian Harian · {{ $detail['name'] }}" description="{{ $periodLabel }} · Satu tanggal bernilai maksimal 100. Shift mengikuti konfigurasi cabang saat ini.">
                <x-slot:close><button type="button" wire:click="closeDetail" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup detail harian"><x-heroicon-o-x-mark class="h-5 w-5" /></button></x-slot:close>
                <div class="flex flex-wrap gap-3 border-b border-gray-200 bg-gray-50/60 px-5 py-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-800/30"><span>Nilai <strong class="text-blue-700 dark:text-blue-300">{{ $detail['score'] !== null ? number_format($detail['score'],2,',','.').'%' : '—' }}</strong></span><span>{{ $detail['passed'] }} hari berhasil / {{ $detail['required'] }} hari wajib</span></div>
                <div class="overflow-y-auto p-5"><div class="space-y-2">
                    @foreach($detail['days'] as $day)
                        @php
                            $statusLabel = ['pending_finance'=>'Approved Supervisor','completed'=>'Completed','rejected'=>'Rejected','draft'=>'Draft','pending_supervisor'=>'Menunggu Supervisor','missing'=>'Tidak Input'][$day['status']] ?? $day['status'];
                            $statusColor = !$day['required'] ? 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' : ($day['score'] === 100 ? 'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-300' : ($day['status'] === 'rejected' ? 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'));
                        @endphp
                        <div class="flex flex-col gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between"><div class="flex items-start gap-3"><div class="w-12 shrink-0 rounded-lg bg-gray-50 py-2 text-center dark:bg-gray-800"><p class="text-lg font-bold text-gray-900 dark:text-white">{{ substr($day['date'],8,2) }}</p><p class="text-xs text-gray-500">{{ \Carbon\CarbonImmutable::parse($day['date'])->translatedFormat('D') }}</p></div><div><span class="inline-flex rounded-md px-2 py-1 text-xs font-semibold {{ $statusColor }}">{{ $statusLabel }}</span><p class="mt-1.5 text-xs text-gray-500">{{ $day['reason'] ?? 'Hari Wajib' }} · Shift {{ $day['submitted_shifts'] }}/{{ $day['required_shifts'] }}</p></div></div><div class="flex items-center justify-between gap-5 sm:justify-end"><div class="text-sm"><span class="text-xs text-gray-500">Nilai</span><strong class="ml-2 text-gray-900 dark:text-white">{{ $day['score'] ?? '—' }}</strong></div>@if($day['report_id'] && auth()->user()->can('view sales reports'))<a class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 dark:text-blue-300" href="{{ \App\Filament\Helpdesk\Resources\SalesReports\SalesReportResource::getUrl('view',['record'=>$day['report_id']]) }}">Buka Laporan<x-heroicon-o-arrow-up-right class="h-3.5 w-3.5" /></a>@endif</div></div>
                    @endforeach
                </div></div>
                <div class="flex justify-end border-t border-gray-200 px-5 py-4 dark:border-gray-700"><button type="button" wire:click="closeDetail" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Tutup</button></div>
            </x-rnd.picker-modal>
        </div>
    @endif

    @if($settingsBranchId !== null)
        <div data-testid="score-settings-modal" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeSettings()" class="fixed inset-0 z-[130] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Pengaturan Penilaian">
            <button type="button" wire:click="closeSettings" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup modal pengaturan"></button>
            <form wire:submit="saveSettings" class="relative w-full max-w-2xl">
                <x-rnd.picker-modal title="Pengaturan Penilaian" description="{{ $this->branches->find($settingsBranchId)?->name }} · Kelola periode dan pengecualian pelaporan.">
                    <x-slot:close><button type="button" wire:click="closeSettings" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup pengaturan"><x-heroicon-o-x-mark class="h-5 w-5" /></button></x-slot:close>
                    <div class="space-y-5 overflow-y-auto p-5">
                        <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"><div class="mb-4 flex items-center gap-2 font-semibold text-gray-900 dark:text-white"><x-heroicon-o-calendar-days class="h-5 w-5 text-blue-500" />Periode Penilaian</div><label for="assessment-start" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Mulai Penilaian *</label><input id="assessment-start" type="date" wire:model="assessmentStart" class="{{ $inputClass }}"><p class="mt-2 text-xs leading-5 text-gray-500">Tanggal pertama cabang wajib melapor. Gunakan tanggal awal kewajiban sebelumnya untuk menghitung laporan existing.</p>@error('assessmentStart')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</section>
                        <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"><div class="flex flex-wrap items-center justify-between gap-3"><h4 class="flex items-center gap-2 font-semibold text-gray-900 dark:text-white"><x-heroicon-o-calendar-date-range class="h-5 w-5 text-blue-500" />Pengecualian Tanggal Tutup</h4><button type="button" wire:click="addExcludedDate" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 dark:text-blue-300"><x-heroicon-o-plus class="h-4 w-4" />Tambah Tanggal</button></div><p class="mt-2 text-xs leading-5 text-gray-500">Tanggal tutup dikeluarkan dari hari wajib. Cantumkan alasan untuk setiap pengecualian.</p>
                            <div class="mt-4 space-y-3">@forelse($excludedDates as $index=>$exception)<div wire:key="exception-{{ $settingsBranchId }}-{{ $index }}" class="grid gap-3 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/50 sm:grid-cols-[1fr_1.5fr_auto]"><div><label for="excluded-date-{{ $index }}" class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-200">Tanggal *</label><input id="excluded-date-{{ $index }}" type="date" wire:model="excludedDates.{{ $index }}.date" class="{{ $inputClass }}">@error('excludedDates.'.$index.'.date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div><div><label for="excluded-reason-{{ $index }}" class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-200">Alasan *</label><input id="excluded-reason-{{ $index }}" type="text" maxlength="255" placeholder="Contoh: Tutup renovasi" wire:model="excludedDates.{{ $index }}.reason" class="{{ $inputClass }}">@error('excludedDates.'.$index.'.reason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div><button type="button" wire:click="removeExcludedDate({{ $index }})" class="self-end rounded-lg p-2.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40" aria-label="Hapus tanggal pengecualian {{ $index + 1 }}"><x-heroicon-o-trash class="h-4 w-4" /></button></div>@empty<p class="rounded-lg border border-dashed border-gray-200 p-4 text-center text-xs text-gray-500 dark:border-gray-700">Belum ada pengecualian tanggal tutup.</p>@endforelse</div>
                            @error('excludedDates')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                        </section>
                        <div class="flex items-start gap-2 rounded-lg bg-blue-50 p-3 text-xs leading-5 text-blue-700 dark:bg-blue-950/30 dark:text-blue-300"><x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0" />Perubahan pengaturan menghitung ulang nilai periode terkait. Status dan isi laporan tetap tersimpan.</div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-700"><button type="button" wire:click="closeSettings" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Batal</button><button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50"><x-heroicon-o-check class="h-4 w-4" />Simpan Pengaturan</button></div>
                </x-rnd.picker-modal>
            </form>
        </div>
    @endif
</x-filament-panels::page>
