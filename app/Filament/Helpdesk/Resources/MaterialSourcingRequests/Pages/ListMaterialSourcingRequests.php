<?php

namespace App\Filament\Helpdesk\Resources\MaterialSourcingRequests\Pages;

use App\Filament\Helpdesk\Resources\MaterialSourcingRequests\MaterialSourcingRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListMaterialSourcingRequests extends ListRecords
{
    protected static string $resource = MaterialSourcingRequestResource::class;

    protected ?string $heading = 'Permintaan Sourcing dari Employee App';

    protected ?string $subheading = 'Request user untuk satu produk Master Product ESB. Lengkapi supplier lalu Buat Sourcing agar masuk review R&D.';

    public function getTitle(): string
    {
        return 'Permintaan Sourcing';
    }
}
