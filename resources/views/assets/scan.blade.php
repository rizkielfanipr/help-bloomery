<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $asset->asset_number }}</title>@vite(['resources/css/app.css'])</head>
<body class="min-h-screen bg-slate-100 p-5 text-slate-900">
    <main class="mx-auto mt-10 max-w-md rounded-3xl bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-widest text-blue-600">Bloomery Asset</p>
        <h1 class="mt-2 text-xl font-bold">{{ $asset->name }}</h1>
        <p class="mt-1 font-mono text-sm text-slate-500">{{ $asset->asset_number }}</p>
        <dl class="mt-6 space-y-3 text-sm">
            <div><dt class="text-slate-400">Branch</dt><dd class="font-semibold">{{ $asset->branch->name }}</dd></div>
            <div><dt class="text-slate-400">Brand / Model</dt><dd class="font-semibold">{{ trim(($asset->brand ?? '').' '.($asset->model ?? '')) ?: '—' }}</dd></div>
            <div><dt class="text-slate-400">Status</dt><dd class="font-semibold {{ $asset->is_active ? 'text-emerald-600' : 'text-red-600' }}">{{ $asset->is_active ? 'Aktif' : 'Nonaktif' }}</dd></div>
        </dl>

        @if(session('success'))
            <div class="mt-6 rounded-2xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-700">{{ session('success') }}</div>
        @endif

        @if($activeRequest)
            <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Laporan Aktif</p>
                <p class="mt-1 font-bold">{{ $activeRequest->code }}</p>
                <p class="mt-1 text-sm text-amber-800">{{ $activeRequest->requestor_notes }}</p>
            </div>
        @endif

        @auth
            @if(auth()->user()->canAccessBranch($asset->branch_id))
                <form method="POST" action="{{ route('assets.report', $asset->qr_token) }}" enctype="multipart/form-data" class="mt-7 space-y-4">
                    @csrf
                    <div>
                        <label for="issue" class="mb-1.5 block text-sm font-bold">Kendala <span class="text-red-500">*</span></label>
                        <textarea id="issue" name="issue" rows="4" required maxlength="2000" class="w-full rounded-2xl border border-slate-300 p-3 text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Jelaskan kendala pada asset ini...">{{ old('issue') }}</textarea>
                        @error('issue')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="photos" class="mb-1.5 block text-sm font-bold">Foto Kendala <span class="text-red-500">*</span></label>
                        <input id="photos" name="photos[]" type="file" accept="image/*" capture="environment" multiple required class="block w-full rounded-2xl border border-slate-300 bg-white p-3 text-sm">
                        <p class="mt-1 text-xs text-slate-400">Maksimal 5 foto, masing-masing 5 MB.</p>
                        @error('photos')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        @error('photos.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="w-full rounded-2xl bg-blue-600 px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Kirim Laporan</button>
                </form>
            @else
                <p class="mt-6 rounded-2xl bg-amber-50 p-4 text-sm text-amber-700">Akun Anda tidak memiliki akses ke branch asset ini.</p>
            @endif
        @else
            <a href="{{ route('assets.login', $asset->qr_token) }}" class="mt-7 block w-full rounded-2xl bg-blue-600 px-4 py-3 text-center text-sm font-bold text-white">Login untuk Melaporkan Kendala</a>
        @endauth
    </main>
</body>
</html>
