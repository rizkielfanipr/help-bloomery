<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\CasualPanelProvider;
use App\Providers\Filament\DriverPanelProvider;
use App\Providers\Filament\HelpdeskPanelProvider;
use App\Providers\Filament\TechnicianPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    CasualPanelProvider::class,
    DriverPanelProvider::class,
    HelpdeskPanelProvider::class,
    TechnicianPanelProvider::class,
];
