<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <meta name="color-scheme" content="light">
    <title>{{ $asset->name }} · Bloomery Asset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-slate-100 font-sans text-slate-900">
    @php
        $assetStatus = $asset->status;
        $canReport = auth()->user()?->canAccessBranch($asset->branch_id) && $asset->is_active;
        $fieldClass = 'w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-slate-700 placeholder-slate-400 focus:border-blue-400 focus:outline-none focus:ring-0';
    @endphp
    <div class="mx-auto min-h-dvh max-w-[430px] bg-slate-50">
        <header class="relative overflow-hidden bg-blue-600 px-5 pb-5 pt-6 text-white">
            <div class="pointer-events-none absolute -right-12 -top-16 h-40 w-40 rounded-full border-[32px] border-white/5" aria-hidden="true"></div>
            <div class="relative flex items-center justify-between gap-3">
                <a href="{{ route('filament.casual.pages.technician-request-page') }}" aria-label="Kembali ke Request Teknisi" class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/20 bg-white/10 hover:bg-white/20"><x-heroicon-o-arrow-left class="h-5 w-5" /></a>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-white/20 px-3 py-1.5 text-[10px] font-semibold text-blue-100"><x-heroicon-o-qr-code class="h-3.5 w-3.5" />Asset Teridentifikasi</span>
            </div>
            <div class="relative mt-4 flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white/15"><x-heroicon-o-cube class="h-5 w-5" /></div>
                <div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-[0.18em] text-blue-200">Bloomery Asset</p><h1 class="mt-1 text-xl font-bold">Detail Asset</h1><p class="mt-1 text-xs leading-5 text-blue-100">Periksa identitas asset sebelum melaporkan kendala.</p></div>
            </div>
        </header>

        <main class="space-y-5 px-5 py-6">
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between gap-3"><h2 class="text-base font-bold leading-6">{{ $asset->name }}</h2><span class="shrink-0 rounded-md border px-2 py-1 text-[10px] font-bold {{ match($assetStatus) { 'Inactive' => 'border-red-200 bg-red-50 text-red-700', 'Maintenance' => 'border-amber-200 bg-amber-50 text-amber-700', default => 'border-emerald-200 bg-emerald-50 text-emerald-700' } }}">{{ $assetStatus }}</span></div>
                <p class="mt-2 break-all font-mono text-[11px] text-slate-500">{{ $asset->asset_number }}</p>
                <dl class="mt-4 grid grid-cols-2 gap-4 border-t border-gray-100 pt-4">
                    <div class="col-span-2"><dt class="text-[10px] font-semibold text-slate-400">Cabang</dt><dd class="mt-1 flex items-center gap-2 text-xs font-semibold"><x-heroicon-o-building-office-2 class="h-4 w-4 shrink-0 text-slate-400" />{{ $asset->branch->name }}</dd></div>
                    <div><dt class="text-[10px] font-semibold text-slate-400">Brand / Model</dt><dd class="mt-1 text-xs font-semibold">{{ trim(($asset->brand ?? '').' '.($asset->model ?? '')) ?: 'Belum Diatur' }}</dd></div>
                    <div><dt class="text-[10px] font-semibold text-slate-400">Kategori</dt><dd class="mt-1 text-xs font-semibold">{{ $asset->category ?: 'Belum Diatur' }}</dd></div>
                    @if($asset->serial_number)<div class="col-span-2"><dt class="text-[10px] font-semibold text-slate-400">Nomor Seri</dt><dd class="mt-1 break-all text-xs font-semibold">{{ $asset->serial_number }}</dd></div>@endif
                </dl>
                @if($repairCount !== null)<p class="mt-4 flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500"><x-heroicon-o-wrench-screwdriver class="h-4 w-4 shrink-0" /><span><strong class="text-slate-700">{{ $repairCount }} kali perbaikan selesai</strong> tercatat untuk asset ini.</span></p>@endif
            </section>

            @if(session('success'))<div role="status" class="flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs leading-5 text-emerald-700"><x-heroicon-o-check-circle class="mt-0.5 h-4 w-4 shrink-0" />{{ session('success') }}</div>@endif
            @if($activeRequest)
                <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <h2 class="flex items-center gap-2 text-xs font-bold text-amber-800"><x-heroicon-o-clock class="h-4 w-4" />Laporan Masih Berjalan</h2><p class="mt-2 font-mono text-xs font-semibold text-amber-800">{{ $activeRequest->code }} · {{ $activeRequest->status->getLabel() }}</p><p class="mt-2 text-xs leading-5 text-amber-700">{{ $activeRequest->requestor_notes }}</p><p class="mt-2 text-[11px] leading-5 text-amber-700">Jika kendalanya sama, pantau laporan sebelumnya melalui riwayat request.</p>
                </section>
            @endif

            @auth
                @if($canReport)
                    <form method="POST" action="{{ route('assets.report', $asset->qr_token) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <section class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5">
                            <h2 class="flex items-center gap-2 text-sm font-bold"><x-heroicon-o-chat-bubble-left-ellipsis class="h-4 w-4 text-blue-600" />Laporkan Kendala</h2>
                            <div class="flex items-start gap-2 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2.5"><x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-blue-600" /><p class="text-xs leading-5 text-blue-800">Jelaskan kendala dan lampirkan foto kondisi asset. Laporan otomatis terhubung ke asset ini; <span class="font-semibold">jadwal pengerjaan ditentukan oleh teknisi.</span></p></div>
                            <div><label for="issue" class="mb-1.5 block text-xs font-semibold text-slate-600">Deskripsi Masalah <span class="text-red-500">*</span></label><textarea id="issue" name="issue" rows="4" required maxlength="2000" class="{{ $fieldClass }} resize-none" placeholder="Jelaskan kerusakan, kapan mulai terjadi, dan dampaknya pada penggunaan asset...">{{ old('issue') }}</textarea>@error('issue')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror</div>
                            <div x-data="{ fileNames: [] }">
                                <label for="photos" class="mb-1.5 block text-xs font-semibold text-slate-600">Foto Lampiran <span class="text-red-500">*</span></label>
                                <label for="photos" class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 py-4 transition hover:border-blue-300 hover:bg-blue-50"><x-heroicon-o-photo class="h-5 w-5 text-slate-400" /><span class="text-sm text-slate-400">Tambah Foto</span></label>
                                <input id="photos" name="photos[]" type="file" accept="image/*" multiple required class="sr-only" x-on:change="fileNames = Array.from($event.target.files).map(file => file.name)">
                                <template x-for="(name, index) in fileNames" :key="index"><p class="mt-2 truncate text-xs text-blue-600" x-text="name"></p></template>
                                <p class="mt-2 text-[10px] text-slate-400">Wajib minimal 1 foto · Maksimal 5 foto · 5 MB per foto.</p>
                                @error('photos')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
                                @foreach($errors->get('photos.*') as $messages)@foreach($messages as $message)<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@endforeach@endforeach
                            </div>
                        </section>
                        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white transition hover:bg-blue-700"><x-heroicon-o-paper-airplane class="h-4 w-4" />Kirim Laporan</button>
                    </form>
                @else
                    <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-700">{{ ! $asset->is_active ? 'Asset dinyatakan Inactive. Hubungi teknisi untuk tindak lanjut perbaikan yang sedang berjalan.' : 'Akun Anda tidak memiliki akses untuk melaporkan asset di cabang ini.' }}</p>
                @endif
            @else
                <section class="rounded-2xl border border-gray-200 bg-white p-5"><p class="text-xs leading-5 text-slate-500">Login untuk melaporkan kendala dan melihat riwayat perbaikan asset.</p><a href="{{ route('assets.login', $asset->qr_token) }}" class="mt-4 flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700"><x-heroicon-o-arrow-right-on-rectangle class="h-4 w-4" />Login untuk Melaporkan Kendala</a></section>
            @endauth

            @if($repairCount !== null)
                <section class="space-y-3">
                    <h2 class="flex items-center gap-2 text-sm font-bold"><x-heroicon-o-clock class="h-4 w-4 text-blue-600" />Riwayat Perbaikan<span class="ml-auto text-xs font-normal text-slate-400">{{ $history->count() }} laporan</span></h2>
                    @forelse($history as $item)
                        <details class="group overflow-hidden rounded-xl border border-gray-200 bg-white">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4"><div><p class="font-mono text-xs font-semibold">{{ $item->code }}</p><p class="mt-1 text-[11px] text-slate-500">{{ $item->created_at->format('d M Y') }} · {{ $item->status->getLabel() }}</p></div><x-heroicon-o-chevron-down class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" /></summary>
                            <div class="space-y-3 border-t border-gray-100 p-4"><p class="text-xs leading-5 text-slate-600">{{ $item->requestor_notes }}</p><p class="text-[11px] text-slate-500">Teknisi: {{ $item->technician?->name ?? 'Belum Ditugaskan' }}</p>@foreach($item->repairs as $repair)<div class="border-t border-gray-100 pt-3"><p class="text-xs font-semibold">{{ $repair->cycle_label }}</p><p class="mt-1 text-[11px] text-slate-400">{{ $repair->completed_at?->format('d M Y, H:i') ?? 'Belum Selesai' }}</p><p class="mt-2 text-xs leading-5 text-slate-600">{{ $repair->after_notes }}</p></div>@endforeach</div>
                        </details>
                    @empty<p class="rounded-xl border border-dashed border-gray-300 p-5 text-center text-xs text-slate-500">Belum ada laporan perbaikan.</p>@endforelse
                </section>
            @endif
        </main>
    </div>
</body>
</html>
