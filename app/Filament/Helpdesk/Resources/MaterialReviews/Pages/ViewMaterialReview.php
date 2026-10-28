<?php

namespace App\Filament\Helpdesk\Resources\MaterialReviews\Pages;

use App\Actions\Rnd\Material\AmendMaterialTechnicalDataAction;
use App\Actions\Rnd\Material\ApproveMaterialForEsbAction;
use App\Actions\Rnd\Material\ClaimMaterialReviewAction;
use App\Actions\Rnd\Material\FindEsbProductCandidatesAction;
use App\Actions\Rnd\Material\LinkMaterialToExistingEsbProductAction;
use App\Actions\Rnd\Material\ReconcileMaterialEsbSyncAction;
use App\Actions\Rnd\Material\ReturnMaterialForRevisionAction;
use App\Actions\Rnd\Material\SyncMaterialToEsbAction;
use App\Enums\MaterialEsbSyncStatus;
use App\Enums\MaterialReviewStatus;
use App\Filament\Helpdesk\Resources\MaterialReviews\MaterialReviewResource;
use App\Models\NotificationDelivery;
use App\Models\RndMaterialAuditLog;
use App\Models\RndMaterialReview;
use App\Models\RndProductEsbMaterial;
use App\Services\Notifications\NotificationDeliveryRecorder;
use App\Services\Rnd\Material\MaterialSnapshot;
use App\Services\Rnd\Material\MaterialSubmissionValidator;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * IT review detail (§22.2): identity, units, automatic checklist, duplicate candidates, change
 * diff, history, ESB and Telegram status, and the review decisions. Every button calls an Action
 * that authorizes again server-side.
 */
class ViewMaterialReview extends ViewRecord
{
    protected static string $resource = MaterialReviewResource::class;

    protected string $view = 'filament.helpdesk.material-reviews.view';

    protected Width|string|null $maxContentWidth = Width::Full;

    /** Submission version shown when the page was opened; decisions on a newer version are refused. */
    #[Locked]
    public int $loadedVersion = 0;

