<?php

namespace App\Filament\Helpdesk\Resources\MaterialSourcings;

use App\Actions\Rnd\Material\ConfirmMaterialSampleReceiptAction;
use App\Actions\Rnd\Material\HandOverMaterialSampleAction;
use App\Actions\Rnd\Material\ReopenMaterialSampleApprovalAction;
use App\Actions\Rnd\Material\ReopenRejectedMaterialSourcingAction;
use App\Actions\Rnd\Material\ReviewLegacyMaterialSourcingAction;
use App\Actions\Rnd\Material\ReviewMaterialSampleAction;
use App\Enums\MaterialSourcingStatus;
use App\Models\RndMaterialSampleCycle;
use App\Models\RndProductEsbMaterial;
use App\Services\Rnd\Material\MaterialSampleEvidenceStore;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;

/**
 * Table actions shared by the two sourcing work queues: Purchasing works in Sourcing Bahan
 * (suppliers, sample handover) and R&D in Review Sourcing (sample receipt and evaluation, legacy
 * supplier approval). Every workflow step calls an Action that authorizes and validates again;
 * visibility only hides buttons.
 */
class MaterialSourcingActions
{
    public static function viewSourcing(): Action
    {
        return Action::make('view_sourcing')
            ->label('Lihat Supplier')
            ->icon('heroicon-o-eye')
            ->tooltip('Lihat Supplier')
            ->color('info')
            ->iconButton()
            ->modalWidth(Width::ThreeExtraLarge)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->extraModalWindowAttributes(['class' => 'material-sourcing-modal'])
            ->modalHeading(fn (RndProductEsbMaterial $record): string => 'Detail Supplier — '.$record->product_name)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->visible(fn (RndProductEsbMaterial $record): bool => $record->sourcings_count > 0)
            ->modalContent(fn (RndProductEsbMaterial $record) => view('filament.helpdesk.material-sourcings.view-sourcing', [
                'record' => $record->load(['sourcings', 'selectedSourcing', 'rndReviewer', 'financeReviewer']),
            ]));
    }

    public static function handOverSample(): Action
    {
        return Action::make('hand_over_sample')
            ->label('Serahkan Sampel')
            ->icon('heroicon-o-gift')
            ->tooltip('Serahkan Sampel ke R&D')
            ->color('primary')
            ->iconButton()
            ->modalHeading(fn (RndProductEsbMaterial $record): string => 'Serahkan Sampel — '.$record->product_name)
            ->modalSubmitActionLabel('Catat Penyerahan')
            ->visible(fn (RndProductEsbMaterial $record): bool => self::isSampleWorkflow($record)
                && in_array($record->sourcing_status, [MaterialSourcingStatus::WaitingPurchasing, MaterialSourcingStatus::SourcingInProgress, MaterialSourcingStatus::ResampleRequested], true)
                && $record->sourcings_count > 0
                && (auth()->user()?->can('handOverSample', $record) ?? false))
            ->schema([
                Select::make('material_sourcing_id')->label('Kandidat Supplier')->required()
                    ->options(fn (RndProductEsbMaterial $record): array => $record->sourcings()->pluck('supplier_name', 'id')->all()),
                TextInput::make('quantity')->label('Jumlah Sampel')->numeric()->required()->gt(0),
                TextInput::make('uom_name')->label('UOM Sampel')->required()->maxLength(50)->default(fn (RndProductEsbMaterial $record): string => (string) $record->uom_name),
                Textarea::make('notes')->label('Catatan Purchasing')->rows(2)->maxLength(2000),
                self::photos('photos', 'Foto Penyerahan'),
            ])
            ->action(fn (RndProductEsbMaterial $record, array $data) => self::run(fn () => app(HandOverMaterialSampleAction::class)->execute($record, auth()->user(), [...$data, 'photos' => array_values((array) $data['photos'])]), 'Penyerahan sampel dicatat'));
    }

