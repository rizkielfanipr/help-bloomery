<?php

namespace App\Filament\Helpdesk\Resources\NonPoReceipts\Pages;

use App\Actions\Purchasing\NonPoReceipt\CancelNonPoReceiptAction;
use App\Actions\Purchasing\NonPoReceipt\ComparePurchaseOrderAction;
use App\Actions\Purchasing\NonPoReceipt\RequestNonPoReceiptRevisionAction;
use App\Actions\Purchasing\NonPoReceipt\ResolveNonPoGoodsReceiptAction;
use App\Actions\Purchasing\NonPoReceipt\StartNonPoReceiptProcessingAction;
use App\Actions\Purchasing\NonPoReceipt\VerifyPurchaseOrderAndProcessGoodsReceiptAction;
use App\Enums\NonPoReceiptStatus;
use App\Filament\Helpdesk\Resources\NonPoReceipts\NonPoReceiptResource;
use App\Models\NonPoReceipt;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class ViewNonPoReceipt extends ViewRecord
{
    protected static string $resource = NonPoReceiptResource::class;

    /** @var array<string, mixed>|null Display only: every verification fetches the PO again. */
    #[Locked]
    public ?array $poComparison = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('startProcessing')->label('Mulai Proses PO')->icon('heroicon-o-play')->color('primary')
                ->visible(fn (NonPoReceipt $record): bool => auth()->user()->can('startProcessing', $record))
                ->requiresConfirmation()
                ->action(function (NonPoReceipt $record): void {
                    app(StartNonPoReceiptProcessingAction::class)->execute(auth()->user(), $record);
                    $this->refreshRecord('Proses PO dimulai', 'Buat PO manual di ESB, lalu masukkan nomornya di sini.');
                }),
            Action::make('checkPurchaseOrder')->label('Cek PO')->icon('heroicon-o-magnifying-glass')->color('gray')
                ->visible(fn (NonPoReceipt $record): bool => auth()->user()->can('verifyPurchaseOrder', $record))
                ->schema([TextInput::make('po_number')->label('Nomor PO')->required()->maxLength(255)])
                ->fillForm(fn (NonPoReceipt $record): array => ['po_number' => $record->po_number])
                ->action(function (array $data, NonPoReceipt $record): void {
                    $this->poComparison = $this->displayableComparison(app(ComparePurchaseOrderAction::class)->execute($record, $data['po_number']));
                }),
            Action::make('verifyAndProcess')->label('Verifikasi PO & Proses GR')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn (NonPoReceipt $record): bool => auth()->user()->can('verifyPurchaseOrder', $record))
                ->modalDescription('Server mengambil PO terbaru dari ESB. Bila semua cocok, qty Accepted langsung dikirim sebagai Goods Receipt.')
                ->schema([
                    TextInput::make('po_number')->label('Nomor PO')->required()->maxLength(255),
                    Checkbox::make('supplier_mismatch_confirmed')->label('Saya sudah memeriksa bila supplier PO berbeda dengan catatan Inventory, dan supplier PO yang dipakai'),
                ])
                ->fillForm(fn (NonPoReceipt $record): array => ['po_number' => $record->po_number ?? data_get($this->poComparison, 'poNumber')])
                ->action(function (array $data, NonPoReceipt $record, Action $action): void {
                    $this->runGuarded($action, function () use ($data, $record): void {
                        $updated = app(VerifyPurchaseOrderAndProcessGoodsReceiptAction::class)->execute(auth()->user(), $record, $data['po_number'], (bool) ($data['supplier_mismatch_confirmed'] ?? false));
                        $this->poComparison = null;
                        $this->notifyGoodsReceiptResult($updated);
                    });
                }),
            Action::make('resolveGoodsReceipt')->label('Rekonsiliasi GR')->icon('heroicon-o-arrow-path')->color('warning')
                ->visible(fn (NonPoReceipt $record): bool => auth()->user()->can('resolveGoodsReceipt', $record))
                ->modalDescription('Periksa ESB dan System Monitoring lebih dulu. Tidak ada pengiriman ulang otomatis.')
                ->schema([
                    Select::make('outcome')->label('Hasil Pemeriksaan')->required()->native(false)->live()->options([
                        'link_existing' => 'GR sudah ada di ESB, tautkan nomornya',
                        'resend' => 'GR tidak ada di ESB, kirim ulang',
                    ]),
                    TextInput::make('goods_receipt_number')->label('Nomor GR ESB')->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('outcome') === 'link_existing')
                        ->required(fn (Get $get): bool => $get('outcome') === 'link_existing'),
                    Textarea::make('reason')->label('Catatan Pemeriksaan')->required()->rows(3),
                ])
                ->action(function (array $data, NonPoReceipt $record, Action $action): void {
                    $this->runGuarded($action, function () use ($data, $record): void {
                        $resolver = app(ResolveNonPoGoodsReceiptAction::class);
                        $this->notifyGoodsReceiptResult($data['outcome'] === 'link_existing'
                            ? $resolver->linkExisting(auth()->user(), $record, (string) $data['goods_receipt_number'], $data['reason'])
                            : $resolver->resend(auth()->user(), $record, $data['reason']));
                    });
                }),
            Action::make('requestRevision')->label('Minta Perbaikan')->icon('heroicon-o-pencil-square')->color('warning')
                ->visible(fn (NonPoReceipt $record): bool => auth()->user()->can('requestRevision', $record))
                ->schema([Textarea::make('reason')->label('Alasan Perbaikan')->required()->rows(3)])
                ->action(function (array $data, NonPoReceipt $record): void {
                    app(RequestNonPoReceiptRevisionAction::class)->execute(auth()->user(), $record, $data['reason']);
                    $this->refreshRecord('Perbaikan diminta', 'Inventory mendapat notifikasi.');
                }),
            Action::make('cancelProcess')->label('Batalkan Proses')->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn (NonPoReceipt $record): bool => auth()->user()->can('cancel', $record))
                ->modalDescription(fn (NonPoReceipt $record): ?string => $record->status->needsGoodsReceiptResolution() ? 'Pastikan Goods Receipt tidak ada di ESB sebelum membatalkan.' : null)
                ->schema([Textarea::make('reason')->label('Alasan Atau Bukti Retur')->required()->rows(3)])
                ->action(function (array $data, NonPoReceipt $record): void {
                    app(CancelNonPoReceiptAction::class)->execute(auth()->user(), $record, $data['reason']);
                    $this->refreshRecord('Proses dibatalkan');
                }),
        ];
    }

    /** Keep validation messages on the modal field instead of closing it with a generic error. */
    private function runGuarded(Action $action, \Closure $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Verifikasi tidak dapat dilanjutkan')->body(collect($exception->errors())->flatten()->implode(' '))->persistent()->send();
            $this->record->refresh();
            $action->halt();
        }
    }

    private function notifyGoodsReceiptResult(NonPoReceipt $receipt): void
    {
        $this->record->refresh();

        match ($receipt->status) {
            NonPoReceiptStatus::Completed => Notification::make()->success()->title('Goods Receipt berhasil')->body('Nomor GR: '.($this->record->goodsReceipt?->esb_goods_receipt_number ?? '-'))->send(),
            NonPoReceiptStatus::GrUnknown => Notification::make()->danger()->title('Hasil Goods Receipt belum pasti')->body('Koneksi ESB terputus. Rekonsiliasi dulu; tidak ada pengiriman ulang otomatis.')->persistent()->send(),
            NonPoReceiptStatus::GrFailed => Notification::make()->danger()->title('ESB menolak Goods Receipt')->body((string) $this->record->goodsReceipt?->sync_error)->persistent()->send(),
            default => Notification::make()->warning()->title('Penerimaan sudah diproses')->send(),
        };
    }

    private function refreshRecord(string $title, ?string $body = null): void
    {
        $this->record->refresh();
        Notification::make()->success()->title($title)->body($body)->send();
    }

    /**
     * The raw PO stays on the server; the page only needs what the matrix shows.
     *
     * @param  array<string, mixed>  $comparison
     * @return array<string, mixed>
     */
    private function displayableComparison(array $comparison): array
    {
        unset($comparison['order']);

        return $comparison;
    }
}
