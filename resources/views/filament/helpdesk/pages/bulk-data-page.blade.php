<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2">
        <a
            href="{{ \App\Filament\Helpdesk\Resources\BulkProductSubmissions\BulkProductSubmissionResource::getUrl() }}"
            class="group rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-500 hover:shadow-md dark:border-gray-700 dark:bg-gray-900"
        >
            <div class="flex items-start gap-4">
                <div class="rounded-lg bg-primary-50 p-3 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                    <x-filament::icon icon="heroicon-o-cube" class="h-6 w-6" />
                </div>

                <div>
                    <h2 class="text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                        Bulk Data Product
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Kelola submit bulk create dan update master product ke ESB.
                    </p>
                </div>
            </div>
        </a>

        <a
            href="{{ route('filament.helpdesk.pages.bulk-data.promotion') }}"
            class="group rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-500 hover:shadow-md dark:border-gray-700 dark:bg-gray-900"
        >
            <div class="flex items-start gap-4">
                <div class="rounded-lg bg-primary-50 p-3 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                    <x-filament::icon icon="heroicon-o-tag" class="h-6 w-6" />
                </div>

                <div>
                    <h2 class="text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                        Bulk Data Promotion
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Masuk ke halaman bulk data promotion.
                    </p>
                </div>
            </div>
        </a>
    </div>
</x-filament-panels::page>
