<?php

namespace App\Filament\Helpdesk\Resources\Branches\Pages;

use App\Filament\Helpdesk\Concerns\HasWorkspaceFormLayout;
use App\Filament\Helpdesk\Resources\Branches\BranchResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateBranch extends CreateRecord
{
    use HasWorkspaceFormLayout;

    protected static string $resource = BranchResource::class;

    protected function afterCreate(): void
    {
        $activeMappings = $this->getRecord()->esbCodes()->where('is_active', true)->get();
        if ($activeMappings->count() === 1) {
            $this->getRecord()->update(['stock_card_esb_code_id' => $activeMappings->first()->id]);
        }
    }

    protected function workspaceHero(): array
    {
        return [
            'icon' => 'heroicon-o-building-office-2',
            'eyebrow' => 'Master · Branch',
            'title' => 'Tambah Branch',
            'description' => 'Lengkapi data dasar, kode ESB, shift Basket Size, dan titik lokasi absen. Hapus baris kode ESB atau shift yang belum diperlukan; lokasi absen bersifat opsional.',
            'badge' => null,
            'backUrl' => BranchResource::getUrl('index'),
            'backLabel' => 'Daftar Branch',
        ];
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Simpan Branch');
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()->label('Simpan & Tambah Lagi');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Batal');
    }
}
