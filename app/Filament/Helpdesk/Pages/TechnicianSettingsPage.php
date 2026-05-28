<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\TechnicianSettings;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TechnicianSettingsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Teknisi';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Pengaturan Teknisi';

    protected static ?string $title = 'Pengaturan Teknisi';

    protected string $view = 'filament.helpdesk.pages.technician-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = TechnicianSettings::instance();
        $this->form->fill($settings->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Batas Pekerjaan Harian')
                    ->description('Atur berapa maksimal pekerjaan yang dapat dijadwalkan dalam satu hari.')
                    ->schema([
                        TextInput::make('max_jobs_per_day')
                            ->label('Maksimal Pekerjaan per Hari')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(5)
                            ->helperText('Jika kuota tercapai, pemohon tidak bisa menjadwalkan di tanggal tersebut.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $settings = TechnicianSettings::instance();
        $settings->update($this->form->getState());

        Notification::make()
            ->title('Pengaturan berhasil disimpan')
            ->success()
            ->send();
    }
}
