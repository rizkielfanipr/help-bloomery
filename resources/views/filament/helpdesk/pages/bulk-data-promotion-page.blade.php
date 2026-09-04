<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="submit">
            {{ $this->form }}

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <x-filament::button
                    tag="a"
                    color="gray"
                    href="{{ route('filament.helpdesk.pages.bulk-data') }}"
                    icon="heroicon-m-arrow-left"
                >
                    Kembali ke Bulk Data
                </x-filament::button>

                <x-filament::button type="submit" icon="heroicon-m-paper-airplane">
                    Submit Promotion Free Item
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
