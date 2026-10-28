<?php

namespace App\Filament\Helpdesk\Resources\MonitoringTransactions\Pages;

use App\Actions\Rnd\Bom\ReconcileBomAdjustmentAction;
use App\Enums\MonitoringTransactionStatus;
use App\Enums\RndBomChangeLogStatus;
use App\Filament\Helpdesk\Resources\MonitoringTransactions\MonitoringTransactionResource;
use App\Models\MonitoringTransaction;
use App\Models\RndBomChangeLog;
use App\Services\Monitoring\EsbTransactionRecorder;
use App\Services\Monitoring\MonitoringLocalRecordLinker;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Throwable;

/**
 * Transaction detail: identity, attempt timeline, sanitized summaries (permission-gated),
 * linked domain record, and the reconciliation decision (docs/products/system-monitoring/prd.md §11.3-§11.6).
 */
class ViewMonitoringTransaction extends ViewRecord
{
    protected static string $resource = MonitoringTransactionResource::class;

    protected string $view = 'filament.helpdesk.monitoring.transaction-view';

    protected Width|string|null $maxContentWidth = Width::Full;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing(['actor:id,name', 'branch:id,name', 'reconciledBy:id,name']);
    }

    public function getTitle(): string
    {
        return 'Detail Transaksi ESB';
    }

    public function transaction(): MonitoringTransaction
    {
        /** @var MonitoringTransaction $record */
        $record = $this->getRecord();

        return $record;
    }

    public function canViewPayload(): bool
    {
        return Gate::allows('viewPayload', $this->transaction());
    }

    /** @return Collection<int, MonitoringTransaction> */
    #[Computed]
    public function attempts(): Collection
    {
        $columns = ['id', 'uuid', 'parent_id', 'operation_type', 'target_key', 'company_code', 'method', 'endpoint_template', 'status',
            'attempt_count', 'mutation_sent', 'http_status', 'external_reference', 'error_class', 'error_code', 'safe_error_message',
            'started_at', 'completed_at', 'duration_ms'];

        return $this->transaction()->attempts()
            ->select($this->canViewPayload() ? [...$columns, 'request_summary', 'response_summary', 'payload_expired_at'] : $columns)
            ->get();
    }

    /** @return array{label: string, url: ?string, missing: bool}|null */
    #[Computed]
    public function localRecord(): ?array
    {
        return app(MonitoringLocalRecordLinker::class)->resolve($this->transaction());
    }

    public function retryGuidance(): string
    {
        $transaction = $this->transaction();

        return match (true) {
            $transaction->status === MonitoringTransactionStatus::Unknown => 'Hasil di ESB tidak pasti. Jangan kirim ulang. Rekonsiliasi dengan ESB terlebih dahulu (cek berdasarkan referensi), lalu tandai hasilnya.',
            in_array($transaction->status, [MonitoringTransactionStatus::Succeeded, MonitoringTransactionStatus::Reconciled], true) => 'Tidak ada tindakan yang diperlukan.',
            $transaction->status === MonitoringTransactionStatus::Processing => 'Transaksi masih berjalan atau prosesnya terhenti sebelum selesai. Jangan kirim ulang sebelum hasilnya dipastikan.',
            $transaction->source_module === 'bulk_product' => 'Coba Ulang tersedia di halaman Bulk Data Product hanya untuk target berstatus failed. Target sukses dan unknown tidak dikirim ulang.',
            $transaction->source_module === 'item_journal' => 'Upload attachment yang gagal dapat dicoba ulang dari aplikasi Item Journal; jurnal tidak dibuat ulang. Create jurnal yang gagal diperiksa manual.',
            $transaction->source_module === 'rnd_product_release' => 'Bahan berstatus failed dapat dikirim ulang dari halaman Product Release setelah penyebab penolakan diperbaiki.',
            in_array($transaction->source_module, ['rnd_bom', 'bom_adjustment'], true) => 'Perbaiki data lalu simpan ulang dari editor BOM; setiap simpan membuat change log dan transaksi baru.',
            $transaction->source_module === 'bulk_promotion' => 'Endpoint promotion belum terbukti idempotent. Tinjau target gagal secara manual sebelum mengirim ulang; tidak ada tombol Coba Ulang.',
            $transaction->source_module === 'goods_receipt' => 'Create Goods Receipt tidak dicoba ulang otomatis. Periksa penolakan ESB dan proses ulang penerimaan secara manual.',
            default => 'Tinjau manual. Coba ulang hanya bila domain terkait menyatakan aman.',
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reconcileBomWithEsb')
                ->label('Rekonsiliasi dengan ESB')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Mengambil BOM terbaru dari ESB (read-only) dan membandingkannya dengan perubahan yang diminta. Mutation tidak dikirim ulang.')
                ->visible(fn (): bool => $this->bomChangeLogAwaitingReconciliation() !== null
                    && Gate::allows('reconcile', $this->transaction())
                    && (auth()->user()?->can('reconcile bom adjustments') ?? false))
                ->action(function (): void {
                    $changeLog = $this->bomChangeLogAwaitingReconciliation();
                    abort_unless($changeLog && Gate::allows('reconcile', $this->transaction()) && auth()->user()?->can('reconcile bom adjustments'), 403);

                    try {
                        $result = app(ReconcileBomAdjustmentAction::class)->execute($changeLog, auth()->user());
                        Notification::make()->title('Rekonsiliasi selesai')->body('Hasil BOM: '.$result->status->getLabel())->success()->send();
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()->title('Rekonsiliasi gagal')->body('ESB belum dapat dibaca. Coba beberapa saat lagi.')->danger()->send();
                    }

                    $this->refreshRecord();
                }),
            Action::make('recordReconciliation')
                ->label('Catat Hasil Rekonsiliasi')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('gray')
                ->visible(fn (): bool => Gate::allows('reconcile', $this->transaction()))
                ->modalHeading('Catat Hasil Rekonsiliasi Manual')
                ->modalDescription('Gunakan setelah memeriksa ESB secara langsung. Hanya catatan monitoring yang berubah; data domain tidak diubah.')
                ->schema([
                    Select::make('outcome')->label('Hasil Pemeriksaan')->required()->options([
                        'confirmed_applied' => 'Terkonfirmasi sudah tercatat di ESB',
                        'confirmed_not_applied' => 'Terkonfirmasi tidak tercatat di ESB',
                        'manual_review_closed' => 'Ditutup setelah tinjauan manual',
                    ]),
                    Textarea::make('note')->label('Catatan Pemeriksaan')->required()->minLength(10)->maxLength(1000)->rows(3),
                ])
                ->action(function (array $data): void {
                    abort_unless(Gate::allows('reconcile', $this->transaction()), 403);
                    $saved = app(EsbTransactionRecorder::class)->reconcile($this->transaction(), $data['outcome'], $data['note'], auth()->user());

                    $saved
                        ? Notification::make()->title('Hasil rekonsiliasi dicatat')->success()->send()
                        : Notification::make()->title('Transaksi tidak dapat direkonsiliasi')->warning()->send();
                    $this->refreshRecord();
                }),
        ];
    }

    private function bomChangeLogAwaitingReconciliation(): ?RndBomChangeLog
    {
        $transaction = $this->transaction();
        if ($transaction->local_model_type !== RndBomChangeLog::class || $transaction->status !== MonitoringTransactionStatus::Unknown) {
            return null;
        }

        $changeLog = RndBomChangeLog::query()->find($transaction->local_model_id);

        return $changeLog?->status === RndBomChangeLogStatus::NeedsReconciliation ? $changeLog : null;
    }

    private function refreshRecord(): void
    {
        $this->record = $this->transaction()->fresh(['actor:id,name', 'branch:id,name', 'reconciledBy:id,name']);
        unset($this->attempts, $this->localRecord);
    }
}