    /** @var list<array{productID: int, productCode: string, productName: string, match: string}> */
    public array $esbCandidates = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->load('units');
        $this->loadedVersion = (int) $this->material()->submission_version;
    }

    public function getTitle(): string
    {
        return 'Review Data';
    }

    public function material(): RndProductEsbMaterial
    {
        /** @var RndProductEsbMaterial $record */
        $record = $this->getRecord();

        return $record;
    }

    #[Computed]
    public function currentReview(): ?RndMaterialReview
    {
        return $this->material()->reviews()->with(['submitter:id,name', 'claimer:id,name', 'reviewer:id,name'])->first();
    }

    /** @return Collection<int, RndMaterialReview> */
    #[Computed]
    public function reviewHistory(): Collection
    {
        return $this->material()->reviews()->with(['submitter:id,name', 'reviewer:id,name'])->limit(20)->get();
    }

    /** @return Collection<int, RndMaterialAuditLog> */
    #[Computed]
    public function auditTrail(): Collection
    {
        return $this->material()->auditLogs()->with('actor:id,name')->limit(50)->get();
    }

    /** @return Collection<int, NotificationDelivery> */
    #[Computed]
    public function deliveries(): Collection
    {
        return NotificationDelivery::query()->where('aggregate_type', $this->material()->getMorphClass())->where('aggregate_id', $this->material()->id)->latest('id')->limit(10)->get();
    }

    /** @return list<array{label: string, ok: bool, detail: ?string}> */
    #[Computed]
    public function checklist(): array
    {
        $material = $this->material();
        try {
            app(MaterialSubmissionValidator::class)->validate($material);
            $errors = [];
        } catch (ValidationException $exception) {
            $errors = collect($exception->errors())->flatten()->all();
        }
        $review = $this->currentReview;
        $snapshotMatches = $review && hash_equals($review->snapshot_hash, app(MaterialSnapshot::class)->hashFor($material));

        return [
            ['label' => 'Field wajib, kode, kategori, dan unit valid', 'ok' => $errors === [], 'detail' => $errors === [] ? null : implode(' ', $errors)],
            ['label' => 'Data sama dengan snapshot yang diajukan', 'ok' => $snapshotMatches, 'detail' => $snapshotMatches ? null : 'Data berubah setelah diajukan; approval akan ditolak.'],
            ['label' => 'Tidak ada kandidat duplikasi lokal', 'ok' => $this->duplicateCandidates->isEmpty(), 'detail' => $this->duplicateCandidates->isEmpty() ? null : 'Periksa kandidat duplikasi di bawah.'],
            ['label' => 'Jenis bahan menentukan sourcing', 'ok' => true, 'detail' => $material->requiresSourcing() ? 'Bahan biasa: setelah ESB synced masuk sourcing & sampel.' : 'WIP: tanpa sourcing, langsung Pricelist setelah synced.'],
        ];
    }

    /** @return Collection<int, RndProductEsbMaterial> */
    #[Computed]
    public function duplicateCandidates(): Collection
    {
        return app(MaterialSubmissionValidator::class)->duplicateCandidates($this->material());
    }

    public function isClaimedByMe(): bool
    {
        return $this->currentReview?->status === MaterialReviewStatus::UnderItReview && $this->currentReview->claimed_by === auth()->id();
    }

    public function retryDelivery(int $deliveryId): void
    {
        abort_unless(Gate::allows('retryNotification', $this->material()), 403);
        $delivery = NotificationDelivery::query()->where('aggregate_type', $this->material()->getMorphClass())->where('aggregate_id', $this->material()->id)->findOrFail($deliveryId);

        app(NotificationDeliveryRecorder::class)->retry($delivery)
            ? Notification::make()->title('Telegram dijadwalkan ulang')->success()->send()
            : Notification::make()->title('Notifikasi tidak dapat dikirim ulang')->body('Hanya delivery gagal dengan konfigurasi aktif yang dapat dicoba ulang.')->warning()->send();
        unset($this->deliveries);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('claim')
                ->label('Ambil Review')
                ->icon('heroicon-o-hand-raised')
                ->visible(fn (): bool => $this->currentReview?->status === MaterialReviewStatus::WaitingItReview && Gate::allows('reviewAsIt', $this->material()))
                ->action(fn () => $this->run(fn () => app(ClaimMaterialReviewAction::class)->execute($this->material(), auth()->user()), 'Review diambil')),

            Action::make('approvePush')
                ->label('Setujui & Kirim ke ESB')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Data versi ini disetujui lalu dikirim ke ESB satu kali. Hasil dibaca kembali; status Synced hanya diberikan bila seluruh Product Detail cocok.')
                ->schema([Textarea::make('note')->label('Catatan Approval')->rows(2)->maxLength(1000)])
                ->visible(fn (): bool => $this->isClaimedByMe() && Gate::allows('approveEsb', $this->material()))
                ->action(fn (array $data) => $this->run(fn () => app(ApproveMaterialForEsbAction::class)->execute($this->material(), auth()->user(), $this->loadedVersion, $data['note'] ?? null), 'Disetujui dan diproses ke ESB')),

            Action::make('linkExisting')
                ->label('Tautkan Produk Existing')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->visible(fn (): bool => $this->isClaimedByMe() && Gate::allows('approveEsb', $this->material()))
                ->modalDescription('Produk dibaca dari ESB lalu setiap unit harus cocok dengan Product Detail aktif. Nama saja tidak cukup.')
                ->schema([
                    TextInput::make('product_id')->label('Product ID ESB')->required()->integer()->minValue(1)
                        ->helperText(fn (): string => $this->esbCandidates === [] ? 'Gunakan "Cari di ESB" untuk melihat kandidat.' : 'Kandidat: '.collect($this->esbCandidates)->map(fn ($candidate): string => "#{$candidate['productID']} {$candidate['productCode']}")->implode(', ')),
                    Textarea::make('note')->label('Alasan Penautan')->required()->minLength(10)->rows(2),
                ])
                ->action(fn (array $data) => $this->run(fn () => app(LinkMaterialToExistingEsbProductAction::class)->execute($this->material(), auth()->user(), (int) $data['product_id'], $this->loadedVersion, $data['note']), 'Produk existing ditautkan dan terverifikasi')),

            Action::make('returnRevision')
                ->label('Kembalikan ke R&D')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => $this->isClaimedByMe())
                ->schema([Textarea::make('reason')->label('Alasan Revisi')->required()->minLength(10)->rows(3)])
                ->action(fn (array $data) => $this->run(fn () => app(ReturnMaterialForRevisionAction::class)->execute($this->material(), auth()->user(), $data['reason'], $this->loadedVersion), 'Dikembalikan ke R&D')),

            ActionGroup::make([
                Action::make('findCandidates')
                    ->label('Cari di ESB')
                    ->icon('heroicon-o-magnifying-glass')
                    ->visible(fn (): bool => Gate::allows('reviewAsIt', $this->material()))
                    ->action(function (): void {
                        $this->run(function (): void {
                            $this->esbCandidates = app(FindEsbProductCandidatesAction::class)->execute($this->material(), auth()->user());
                        }, 'Pencarian ESB selesai');
                    }),
                Action::make('amendTechnical')
                    ->label('Edit Data Teknis')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->visible(fn (): bool => $this->isClaimedByMe() && Gate::allows('amendTechnical', $this->material()))
                    ->modalWidth(Width::FourExtraLarge)
                    ->fillForm(fn (): array => [
                        'category_id' => $this->material()->category_id,
                        'category_name' => $this->material()->category_name,
                        'sub_category_id' => $this->material()->sub_category_id,
                        'sub_category_name' => $this->material()->sub_category_name,
                        'units' => $this->material()->units()->get()->map(fn ($unit): array => $unit->only(['id', 'uom_id', 'uom_name', 'sku', 'conversion_factor', 'is_base', 'is_stock', 'is_purchase', 'is_transfer', 'is_sales', 'flag_active']))->all(),
                    ])
                    ->schema([
                        TextInput::make('category_id')->label('Category ID')->integer()->required()->minValue(1),
                        TextInput::make('category_name')->label('Kategori')->maxLength(255),
                        TextInput::make('sub_category_id')->label('Sub Category ID')->integer()->required()->minValue(1),
                        TextInput::make('sub_category_name')->label('Subkategori')->maxLength(255),
                        Repeater::make('units')->label('Unit')->addable(false)->deletable(false)->reorderable(false)->columns(4)->schema([
                            TextInput::make('id')->hidden(),
                            TextInput::make('uom_id')->label('UOM ID')->integer()->required()->minValue(1),
                            TextInput::make('uom_name')->label('UOM')->required()->maxLength(50),
                            TextInput::make('sku')->label('SKU')->required()->maxLength(50),
                            TextInput::make('conversion_factor')->label('Conversion Factor')->numeric()->required()->gt(0),
                            Toggle::make('is_base')->label('Base'),
                            Toggle::make('is_stock')->label('Stock'),
                            Toggle::make('is_purchase')->label('Purchase'),
                            Toggle::make('is_transfer')->label('Transfer'),
                            Toggle::make('is_sales')->label('Sales'),
                            Toggle::make('flag_active')->label('Aktif'),
                        ]),
                        Textarea::make('reason')->label('Alasan Perubahan')->required()->minLength(10)->rows(2),
                    ])
                    ->action(function (array $data): void {
                        $units = collect($data['units'] ?? [])->keyBy('id')->map(fn (array $unit): array => collect($unit)->except('id')->all())->all();
                        $this->run(fn () => app(AmendMaterialTechnicalDataAction::class)->execute($this->material(), auth()->user(), [
                            'category_id' => (int) $data['category_id'],
                            'category_name' => $data['category_name'] ?? null,
                            'sub_category_id' => (int) $data['sub_category_id'],
                            'sub_category_name' => $data['sub_category_name'] ?? null,
                            'units' => $units,
                        ], $data['reason'], $this->loadedVersion), 'Data teknis diperbarui dan dicatat');
                    }),
                Action::make('retryPush')
                    ->label('Coba Kirim Ulang ke ESB')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalDescription('Hanya untuk kegagalan yang terbukti tidak tercatat di ESB. Approval IT versi ini tetap berlaku.')
                    ->visible(fn (): bool => $this->material()->esb_sync_status === MaterialEsbSyncStatus::Failed && $this->material()->review_status === MaterialReviewStatus::ApprovedForEsb && Gate::allows('approveEsb', $this->material()))
                    ->action(fn () => $this->run(fn () => app(SyncMaterialToEsbAction::class)->execute($this->material(), auth()->user()), 'Proses ESB dijalankan')),
                Action::make('reconcileVerify')
                    ->label('Rekonsiliasi dengan ESB')
                    ->icon('heroicon-o-check-badge')
                    ->visible(fn (): bool => $this->material()->needsEsbReconciliation() && Gate::allows('reconcileEsb', $this->material()))
                    ->fillForm(fn (): array => ['product_id' => $this->material()->esb_product_id ?: $this->material()->finishedEsbClaimTransaction()?->external_reference])
                    ->schema([
                        TextInput::make('product_id')->label('Product ID ESB')->required()->integer()->minValue(1),
                        Textarea::make('reason')->label('Hasil Pemeriksaan')->required()->minLength(10)->rows(2),
                    ])
                    ->action(fn (array $data) => $this->run(fn () => app(ReconcileMaterialEsbSyncAction::class)->verify($this->material(), auth()->user(), (int) $data['product_id'], $data['reason']), 'Rekonsiliasi selesai: produk terverifikasi')),
                Action::make('reconcileNotApplied')
                    ->label('Tandai Tidak Tercatat di ESB')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (): bool => $this->material()->needsEsbReconciliation() && Gate::allows('reconcileEsb', $this->material()))
                    ->modalDescription('Gunakan hanya setelah memastikan di ESB bahwa produk/perubahan tidak tercatat. Setelah itu push dapat dicoba ulang dengan aman.')
                    ->schema([Textarea::make('reason')->label('Bukti Pemeriksaan')->required()->minLength(10)->rows(2)])
                    ->action(fn (array $data) => $this->run(fn () => app(ReconcileMaterialEsbSyncAction::class)->markNotApplied($this->material(), auth()->user(), $data['reason']), 'Ditandai belum tercatat; push dapat dicoba ulang')),
            ])->label('Aksi Lain')->icon('heroicon-o-ellipsis-vertical')->button()->color('gray'),
        ];
    }

    private function run(callable $operation, string $success): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            Notification::make()->title('Tidak dapat diproses')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();

            return;
        }

        $this->record = $this->material()->fresh(['units']);
        $this->loadedVersion = (int) $this->material()->submission_version;
        unset($this->currentReview, $this->reviewHistory, $this->auditTrail, $this->deliveries, $this->checklist, $this->duplicateCandidates);

        $status = $this->material()->esb_sync_status;
        $status === MaterialEsbSyncStatus::NeedsReconciliation || $status === MaterialEsbSyncStatus::Failed
            ? Notification::make()->title('ESB: '.$status->getLabel())->body($this->material()->sync_error)->warning()->send()
            : Notification::make()->title($success)->success()->send();
    }
}