    public static function receiveSample(): Action
    {
        return Action::make('receive_sample')
            ->label('Terima Sampel')
            ->icon('heroicon-o-inbox-arrow-down')
            ->tooltip('Konfirmasi Penerimaan Sampel')
            ->color('info')
            ->iconButton()
            ->modalHeading(fn (RndProductEsbMaterial $record): string => 'Konfirmasi Penerimaan Sampel — '.$record->product_name)
            ->modalDescription('Unggah foto penerimaan R&D sendiri. Foto penyerahan Purchasing tidak dihitung sebagai bukti penerimaan.')
            ->modalSubmitActionLabel('Konfirmasi Diterima')
            ->visible(fn (RndProductEsbMaterial $record): bool => self::isSampleWorkflow($record) && $record->sourcing_status === MaterialSourcingStatus::SampleHandedOver && (auth()->user()?->can('receiveSample', $record) ?? false))
            ->schema([
                TextInput::make('received_quantity')->label('Jumlah Diterima')->numeric()->required()->gt(0),
                Select::make('received_condition')->label('Kondisi')->options(ConfirmMaterialSampleReceiptAction::CONDITIONS)->required(),
                Textarea::make('notes')->label('Catatan Penerimaan')->rows(2)->maxLength(2000),
                self::photos('photos', 'Foto Penerimaan R&D'),
            ])
            ->action(fn (RndProductEsbMaterial $record, array $data) => self::run(fn () => app(ConfirmMaterialSampleReceiptAction::class)->execute($record, auth()->user(), [...$data, 'photos' => array_values((array) $data['photos'])]), 'Penerimaan sampel dikonfirmasi'));
    }

    public static function evaluateSample(): Action
    {
        return Action::make('evaluate_sample')
            ->label('Evaluasi Sampel')
            ->icon('heroicon-o-beaker')
            ->tooltip('Evaluasi Sampel')
            ->color('success')
            ->iconButton()
            ->modalHeading(fn (RndProductEsbMaterial $record): string => 'Evaluasi Sampel — '.$record->product_name)
            ->modalSubmitActionLabel('Simpan Evaluasi')
            ->visible(fn (RndProductEsbMaterial $record): bool => self::isSampleWorkflow($record) && $record->sourcing_status === MaterialSourcingStatus::SampleReceived && (auth()->user()?->can('evaluateSample', $record) ?? false))
            ->schema([
                Select::make('decision')->label('Hasil Evaluasi')->required()->live()->options([
                    RndMaterialSampleCycle::EVALUATION_APPROVE => 'Approve — lanjut ke Product Pricelist',
                    RndMaterialSampleCycle::EVALUATION_RESAMPLE => 'Request Resample — siklus sampel baru',
                    RndMaterialSampleCycle::EVALUATION_REJECT => 'Reject — tidak lanjut ke Finance',
                ]),
                Textarea::make('reason')->label('Alasan')->rows(3)->maxLength(2000)
                    ->required(fn (Get $get): bool => in_array($get('decision'), [RndMaterialSampleCycle::EVALUATION_RESAMPLE, RndMaterialSampleCycle::EVALUATION_REJECT], true)),
            ])
            ->action(fn (RndProductEsbMaterial $record, array $data) => self::run(fn () => app(ReviewMaterialSampleAction::class)->execute($record, auth()->user(), $data['decision'], $data['reason'] ?? null), 'Evaluasi sampel disimpan'));
    }

    public static function reopenSampleApproval(): Action
    {
        return Action::make('reopen_sample_approval')
            ->label('Buka Ulang Approval Sampel')
            ->icon('heroicon-o-lock-open')
            ->tooltip('Buka Ulang Approval Sampel')
            ->color('warning')
            ->iconButton()
            ->visible(fn (RndProductEsbMaterial $record): bool => self::isSampleWorkflow($record) && $record->sourcing_status === MaterialSourcingStatus::ApprovedByRnd && (auth()->user()?->can('reopenSampleApproval', $record) ?? false))
            ->schema([Textarea::make('reason')->label('Alasan')->required()->minLength(10)->rows(3)])
            ->action(fn (RndProductEsbMaterial $record, array $data) => self::run(fn () => app(ReopenMaterialSampleApprovalAction::class)->execute($record, auth()->user(), $data['reason']), 'Approval sampel dibuka ulang'));
    }

    public static function reopenRejectedSourcing(): Action
    {
        return Action::make('reopen_rejected_sourcing')
            ->label('Buka Kembali Sourcing')
            ->icon('heroicon-o-arrow-path')
            ->tooltip('Buka Kembali Sourcing')
            ->color('warning')
            ->iconButton()
            ->modalDescription('Sourcing kembali ke antrean Purchasing. Riwayat sampel yang ditolak tetap tersimpan.')
            ->visible(fn (RndProductEsbMaterial $record): bool => self::isSampleWorkflow($record) && $record->sourcing_status === MaterialSourcingStatus::RejectedByRnd && (auth()->user()?->can('reopenSampleApproval', $record) ?? false))
            ->schema([Textarea::make('reason')->label('Alasan')->required()->minLength(10)->rows(3)])
            ->action(fn (RndProductEsbMaterial $record, array $data) => self::run(fn () => app(ReopenRejectedMaterialSourcingAction::class)->execute($record, auth()->user(), $data['reason']), 'Sourcing dibuka kembali ke Purchasing'));
    }

