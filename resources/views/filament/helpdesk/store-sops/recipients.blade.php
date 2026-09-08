<div class="max-h-[65dvh] overflow-y-auto">
    <div class="mb-4 grid grid-cols-3 gap-3 text-center">
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5"><p class="text-xl font-bold">{{ $record->assignments->count() }}</p><p class="text-xs text-gray-500">Ditugaskan</p></div>
        <div class="rounded-xl bg-blue-50 p-3 dark:bg-blue-950/30"><p class="text-xl font-bold text-blue-700">{{ $record->assignments->whereNotNull('opened_at')->count() }}</p><p class="text-xs text-gray-500">Dibuka</p></div>
        <div class="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/30"><p class="text-xl font-bold text-emerald-700">{{ $record->assignments->whereNotNull('acknowledged_at')->count() }}</p><p class="text-xs text-gray-500">Dipahami</p></div>
    </div>
    <div class="divide-y divide-gray-100 rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
        @forelse($record->assignments as $assignment)
            <div class="grid gap-2 p-3 text-sm sm:grid-cols-[1fr_1fr_auto] sm:items-center">
                <div><p class="font-semibold">{{ $assignment->user->name }}</p><p class="text-xs text-gray-500">{{ $assignment->user->username }}</p></div>
                <div><p>{{ $assignment->branch->name }}</p><p class="text-xs text-gray-500">{{ $assignment->assigned_at->format('d M Y H:i') }}</p></div>
                <x-filament::badge :color="$assignment->acknowledged_at ? 'success' : ($assignment->opened_at ? 'info' : 'warning')">
                    {{ $assignment->acknowledged_at ? 'Sudah Dipahami' : ($assignment->opened_at ? 'Sudah Dibuka' : 'Belum Dibuka') }}
                </x-filament::badge>
            </div>
        @empty
            <p class="p-6 text-center text-sm text-gray-500">Belum ada Supervisor Store pada branch target.</p>
        @endforelse
    </div>
</div>
