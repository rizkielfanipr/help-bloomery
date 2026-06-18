<?php

namespace App\Filament\Helpdesk\Resources\BriefingRecords\Schemas;

use App\Enums\BriefingPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class BriefingRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                DatePicker::make('record_date')
                    ->required(),
                Select::make('period')
                    ->options(BriefingPeriod::class)
                    ->required(),
                DateTimePicker::make('submitted_at'),
            ]);
    }
}
