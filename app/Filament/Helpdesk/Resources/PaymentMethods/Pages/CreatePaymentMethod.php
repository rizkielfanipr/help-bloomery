<?php

namespace App\Filament\Helpdesk\Resources\PaymentMethods\Pages;

use App\Filament\Helpdesk\Resources\PaymentMethods\PaymentMethodResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentMethod extends CreateRecord
{
    protected static string $resource = PaymentMethodResource::class;
}
