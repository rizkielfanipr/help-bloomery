<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\SalesReportSettings;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Attributes\Computed;

class SalesReportSettingsPage extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $title = 'Sales Report Settings';

    protected string $view = 'filament.helpdesk.pages.sales-report-settings-page';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('edit sales report settings') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(SalesReportSettings::instance()->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Auto-Reject Sales Report')->description('Aturan global untuk seluruh cabang. Hanya laporan Supervisor Review yang belum di-approve yang dapat ditolak otomatis.')
                ->schema([
                    Toggle::make('auto_reject_enabled')->label('Aktifkan Auto-Reject')->helperText('Laporan yang melewati batas akan diproses pada eksekusi scheduler berikutnya.'),
                    TextInput::make('auto_reject_after_days')->label('Auto-Reject Setelah')->numeric()->rules(['integer'])->minValue(1)->maxValue(2147483647)->suffix('Hari')->required()
                        ->helperText('Isi jumlah hari kalender bebas, minimal 1 hari. Dihitung sebagai hari × 24 jam sejak seluruh shift dikirim.'),
                    DatePicker::make('effective_from')->label('Berlaku Mulai')->required()->helperText('Hanya tanggal laporan mulai tanggal ini yang terkena aturan.'),
                    TextInput::make('auto_reject_reason')->label('Alasan Auto-Reject')->required()->maxLength(255)->helperText('Gunakan :days untuk menampilkan jumlah hari yang diatur.')->columnSpanFull(),
                ])->columns(2),
        ])->statePath('data');
    }

    #[Computed]
    public function overdueCount(): int
    {
        return SalesReportSettings::instance()->eligibleReports()->count();
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        SalesReportSettings::instance()->update($this->form->getState());
        unset($this->overdueCount);
        Notification::make()->title('Pengaturan Sales Report disimpan')->success()->send();
    }
}
