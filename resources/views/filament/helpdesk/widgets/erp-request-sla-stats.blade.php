<div class="space-y-3">
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->getStats() as $stat)
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                <p class="mt-2 text-lg font-semibold text-blue-600 dark:text-blue-400">{{ $stat['value'] }}</p>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $stat['description'] }}</p>
            </div>
        @endforeach
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400">Senin–Jumat 08.00–17.00 WIB · Mengikuti seluruh hasil filter daftar tiket.</p>
    <x-erp-request.sla-explanation />
</div>
