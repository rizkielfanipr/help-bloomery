<?php

namespace App\Services;

use App\Enums\NonPoReceiptStatus;
use App\Filament\Helpdesk\Resources\NonPoReceipts\NonPoReceiptResource;
use App\Models\NonPoReceipt;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Database notifications for the actionable PO Susulan events
 * (docs/products/inventory/non-po-receiving/prd.md §19). Callers send these after commit, so a
 * rolled back transition never announces anything.
 */
class NonPoReceiptNotifier
{
    public function submittedToPurchasing(NonPoReceipt $receipt): void
    {
        $recipients = User::query()->where('is_active', true)->permission('process non po purchase order requests')->get();
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()->warning()->title('Permintaan PO Susulan baru')
            ->body("{$receipt->receipt_number} · {$receipt->supplier_text} menunggu PO.")
            ->actions([
                Action::make('open')->label('Buka')->url(NonPoReceiptResource::getUrl('view', ['record' => $receipt], panel: 'helpdesk')),
            ])
            ->sendToDatabase($recipients);
    }

    public function revisionRequested(NonPoReceipt $receipt, string $reason): void
    {
        $this->notifyOwners($receipt, fn (): Notification => Notification::make()->warning()->title('PO Susulan perlu diperbaiki')
            ->body("{$receipt->receipt_number}: {$reason}"));
    }

    public function cancelled(NonPoReceipt $receipt, string $reason): void
    {
        $this->notifyOwners($receipt, fn (): Notification => Notification::make()->danger()->title('PO Susulan dibatalkan')
            ->body("{$receipt->receipt_number}: {$reason}"));
    }

    public function goodsReceiptResult(NonPoReceipt $receipt): void
    {
        $this->notifyOwners($receipt, fn (): Notification => match ($receipt->status) {
            NonPoReceiptStatus::Completed => Notification::make()->success()->title('Goods Receipt PO Susulan berhasil')
                ->body("{$receipt->receipt_number} · PO {$receipt->po_number}"),
            NonPoReceiptStatus::GrUnknown => Notification::make()->danger()->title('Goods Receipt PO Susulan perlu rekonsiliasi')
                ->body("{$receipt->receipt_number} · PO {$receipt->po_number}: koneksi ESB terputus, hasil belum pasti."),
            default => Notification::make()->danger()->title('Goods Receipt PO Susulan gagal')
                ->body("{$receipt->receipt_number} · PO {$receipt->po_number}"),
        });
    }

    /** @param \Closure(): Notification $notification */
    private function notifyOwners(NonPoReceipt $receipt, \Closure $notification): void
    {
        $recipients = $this->owners($receipt);
        if ($recipients->isNotEmpty()) {
            $notification()->sendToDatabase($recipients);
        }
    }

    /** @return Collection<int, User> */
    private function owners(NonPoReceipt $receipt): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('id', array_filter([$receipt->created_by, $receipt->submitted_by, $receipt->processed_by]))
            ->get();
    }
}
