<x-filament-panels::page>
    @php
        $inputClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
        $defaultTotal = $this->defaultWeightTotal();
        $overrideTotal = $this->overrideWeightTotal();
        $participantBranches = $this->participantBranches();
        $availableBranches = $this->availableBranches();
        $participationPeriods = $this->participationPeriods();
    @endphp
    <form wire:submit="save" class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-start gap-3 border-b border-gray-200 p-5 dark:border-gray-700">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300"><x-heroicon-o-calculator class="h-5 w-5" /></div>
            <div>
                <h3 class="font-bold text-gray-900 dark:text-white">Pengaturan Nilai Daily Briefing</h3>
                <p class="mt-1 text-sm leading-6 text-gray-500">Mengatur metode penilaian, bobot periode, dan auto-reject review per branch untuk Daily Briefing.</p>
            </div>
        </div>

        <div class="space-y-5 p-5 sm:p-6">

            {{-- Metode Penilaian --}}
            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <h4 class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"><x-heroicon-o-chart-bar class="h-5 w-5 text-blue-500" />Metode Penilaian</h4>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="scoring-started-at" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Mulai Penilaian Briefing <span class="text-red-500">*</span></label>
                        <input id="scoring-started-at" type="date" required wire:model="data.scoring_started_at" class="{{ $inputClass }}">
                        <p class="mt-2 text-xs leading-5 text-gray-500">Pada bulan pertama, nilai hanya dihitung mulai tanggal ini. Bulan berikutnya tetap dihitung dari tanggal 1 sampai akhir bulan.</p>
                        @error('data.scoring_started_at')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="passing-score" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Nilai Minimum Achieve <span class="text-red-500">*</span></label>
                        <div class="relative"><input id="passing-score" type="number" min="0" max="100" step="0.01" required wire:model="data.passing_score" class="{{ $inputClass }} pr-12"><span class="pointer-events-none absolute right-3 top-3 text-sm text-gray-400">/ 100</span></div>
                        <p class="mt-2 text-xs leading-5 text-gray-500">Branch dengan nilai akhir ≥ ambang ini berstatus Achieve.</p>
                        @error('data.passing_score')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-xs leading-5 text-gray-600 dark:bg-gray-800/50 dark:text-gray-300">
                    <p class="font-semibold text-gray-700 dark:text-gray-200">Formula (contoh, bukan data production):</p>
                    <p class="mt-1">Nilai Periode = Approved Occurrence ÷ Expected Occurrence × 100. Hanya submission <strong>Approved</strong> mendapat kredit. Tanggal masa depan dan periode yang tidak berlaku (Not Applicable) tidak masuk denominator.</p>
                </div>
            </section>

            {{-- Bobot Default --}}
            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <h4 class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"><x-heroicon-o-scale class="h-5 w-5 text-blue-500" />Bobot Default</h4>
                <div class="grid gap-5 sm:grid-cols-3">
                    <div>
                        <label for="default-daily" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Bobot Harian <span class="text-red-500">*</span></label>
                        <div class="relative"><input id="default-daily" type="number" min="0" max="100" step="0.01" required wire:model.live="data.default_daily_weight" class="{{ $inputClass }} pr-8"><span class="pointer-events-none absolute right-3 top-3 text-sm text-gray-400">%</span></div>
                        @error('data.default_daily_weight')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="default-weekly" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Bobot Mingguan <span class="text-red-500">*</span></label>
                        <div class="relative"><input id="default-weekly" type="number" min="0" max="100" step="0.01" required wire:model.live="data.default_weekly_weight" class="{{ $inputClass }} pr-8"><span class="pointer-events-none absolute right-3 top-3 text-sm text-gray-400">%</span></div>
                    </div>
                    <div>
                        <label for="default-monthly" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Bobot Bulanan <span class="text-red-500">*</span></label>
                        <div class="relative"><input id="default-monthly" type="number" min="0" max="100" step="0.01" required wire:model.live="data.default_monthly_weight" class="{{ $inputClass }} pr-8"><span class="pointer-events-none absolute right-3 top-3 text-sm text-gray-400">%</span></div>
                    </div>
                </div>
                <p class="mt-3 text-sm font-semibold {{ abs($defaultTotal - 100) <= 0.01 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                    Total Bobot: {{ number_format($defaultTotal, 2) }}% — {{ abs($defaultTotal - 100) <= 0.01 ? 'Valid' : 'Harus 100%' }}
                </p>
                <p class="mt-2 text-xs leading-5 text-gray-500">Jika salah satu periode tidak berlaku pada suatu branch/bulan (Not Applicable), bobotnya didistribusikan proporsional ke periode yang berlaku.</p>
            </section>

            {{-- Partisipasi Branch --}}
            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"><x-heroicon-o-building-office-2 class="h-5 w-5 text-blue-500" />Peserta Daily Briefing</h4>
                <p class="mt-2 text-xs leading-5 text-gray-500">Hanya STORE_STAFF pada branch yang terdaftar sebagai peserta yang dapat mengisi Daily Briefing. Branch tetap aktif sampai dilepas dari daftar peserta.</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    <div>
                        <label for="participant-branch" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Branch <span class="text-red-500">*</span></label>
                        <select id="participant-branch" wire:model="participationData.branch_id" class="{{ $inputClass }}">
                            <option value="">— Pilih branch —</option>
                            @foreach($availableBranches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('participationData.branch_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="button" wire:click="addParticipation" wire:loading.attr="disabled" wire:target="addParticipation" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60">Daftarkan sebagai Peserta</button>
                </div>

                <div class="mt-5 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800/60">
                            <tr><th class="px-4 py-3">Branch</th><th class="px-4 py-3">Terdaftar Sejak</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($participationPeriods as $period)
                                @php($isActiveToday = $period->coversDate(today()))
                                <tr wire:key="participation-{{ $period->id }}">
                                    <td class="px-4 py-3 font-semibold text-gray-800 dark:text-gray-100">{{ $period->branch->name }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $period->started_at->format('d M Y') }}</td>
                                    <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $isActiveToday ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">{{ $isActiveToday ? 'Aktif' : 'Tidak aktif' }}</span></td>
                                    <td class="px-4 py-3 text-right">
                                        @if($period->is_enabled && ($period->ended_at === null || $period->ended_at->gte(today())))
                                            <button type="button" wire:click="deactivateParticipation({{ $period->id }})" wire:confirm="Lepas branch ini dari peserta Daily Briefing?" class="text-xs font-semibold text-red-600 hover:text-red-700">Lepas Peserta</button>
                                        @else
                                            <span class="text-xs text-gray-400">Riwayat</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada branch peserta. Tambahkan branch agar STORE_STAFF dapat membuka Daily Briefing.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Override Branch --}}
            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <h4 class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"><x-heroicon-o-building-storefront class="h-5 w-5 text-blue-500" />Override Bobot per Branch</h4>

                @if($participantBranches->isEmpty())
                    <p class="rounded-lg border border-dashed border-gray-300 p-4 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Belum ada branch yang terdaftar sebagai peserta Daily Briefing.</p>
                @else
                    <div class="mb-4">
                        <label for="override-branch" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Pilih Branch</label>
                        <select id="override-branch" wire:model.live="selectedOverrideBranchId" class="{{ $inputClass }}">
                            <option value="">— Pilih branch peserta —</option>
                            @foreach($participantBranches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($selectedOverrideBranchId)
                        <div class="rounded-lg bg-gray-50 p-3 text-xs leading-5 text-gray-600 dark:bg-gray-800/50 dark:text-gray-300">
                            Bobot default saat ini: Harian {{ number_format((float) $data['default_daily_weight'], 2) }}%, Mingguan {{ number_format((float) $data['default_weekly_weight'], 2) }}%, Bulanan {{ number_format((float) $data['default_monthly_weight'], 2) }}%.
                        </div>

                        <label for="use-custom-weight" class="mt-4 flex cursor-pointer items-start justify-between gap-4 rounded-xl border border-gray-200 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                            <span><span class="block text-sm font-semibold text-gray-900 dark:text-white">Gunakan Bobot Khusus</span><span class="mt-1 block text-xs leading-5 text-gray-500">Branch ini memakai bobot sendiri menggantikan bobot default.</span></span>
                            <span class="relative mt-0.5 shrink-0"><input id="use-custom-weight" type="checkbox" wire:model.live="useCustomWeight" class="peer sr-only"><span class="block h-6 w-11 rounded-full bg-gray-300 transition peer-checked:bg-blue-600 peer-focus-visible:ring-2 peer-focus-visible:ring-blue-500 peer-focus-visible:ring-offset-2 dark:bg-gray-600"></span><span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition peer-checked:translate-x-5"></span></span>
                        </label>

                        @if($useCustomWeight)
                            <div class="mt-4 grid gap-5 sm:grid-cols-3">
                                <div>
                                    <label for="override-daily" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Bobot Harian</label>
                                    <div class="relative"><input id="override-daily" type="number" min="0" max="100" step="0.01" wire:model.live="data.override_daily_weight" class="{{ $inputClass }} pr-8"><span class="pointer-events-none absolute right-3 top-3 text-sm text-gray-400">%</span></div>
                                </div>
                                <div>
                                    <label for="override-weekly" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Bobot Mingguan</label>
                                    <div class="relative"><input id="override-weekly" type="number" min="0" max="100" step="0.01" wire:model.live="data.override_weekly_weight" class="{{ $inputClass }} pr-8"><span class="pointer-events-none absolute right-3 top-3 text-sm text-gray-400">%</span></div>
                                </div>
                                <div>
                                    <label for="override-monthly" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Bobot Bulanan</label>
                                    <div class="relative"><input id="override-monthly" type="number" min="0" max="100" step="0.01" wire:model.live="data.override_monthly_weight" class="{{ $inputClass }} pr-8"><span class="pointer-events-none absolute right-3 top-3 text-sm text-gray-400">%</span></div>
                                </div>
                            </div>
                            <p class="mt-3 text-sm font-semibold {{ abs($overrideTotal - 100) <= 0.01 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                Total Bobot: {{ number_format($overrideTotal, 2) }}% — {{ abs($overrideTotal - 100) <= 0.01 ? 'Valid' : 'Harus 100%' }}
                            </p>
                            @error('data.override_daily_weight')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        @endif
                    @endif
                @endif
            </section>

            {{-- Auto-Reject --}}
            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <h4 class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"><x-heroicon-o-clock class="h-5 w-5 text-blue-500" />Auto-Reject Review Briefing</h4>
                <p class="mb-4 text-xs leading-5 text-gray-500">Berlaku untuk submission yang <strong>sudah dikirim staff tetapi belum direview Supervisor</strong> dalam jangka waktu ini — bukan untuk staff yang tidak mengisi sama sekali.</p>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="auto-reject-days" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Auto-Reject Setelah <span class="text-red-500">*</span></label>
                        <div class="relative"><input id="auto-reject-days" type="number" min="1" step="1" required wire:model.live="data.auto_reject_after_days" class="{{ $inputClass }} pr-16"><span class="pointer-events-none absolute right-3 top-3 text-sm text-gray-400">Hari</span></div>
                        @error('data.auto_reject_after_days')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex items-center gap-3 rounded-xl bg-gray-50 p-3 text-sm text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                        <x-heroicon-o-exclamation-circle class="h-5 w-5 shrink-0 text-blue-500" />
                        <div><p class="font-semibold text-gray-700 dark:text-gray-200">{{ $this->eligibleAutoRejectCount }} item memenuhi syarat</p><p class="mt-1 text-xs leading-5">Dihitung dengan jumlah hari saat ini pada eksekusi scheduler berikutnya.</p></div>
                    </div>
                </div>
                <div class="mt-4">
                    <label for="auto-reject-reason" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Alasan Auto-Reject <span class="text-red-500">*</span></label>
                    <textarea id="auto-reject-reason" rows="3" required maxlength="255" wire:model="data.auto_reject_reason" class="{{ $inputClass }} resize-y" placeholder="Contoh: Tidak ada approval dalam :days hari setelah poin diselesaikan."></textarea>
                    <p class="mt-2 text-xs leading-5 text-gray-500">Gunakan <span class="font-semibold text-blue-600 dark:text-blue-300">:days</span> untuk menampilkan jumlah hari yang diatur.</p>
                    @error('data.auto_reject_reason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </section>

            {{-- Informasi Proses Otomatis --}}
            <div class="flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30">
                <x-heroicon-o-information-circle class="mt-0.5 h-5 w-5 shrink-0 text-blue-500" />
                <div>
                    <h4 class="text-sm font-semibold text-blue-800 dark:text-blue-200">Informasi Proses Otomatis</h4>
                    <ul class="mt-1 list-disc space-y-1 pl-4 text-xs leading-6 text-blue-700 dark:text-blue-300">
                        <li>Hanya submission <strong>Approved</strong> mendapat kredit.</li>
                        <li><strong>Missing</strong>, <strong>Rejected</strong>, dan <strong>Supervisor Review</strong> bernilai nol setelah occurrence jatuh tempo.</li>
                        <li>Scheduler tidak pernah membuat submission palsu untuk staff yang tidak mengisi.</li>
                        <li>Perubahan pengaturan ini <strong>tidak otomatis menghitung ulang</strong> nilai historis — gunakan Hitung Ulang pada halaman Nilai Briefing bila diperlukan.</li>
                        <li>Bobot override branch menggantikan bobot default untuk branch tersebut.</li>
                        <li>Bulan yang masih berjalan diberi label <strong>Sementara</strong>.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-3 border-t border-gray-200 bg-gray-50/60 px-5 py-4 dark:border-gray-700 dark:bg-gray-800/30 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-gray-500">Perubahan berlaku setelah pengaturan disimpan.</p>
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700 disabled:opacity-50">
                <x-heroicon-o-check class="h-4 w-4" />
                <span wire:loading.remove wire:target="save">Simpan Pengaturan</span>
                <span wire:loading wire:target="save">Menyimpan…</span>
            </button>
        </div>
    </form>
</x-filament-panels::page>
