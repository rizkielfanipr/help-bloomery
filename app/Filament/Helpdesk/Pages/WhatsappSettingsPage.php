<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\User;
use App\Models\WhatsappNotificationSetting;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class WhatsappSettingsPage extends Page
{
    public static function canAccess(): bool
    {
        return auth()->user()?->can('edit whatsapp settings') ?? false;
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'WhatsApp';

    protected static ?string $title = 'Pengaturan WhatsApp';

    protected static ?string $slug = 'whatsapp-settings';

    protected string $view = 'filament.helpdesk.pages.whatsapp-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = [];

        foreach (array_keys(WhatsappNotificationSetting::MODULES) as $moduleKey) {
            $settings[$moduleKey] = WhatsappNotificationSetting::forModule($moduleKey)->only([
                'is_enabled', 'pic_user_id', 'message_template',
            ]);
        }

        $this->form->fill(['settings' => $settings]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(collect(WhatsappNotificationSetting::MODULES)
                ->map(fn (string $label, string $moduleKey) => Section::make($label)
                    ->description('Placeholder pesan: '.$this->placeholdersFor($moduleKey))
                    ->collapsible()
                    ->schema([
                        Toggle::make("settings.{$moduleKey}.is_enabled")
                            ->label('Aktifkan CTA WhatsApp')
                            ->helperText('Jika nonaktif, tombol WhatsApp tidak akan muncul setelah user submit form ini.')
                            ->live(),

                        Select::make("settings.{$moduleKey}.pic_user_id")
                            ->label('PIC (Person In Charge)')
                            ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->helperText('Nomor WA otomatis diambil dari nomor HP akun PIC ini.')
                            ->required(fn (Get $get): bool => (bool) $get("settings.{$moduleKey}.is_enabled")),

                        Textarea::make("settings.{$moduleKey}.message_template")
                            ->label('Template Pesan WhatsApp')
                            ->rows(6)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2))
                ->values()
                ->all())
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach ($state['settings'] as $moduleKey => $moduleData) {
            WhatsappNotificationSetting::forModule($moduleKey)->update($moduleData);
        }

        Notification::make()
            ->title('Pengaturan berhasil disimpan')
            ->success()
            ->send();
    }

    private function placeholdersFor(string $moduleKey): string
    {
        return match ($moduleKey) {
            'design_request' => '{cabang}, {requester}, {kode}, {judul}, {kategori}, {brief}, {link}',
            'content_request' => '{cabang}, {requester}, {kode}, {judul}, {jenis}, {platform}, {tujuan}, {link}',
            'erp_request' => '{cabang}, {requester}, {kode}, {modul}, {request_type}, {keterangan}, {link}',
            'purchase_request' => '{cabang}, {requester}, {kode}, {nama_barang}, {jumlah}, {alasan}, {link}',
            'service_request' => '{cabang}, {requester}, {kode}, {tanggal}, {deskripsi}, {link}',
            default => '',
        };
    }
}
