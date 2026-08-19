<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\DriverTripSettings;
use App\Services\DriverMealAllowanceService;
use Filament\Forms\Components\Select;
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

                Section::make('Rute Lain')
                    ->description('Konfigurasi perjalanan dinamis yang dibuat langsung oleh driver')
                    ->schema([
                        TextInput::make('custom_route_meal_allowance_amount')
                            ->label('Uang Makan Default')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->prefix('Rp')
                            ->helperText('Nominal ini otomatis digunakan untuk setiap Rute Lain.'),
                    ]),

                Section::make('Foto Check-in')
                    ->description('Atur sumber foto, lokasi, watermark, kualitas, dan urutan check-in driver')
                    ->schema([
                        Select::make('checkin_photo_source')->label('Sumber Foto')->options([
                            'camera' => 'Kamera Saja',
                            'camera_gallery' => 'Kamera + Galeri',
                        ])->required(),
                        Toggle::make('require_checkin_photo')->label('Wajib Foto Check-in'),
                        Toggle::make('require_checkin_location')->label('Wajib Lokasi GPS'),
                        Toggle::make('require_waypoint_radius')->label('Wajib Berada dalam Radius Titik'),
                        TextInput::make('default_waypoint_radius')->label('Radius Default Titik')->numeric()->required()->minValue(10)->maxValue(5000)->suffix('meter'),
                        Toggle::make('stamp_checkin_timestamp')->label('Timestamp pada Foto'),
                        Toggle::make('stamp_checkin_coordinates')->label('Koordinat pada Foto'),
                        Toggle::make('stamp_checkin_driver_name')->label('Nama Driver pada Foto'),
                        Toggle::make('stamp_checkin_waypoint_name')->label('Nama Titik pada Foto'),
                        Toggle::make('stamp_checkin_route_name')->label('Nama Rute pada Foto'),
                        Select::make('checkin_photo_quality')->label('Kualitas Foto')->options([
                            60 => 'Rendah (60%)', 75 => 'Sedang (75%)', 85 => 'Tinggi (85%)',
                        ])->required(),
                        Select::make('checkin_photo_max_dimension')->label('Resolusi Maksimal')->options([
                            1280 => '1280 px', 1600 => '1600 px', 1920 => '1920 px',
                        ])->required(),
                        TextInput::make('checkin_max_location_accuracy')->label('Akurasi GPS Maksimal')->numeric()->minValue(1)->suffix('meter')->nullable(),
                        Toggle::make('allow_checkin_retake')->label('Izinkan Check-in Ulang'),
                        Toggle::make('require_sequential_checkin')->label('Wajib Urut Sesuai Titik'),
                    ])->columns(2),
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
