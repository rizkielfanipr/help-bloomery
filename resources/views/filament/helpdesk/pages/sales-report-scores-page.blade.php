<x-filament-panels::page>
    <section class="rounded-xl border border-gray-200 bg-white p-5 space-y-4 dark:border-gray-700 dark:bg-gray-900">
        <div><h2 class="text-lg font-semibold">Monthly Reporting Compliance</h2><p class="text-sm text-gray-500">Nilai = hari approved supervisor atau Completed ÷ hari wajib × 100%. Rejected, belum approved, dan tidak input bernilai 0.</p></div>
        <div class="flex flex-wrap gap-4">
            <label class="text-sm space-y-1">Bulan Penilaian<input aria-label="Bulan Penilaian" type="month" wire:model.live="month" class="block rounded-lg border-gray-300 dark:bg-gray-800"></label>
            <label class="text-sm space-y-1">Cabang<select aria-label="Cabang" wire:model.live="branchFilter" class="block rounded-lg border-gray-300 dark:bg-gray-800"><option value="">Semua Cabang</option>@foreach($this->branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
        </div>
        <p class="text-sm text-gray-500">Bulan berjalan dihitung sampai kemarin. Sabtu dan Minggu tetap wajib, kecuali tanggal tutup yang diatur. Tanggal sebelum mulai penilaian tidak dihitung. Perubahan status memperbarui nilai bulan tanggal laporan.</p>
    </section>
    @php
        $totals = collect($this->scores);
        $requiredTotal = $totals->sum('required');
        $passedTotal = $totals->sum('passed');
        $overallScore = $requiredTotal ? number_format($passedTotal / $requiredTotal * 100, 2, ',', '.') . '%' : '—';
    @endphp
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach(['Nilai Gabungan' => $overallScore, 'Hari Wajib' => $requiredTotal, 'Approved / Completed' => $passedTotal, 'Rejected' => $totals->sum('rejected'), 'Tidak Input' => $totals->sum('missing'), 'Belum Approved' => $totals->sum('pending')] as $label => $value)
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><p class="text-xs text-gray-500">{{ $label }}</p><p class="mt-2 text-xl font-semibold">{{ $value }}</p></div>
        @endforeach
    </div>
    <p class="text-xs text-gray-500">Nilai gabungan menggunakan total hari wajib seluruh cabang pada filter, bukan rata-rata persentase cabang. Tanda — berarti belum ada hari wajib yang dapat dinilai.</p>
    <div wire:loading class="text-sm text-gray-500">Memuat penilaian…</div>
    <section class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm text-left"><thead class="bg-gray-50 dark:bg-gray-800"><tr>@foreach(['Cabang','Mulai Penilaian','Hari Wajib','Approved / Completed','Rejected','Tidak Input','Belum Approved','Nilai','Aksi'] as $label)<th class="px-4 py-3 whitespace-nowrap">{{ $label }}</th>@endforeach</tr></thead><tbody>
        @forelse($this->scores as $score)<tr class="border-t border-gray-200 dark:border-gray-700">
            <td class="px-4 py-3 font-medium">{{ $score['name'] }}</td><td class="px-4 py-3 whitespace-nowrap">{{ $score['started_at'] ?? 'Belum Diatur' }}</td>
            @foreach(['required','passed','rejected','missing','pending'] as $key)<td class="px-4 py-3">{{ $score[$key] }}</td>@endforeach
            <td class="px-4 py-3 font-semibold">{{ $score['score'] !== null ? number_format($score['score'], 2, ',', '.') . '%' : '—' }}</td>
            <td class="px-4 py-3 whitespace-nowrap"><button type="button" wire:click="showDetail({{ $score['branch_id'] }})" class="text-primary-600 font-medium">Detail Harian</button>@can('edit sales report assessment settings')<button type="button" wire:click="openSettings({{ $score['branch_id'] }})" class="ml-3 text-primary-600 font-medium">Pengaturan</button>@endcan</td>
        </tr>@empty<tr><td colspan="9" class="p-5 text-gray-500">Tidak ada cabang atau periode belum valid.</td></tr>@endforelse
        </tbody></table>
    </section>
    @if(isset($this->scores[$detailBranchId]))
        @php($detail = $this->scores[$detailBranchId])
        <section class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-4"><h2 class="font-semibold">Rincian Harian · {{ $detail['name'] }}</h2><p class="text-sm text-gray-500">Satu tanggal bernilai maksimal 100. Kolom shift menunjukkan kelengkapan terhadap shift cabang saat ini.</p></div>
            <div class="overflow-x-auto"><table class="w-full text-sm text-left"><thead class="bg-gray-50 dark:bg-gray-800"><tr><th class="p-3">Tanggal</th><th class="p-3">Status</th><th class="p-3">Shift</th><th class="p-3">Nilai</th><th class="p-3">Keterangan</th><th class="p-3">Laporan</th></tr></thead><tbody>
            @foreach($detail['days'] as $day)<tr class="border-t border-gray-200 dark:border-gray-700"><td class="p-3 whitespace-nowrap">{{ $day['date'] }}</td><td class="p-3">{{ ['pending_finance'=>'Approved Supervisor','completed'=>'Completed','rejected'=>'Rejected','draft'=>'Draft','pending_supervisor'=>'Menunggu Supervisor','missing'=>'Tidak Input'][$day['status']] ?? $day['status'] }}</td><td class="p-3">{{ $day['submitted_shifts'] }}/{{ $day['required_shifts'] }}</td><td class="p-3 font-medium">{{ $day['score'] ?? '—' }}</td><td class="p-3">{{ $day['reason'] ?? 'Hari Wajib' }}</td><td class="p-3">@if($day['report_id'] && auth()->user()->can('view sales reports'))<a class="text-primary-600" href="{{ \App\Filament\Helpdesk\Resources\SalesReports\SalesReportResource::getUrl('view', ['record'=>$day['report_id']]) }}">Buka Laporan</a>@else — @endif</td></tr>@endforeach
            </tbody></table></div>
        </section>
    @endif
    @if($settingsBranchId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true" aria-label="Pengaturan Penilaian">
            <form wire:submit="saveSettings" class="w-full max-w-xl max-h-[85vh] overflow-y-auto rounded-xl bg-white p-6 space-y-4 dark:bg-gray-900">
                <h2 class="text-lg font-semibold">Pengaturan Penilaian · {{ $this->branches->find($settingsBranchId)?->name }}</h2>
                <p class="text-sm text-gray-500">Tentukan tanggal pertama cabang wajib melapor. Untuk menghitung data existing, gunakan tanggal mulai kewajiban sebelumnya. Perubahan pengaturan menghitung ulang periode terkait.</p>
                <label class="block text-sm">Mulai Penilaian<input type="date" wire:model="assessmentStart" class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800"></label>
                @error('assessmentStart')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <div><h3 class="font-medium">Pengecualian Tanggal Tutup</h3><p class="text-sm text-gray-500">Tanggal ini dikeluarkan dari pembagi. Wajib mencantumkan alasan.</p></div>
                @foreach($excludedDates as $index=>$exception)<div wire:key="exception-{{ $index }}" class="border border-gray-200 rounded-lg p-3 space-y-2"><label class="block text-sm">Tanggal<input type="date" wire:model="excludedDates.{{ $index }}.date" class="block w-full rounded-lg border-gray-300 dark:bg-gray-800"></label><label class="block text-sm">Alasan<input type="text" maxlength="255" wire:model="excludedDates.{{ $index }}.reason" class="block w-full rounded-lg border-gray-300 dark:bg-gray-800"></label><button type="button" wire:click="removeExcludedDate({{ $index }})" class="text-sm text-red-600">Hapus</button></div>@endforeach
                @foreach($errors->all() as $error)<p class="text-sm text-red-600">{{ $error }}</p>@endforeach
                <button type="button" wire:click="addExcludedDate" class="text-sm text-primary-600">+ Tambah Tanggal Tutup</button>
                <div class="flex gap-3"><x-filament::button type="submit" wire:loading.attr="disabled">Simpan Pengaturan</x-filament::button><x-filament::button color="gray" type="button" wire:click="closeSettings">Batal</x-filament::button></div>
            </form>
        </div>
    @endif
</x-filament-panels::page>
