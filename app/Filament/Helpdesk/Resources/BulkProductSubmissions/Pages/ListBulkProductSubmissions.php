<?php

namespace App\Filament\Helpdesk\Resources\BulkProductSubmissions\Pages;

use App\Filament\Helpdesk\Resources\BulkProductSubmissions\BulkProductSubmissionResource;
use App\Services\EsbCompanyProductService;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Throwable;

class ListBulkProductSubmissions extends ListRecords
{
    protected static string $resource = BulkProductSubmissionResource::class;

    public function mount(): void
    {
        parent::mount();

        try {
            resolve(EsbCompanyProductService::class)->taxonomy('BLSS');
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->title('Kategori ESB belum dapat dimuat')
                ->body($exception->getMessage())
                ->warning()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
