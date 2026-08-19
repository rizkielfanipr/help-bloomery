<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\BriefingSettings;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BriefingSettingsPage extends Page
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->can('edit briefing settings');
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Human Resources';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Pengaturan Briefing';

    protected static ?string $title = 'Pengaturan Briefing';

    protected string $view = 'filament.helpdesk.pages.briefing-settings-page';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = BriefingSettings::instance();
        $this->form->fill($settings->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Periode Awal Penilaian')
                    ->description('Pada bulan pertama, nilai hanya dihitung mulai tanggal ini. Bulan berikutnya tetap dihitung dari tanggal 1 sampai akhir bulan.')
                    ->schema([
                        DatePicker::make('scoring_started_at')
                            ->label('Mulai Penilaian Briefing')
                            ->required()
                            ->native(false)
                            ->helperText('Khusus Juli 2026 diatur mulai 22 Juli 2026.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Auto-Reject Poin Briefing')
                    ->description('Poin briefing yang sudah diisi staff tapi belum di-approve/reject Supervisor dalam jangka waktu ini akan otomatis ter-reject oleh sistem.')
                    ->schema([
                        Select::make('auto_reject_after_days')
                            ->label('Auto-Reject Setelah')
                            ->options([
                                1 => '1 hari',
                                2 => '2 hari',
                                3 => '3 hari',
                                5 => '5 hari',
                                7 => '7 hari',
                                14 => '14 hari',
                            ])
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('auto_reject_reason')
                            ->label('Alasan Reject (Belum Di-approve)')
                            ->helperText('Gunakan :days untuk menampilkan jumlah hari yang dikonfigurasi di atas.')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Auto-Reject Poin Melewati Deadline')
                    ->description('Alasan yang ditampilkan ketika poin briefing tidak diisi sama sekali sebelum deadline task.')
                    ->schema([
                        TextInput::make('deadline_reject_reason')
                            ->label('Alasan Reject (Melewati Deadline)')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        BriefingSettings::instance()->update($this->form->getState());

        Notification::make()
            ->title('Pengaturan berhasil disimpan')
            ->success()
            ->send();
    }
}
