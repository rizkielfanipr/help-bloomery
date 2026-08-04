<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\DriverTripSettings;
use App\Services\DriverMealAllowanceService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DriverTripSettingsPage extends Page
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->can('edit trips');
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Driver';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Pengaturan Trip';

    protected static ?string $title = 'Pengaturan Trip Driver';

    protected string $view = 'filament.helpdesk.pages.driver-trip-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = DriverTripSettings::instance();
        $this->form->fill($settings->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pengaturan Modal BBM')
                    ->description('Konfigurasi apakah driver perlu mengisi data BBM sebelum menyelesaikan perjalanan')
                    ->schema([
                        Toggle::make('show_fuel_modal')
                            ->label('Tampilkan Modal Pengisian BBM')
                            ->helperText('Jika aktif, driver akan ditanya apakah ada pengisian BBM sebelum trip selesai')
                            ->default(true),

                        Toggle::make('require_fuel_attachment')
                            ->label('Wajib Upload Nota BBM')
                            ->helperText('Jika aktif, driver wajib upload nota/struk BBM')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Pengaturan Laporan')
                    ->schema([
                        TextInput::make('report_cutoff_day')
                            ->label('Tanggal Cut-Off Laporan')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(28)
                            ->default(20)
                            ->helperText('Laporan bulanan dihitung dari tanggal ini ke tanggal yang sama bulan berikutnya (misal: 20 → periode 20 bulan lalu sampai 19 bulan ini)'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(DriverMealAllowanceService $mealAllowanceService): void
    {
        $settings = DriverTripSettings::instance();
        $settings->update($this->form->getState());
        $updatedPeriods = $mealAllowanceService->refreshOpenPeriods();

        Notification::make()
            ->title('Pengaturan berhasil disimpan')
            ->body($updatedPeriods > 0
                ? "{$updatedPeriods} periode uang makan yang masih Open ikut diperbarui."
                : 'Tidak ada periode uang makan Open yang perlu diperbarui.')
            ->success()
            ->send();
    }
}
