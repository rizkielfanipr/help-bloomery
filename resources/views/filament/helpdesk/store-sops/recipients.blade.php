<div class="max-h-[65dvh] overflow-y-auto">
    <div class="mb-4 grid grid-cols-2 gap-3 text-center">
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5"><p class="text-xl font-bold">{{ $record->branches->count() }}</p><p class="text-xs text-gray-500">Branch Terkirim</p></div>
        <div class="rounded-xl bg-blue-50 p-3 dark:bg-blue-950/30"><p class="text-xl font-bold text-blue-700">{{ $record->assignments->whereNotNull('acknowledged_at')->pluck('branch_id')->unique()->count() }}</p><p class="text-xs text-gray-500">Branch Diterima</p></div>
    </div>
    <div class="divide-y divide-gray-100 rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
        @forelse($record->assignments as $assignment)
            <div class="grid gap-2 p-3 text-sm sm:grid-cols-[1fr_1fr_auto] sm:items-center">
                <div><p class="font-semibold">{{ $assignment->user->name }}</p><p class="text-xs text-gray-500">{{ $assignment->user->username }}</p></div>
                <div><p>{{ $assignment->branch->name }}</p><p class="text-xs text-gray-500">{{ $assignment->assigned_at->format('d M Y H:i') }}</p></div>
                <x-filament::badge :color="$assignment->acknowledged_at ? 'success' : 'warning'">
                    {{ $assignment->acknowledged_at ? 'Diterima' : 'Baru' }}
                </x-filament::badge>
            </div>
        @empty
            <p class="p-6 text-center text-sm text-gray-500">Belum ada pengguna yang membuka halaman SOP ini.</p>
        @endforelse
    </div>
</div>