    public static function sampleHistory(): Action
    {
        return Action::make('sample_history')
            ->label('Riwayat Sampel')
            ->icon('heroicon-o-clock')
            ->tooltip('Riwayat Sampel')
            ->color('gray')
            ->iconButton()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading(fn (RndProductEsbMaterial $record): string => 'Riwayat Sampel — '.$record->product_name)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->visible(fn (RndProductEsbMaterial $record): bool => self::isSampleWorkflow($record) && (auth()->user()?->can('viewSampleEvidence', $record) ?? false))
            ->modalContent(fn (RndProductEsbMaterial $record) => view('filament.helpdesk.material-sourcings.sample-history', [
                'cycles' => $record->sampleCycles()->with(['supplierCandidate:id,supplier_name,price', 'attachments', 'handedOverBy:id,name', 'receivedBy:id,name', 'evaluatedBy:id,name'])->get(),
            ]));
    }

    /**
     * Legacy rows (pre-workflow and standalone sourcing): R&D picks the winning supplier.
     */
    public static function approveRnd(): Action
    {
        return Action::make('approve_rnd')
            ->label('Setujui (RnD)')
            ->icon('heroicon-o-check-circle')
            ->tooltip('Setujui (RnD)')
            ->color('success')
            ->iconButton()
            ->modalWidth(Width::ThreeExtraLarge)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->extraModalWindowAttributes(['class' => 'material-sourcing-modal'])
            ->modalHeading(fn (RndProductEsbMaterial $record): string => 'Setujui Supplier Pilihan RnD — '.$record->product_name)
            ->modalSubmitActionLabel('Setujui & Kirim ke Finance')
            ->visible(fn (RndProductEsbMaterial $record): bool => auth()->user()?->can('review material sourcing as rnd')
                && $record->sourcing_status === MaterialSourcingStatus::PendingRndReview)
            ->form([
                ViewField::make('sourcing_selected_id')
                    ->view('filament.helpdesk.material-sourcings.supplier-selection-cards')
                    ->hiddenLabel()
                    ->required(),
                Textarea::make('rnd_note')->label('Catatan'),
            ])
            ->action(fn (RndProductEsbMaterial $record, array $data) => self::run(
                fn () => app(ReviewLegacyMaterialSourcingAction::class)->execute($record, auth()->user(), 'approve', (int) $data['sourcing_selected_id'], $data['rnd_note'] ?? null),
                'Sourcing disetujui, menunggu review Finance',
            ));
    }

    /**
     * Legacy rows (pre-workflow and standalone sourcing): R&D returns the submission to Purchasing.
     */
    public static function rejectRnd(): Action
    {
        return Action::make('reject_rnd')
            ->label('Tolak (RnD)')
            ->icon('heroicon-o-x-circle')
            ->tooltip('Tolak (RnD)')
            ->color('danger')
            ->iconButton()
            ->modalHeading('Tolak Pengajuan Supplier')
            ->modalSubmitActionLabel('Tolak & Kembalikan')
            ->visible(fn (RndProductEsbMaterial $record): bool => auth()->user()?->can('review material sourcing as rnd')
                && $record->sourcing_status === MaterialSourcingStatus::PendingRndReview)
            ->form([
                Textarea::make('rnd_note')->label('Alasan Penolakan')->required()->minLength(5),
            ])
            ->action(fn (RndProductEsbMaterial $record, array $data) => self::run(
                fn () => app(ReviewLegacyMaterialSourcingAction::class)->execute($record, auth()->user(), 'reject', null, $data['rnd_note']),
                'Sourcing ditolak, dikembalikan ke Purchasing',
            ));
    }

    private static function isSampleWorkflow(RndProductEsbMaterial $record): bool
    {
        return $record->isInWorkflow() && $record->requiresSourcing();
    }

    private static function run(callable $operation, string $success): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            Notification::make()->title('Tidak dapat diproses')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();

            return;
        }

        Notification::make()->title($success)->success()->send();
    }

    private static function photos(string $name, string $label): FileUpload
    {
        return FileUpload::make($name)->label($label)->image()->multiple()->required()
            ->maxFiles(MaterialSampleEvidenceStore::MAX_FILES)->maxSize(MaterialSampleEvidenceStore::MAX_KILOBYTES)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->storeFiles(false)
            ->helperText('JPG/PNG/WEBP, maksimal 5 foto @ 5 MB. Disimpan privat.');
    }
}
