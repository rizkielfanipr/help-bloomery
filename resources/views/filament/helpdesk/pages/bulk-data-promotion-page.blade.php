<x-filament-panels::page>
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Bulk Data Promotion</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Halaman ini disiapkan sebagai tujuan menu Bulk Data Promotion.
                </p>
            </div>

            <a
                href="{{ route('filament.helpdesk.pages.bulk-data') }}"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
            >
                Kembali ke Bulk Data
            </a>
        </div>
    </div>
</x-filament-panels::page>
