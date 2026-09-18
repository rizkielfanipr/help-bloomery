<?php

namespace App\Filament\Helpdesk\Modules\Erp\Resources\ErpModules\Pages;

use App\Filament\Helpdesk\Modules\Erp\Resources\ErpModules\ErpModuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditErpModule extends EditRecord
{
    protected static string $resource = ErpModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
