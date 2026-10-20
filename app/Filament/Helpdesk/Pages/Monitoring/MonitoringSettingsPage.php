<?php

namespace App\Filament\Helpdesk\Pages\Monitoring;

use App\Models\MonitoringService;
use App\Services\Monitoring\MonitoringOverview;
use App\Services\Monitoring\MonitoringServiceCatalog;
use App\Services\Monitoring\MonitoringSettings;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Monitoring Settings (docs/system-monitoring-prd.md §17, §19). Every value is validated against
 * `config('monitoring.limits')`; secrets (deployment secret, credentials, recipients' channels)
 * are never editable or displayed here.
 */
class MonitoringSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'System Monitoring';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Pengaturan Monitoring';

    protected static ?string $slug = 'system-monitoring/settings';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.monitoring.settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can('manage monitoring settings') ?? false;
    }

    public function mount(): void
    {
        $settings = app(MonitoringSettings::class);
        $window = $settings->maintenanceWindow();

        $this->form->fill([
            'alerts_in_app' => (bool) $settings->get('alerts.in_app', true),
            'alerts_mail' => (bool) $settings->get('alerts.mail', false),
            'alerts_reminder_minutes' => (int) $settings->get('alerts.reminder_minutes', 60),
            'alerts_auto_incident' => (bool) $settings->get('alerts.auto_incident', false),
            'scheduler_late_seconds' => (int) $settings->get('heartbeats.scheduler_late_seconds', 180),
            'queue_late_seconds' => (int) $settings->get('heartbeats.queue_late_seconds', 300),
            'maintenance_starts_at' => $window['starts_at'] ?? null,
            'maintenance_ends_at' => $window['ends_at'] ?? null,
            'maintenance_reason' => $window['reason'] ?? null,
            'maintenance_service_keys' => $window['service_keys'] ?? [],
            ...collect(array_keys((array) config('monitoring.limits.retention')))->mapWithKeys(fn (string $key): array => ['retention_'.$key => (int) $settings->get('retention.'.$key)])->all(),
            'services' => app(MonitoringServiceCatalog::class)->sync()->sortBy('name')->values()->map(fn (MonitoringService $service): array => [
                'key' => $service->key,
                'name' => $service->name,
                'is_enabled' => $service->is_enabled,
                'check_interval_seconds' => $service->check_interval_seconds,
                'timeout_seconds' => $service->timeout_seconds,
                'failure_threshold' => $service->failure_threshold,
                'recovery_threshold' => $service->recovery_threshold,
            ])->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $limits = (array) config('monitoring.limits');
        $range = fn (TextInput $input, array $bounds): TextInput => $input->integer()->required()->minValue($bounds[0])->maxValue($bounds[1])->helperText("Rentang {$bounds[0]}–{$bounds[1]}");
        $mailReady = config('mail.default') !== 'log' && config('mail.default') !== 'array' && (array) config('monitoring.alerts.mail_recipients', []) !== [];

        return $schema
            ->components([
                Section::make('Alert & Notifikasi')->description('Notifikasi dikirim hanya saat status berubah, eskalasi, pengingat, dan pemulihan.')->columns(['default' => 1, 'md' => 2])->schema([
                    Toggle::make('alerts_in_app')->label('Notifikasi In-App'),
                    Toggle::make('alerts_mail')->label('Email')->helperText($mailReady ? 'Penerima email diatur melalui konfigurasi server.' : 'Not Configured: mailer/penerima email belum dikonfigurasi di server.'),
                    Placeholder::make('whatsapp')->label('WhatsApp')->content('Not Configured — belum ada provider pengiriman WhatsApp yang disetujui.'),
                    Placeholder::make('deployment_ingestion')->label('Callback Deployment')->content(filled(config('monitoring.deployments.ingestion_secret')) ? 'Configured (secret hanya tersedia di server)' : 'Not Configured — gunakan perintah lokal monitoring:record-deployment.'),
                    $range(TextInput::make('alerts_reminder_minutes')->label('Pengingat Alert Belum Diakui (menit)'), $limits['reminder_minutes']),
                    Toggle::make('alerts_auto_incident')->label('Buat Incident Otomatis untuk SEV-1/SEV-2'),
                ]),
                Section::make('Heartbeat')->columns(['default' => 1, 'md' => 2])->schema([
                    $range(TextInput::make('scheduler_late_seconds')->label('Scheduler Terlambat Setelah (detik)'), $limits['heartbeat_late_seconds']),
                    $range(TextInput::make('queue_late_seconds')->label('Queue Terlambat Setelah (detik)'), $limits['heartbeat_late_seconds']),
                ]),
                Section::make('Maintenance Window')->description('Selama maintenance, hasil pemeriksaan tetap dicatat tetapi alert ditahan. Kosongkan tanggal untuk menonaktifkan.')->columns(['default' => 1, 'md' => 2])->schema([
                    DateTimePicker::make('maintenance_starts_at')->label('Mulai')->seconds(false)->timezone('Asia/Jakarta'),
                    DateTimePicker::make('maintenance_ends_at')->label('Selesai')->seconds(false)->timezone('Asia/Jakarta')->after('maintenance_starts_at'),
                    TextInput::make('maintenance_reason')->label('Alasan')->maxLength(255),
                    Select::make('maintenance_service_keys')->label('Layanan (kosong = semua)')->multiple()
                        ->options(fn (): array => MonitoringService::query()->forCurrentEnvironment()->orderBy('name')->pluck('name', 'key')->all()),
                ]),
                Section::make('Retensi Data (hari)')->description('Incident tidak pernah dihapus otomatis. Penghapusan payload tidak menghapus status transaksi.')->columns(['default' => 1, 'md' => 3])->schema(
                    collect($limits['retention'])->map(fn (array $bounds, string $key): TextInput => $range(TextInput::make('retention_'.$key)->label(str($key)->replace('_days', '')->headline()->toString()), $bounds))->values()->all(),
                ),
                Section::make('Pemeriksaan Layanan')->description('Timeout harus lebih pendek dari interval.')->schema([
                    Repeater::make('services')->hiddenLabel()->addable(false)->deletable(false)->reorderable(false)
                        ->itemLabel(fn (array $state): string => (string) ($state['name'] ?? $state['key'] ?? ''))
                        ->columns(['default' => 1, 'md' => 5])
                        ->schema([
                            Toggle::make('is_enabled')->label('Aktif')->inline(false),
                            $range(TextInput::make('check_interval_seconds')->label('Interval (detik)'), $limits['interval_seconds']),
                            $range(TextInput::make('timeout_seconds')->label('Timeout (detik)'), $limits['timeout_seconds']),
                            $range(TextInput::make('failure_threshold')->label('Ambang Gagal'), $limits['threshold']),
                            $range(TextInput::make('recovery_threshold')->label('Ambang Pulih'), $limits['threshold']),
                        ]),
                ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();

        foreach ($data['services'] ?? [] as $index => $service) {
            if ((int) $service['timeout_seconds'] >= (int) $service['check_interval_seconds']) {
                throw ValidationException::withMessages(["data.services.{$index}.timeout_seconds" => 'Timeout harus lebih pendek dari interval.']);
            }
        }

        $hasWindow = filled($data['maintenance_starts_at'] ?? null) && filled($data['maintenance_ends_at'] ?? null);
        $settingsValues = [
            'alerts.in_app' => (bool) $data['alerts_in_app'],
            'alerts.mail' => (bool) $data['alerts_mail'],
            'alerts.reminder_minutes' => (int) $data['alerts_reminder_minutes'],
            'alerts.auto_incident' => (bool) $data['alerts_auto_incident'],
            'heartbeats.scheduler_late_seconds' => (int) $data['scheduler_late_seconds'],
            'heartbeats.queue_late_seconds' => (int) $data['queue_late_seconds'],
            'maintenance.window' => $hasWindow ? [
                'starts_at' => (string) $data['maintenance_starts_at'],
                'ends_at' => (string) $data['maintenance_ends_at'],
                'reason' => $data['maintenance_reason'] ?? null,
                'service_keys' => array_values($data['maintenance_service_keys'] ?? []),
            ] : null,
        ];
        foreach (array_keys((array) config('monitoring.limits.retention')) as $key) {
            $settingsValues['retention.'.$key] = (int) $data['retention_'.$key];
        }

        DB::transaction(function () use ($settingsValues, $data): void {
            app(MonitoringSettings::class)->put($settingsValues, Auth::user());

            foreach ($data['services'] ?? [] as $service) {
                MonitoringService::query()->forCurrentEnvironment()->where('key', $service['key'])->update([
                    'is_enabled' => (bool) $service['is_enabled'],
                    'check_interval_seconds' => (int) $service['check_interval_seconds'],
                    'timeout_seconds' => (int) $service['timeout_seconds'],
                    'failure_threshold' => (int) $service['failure_threshold'],
                    'recovery_threshold' => (int) $service['recovery_threshold'],
                ]);
            }
        });

        app(MonitoringOverview::class)->forget();
        Notification::make()->title('Pengaturan monitoring disimpan')->success()->send();
    }
}
