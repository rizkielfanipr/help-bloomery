<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="startTrip">
            {{ $this->form }}

            <div class="mt-6 flex items-center justify-between">
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Driver\Pages\TripDashboard::getUrl()"
                    color="gray"
                    outlined
                >
                    Batal
                </x-filament::button>

                <x-filament::button
                    type="submit"
                    icon="heroicon-m-play"
                    color="success"
                >
                    Mulai Perjalanan
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
