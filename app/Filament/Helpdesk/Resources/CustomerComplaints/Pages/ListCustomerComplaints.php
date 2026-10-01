<?php

namespace App\Filament\Helpdesk\Resources\CustomerComplaints\Pages;

use App\Filament\Helpdesk\Resources\CustomerComplaints\CustomerComplaintResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCustomerComplaints extends ListRecords
{
    protected static string $resource = CustomerComplaintResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Customer Complaints';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Tinjau dan tindak lanjuti komplain customer sesuai cakupan branch Anda.';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
