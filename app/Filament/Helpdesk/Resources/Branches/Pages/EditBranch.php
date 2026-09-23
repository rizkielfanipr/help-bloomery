<?php

namespace App\Filament\Helpdesk\Resources\Branches\Pages;

use App\Filament\Helpdesk\Concerns\HasWorkspaceFormLayout;
use App\Filament\Helpdesk\Resources\Branches\BranchResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditBranch extends EditRecord
{
    use HasWorkspaceFormLayout;

    protected static string $resource = BranchResource::class;

    protected function afterSave(): void
    {
        $branch = $this->getRecord()->fresh(['stockCardEsbCode']);
        if ($branch->stock_card_esb_code_id !== null && $branch->activeStockCardEsbCode() === null) {
            $branch->update(['stock_card_esb_code_id' => null]);
            Notification::make()
                ->title('Sumber Stock Card dikosongkan')
                ->body('Mapping yang dipilih sudah tidak aktif atau tidak lagi dimiliki Branch ini.')
                ->warning()
                ->send();
        }
    }

    protected function workspaceHero(): array
    {
        return [
            'icon' => 'heroicon-o-building-office-2',
            'eyebrow' => 'Master · Branch',
            'title' => $this->getRecord()->name,
            'description' => BranchResource::isShiftEditorOnly()
                ? 'Ubah jam shift branch ini. Perubahan berlaku untuk perhitungan Basket Size berikutnya.'
                : 'Perbarui data branch, kode ESB, shift Basket Size, dan titik lokasi absen.',
            'badge' => $this->getRecord()->is_active
                ? ['label' => 'Aktif', 'tone' => 'success']
                : ['label' => 'Nonaktif', 'tone' => 'gray'],
            'backUrl' => BranchResource::getUrl('index'),
            'backLabel' => 'Daftar Branch',
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Simpan Perubahan');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Batal');
    }
}
