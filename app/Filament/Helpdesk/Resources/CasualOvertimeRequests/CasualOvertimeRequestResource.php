<?php

namespace App\Filament\Helpdesk\Resources\CasualOvertimeRequests;

use App\Filament\Exports\CasualOvertimeRequestExporter;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\CasualOvertimeRequests\Pages\ListCasualOvertimeRequests;
use App\Filament\Helpdesk\Resources\CasualOvertimeRequests\Pages\ViewCasualOvertimeRequest;
use App\Models\CasualOvertimeRequest;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CasualOvertimeRequestResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'overtime requests';

    protected static string $permissionGroup = 'Human Resources';

    protected static ?string $model = CasualOvertimeRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Casual Staff';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Lembur';

    protected static ?string $pluralModelLabel = 'Data Lembur Casual';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    Section::make('Info Staff')
                        ->icon('heroicon-o-user-circle')
                        ->schema([
                            Grid::make(3)->schema([
                                TextEntry::make('user.name')->label('Nama Staff'),
                                TextEntry::make('posisi')
                                    ->label('Posisi')
                                    ->state(fn (CasualOvertimeRequest $record): ?string => $record->effectivePosition()?->name)
                                    ->placeholder('-'),
                                TextEntry::make('clockRecord.date')
                                    ->label('Tanggal Kerja')
                                    ->date('d M Y'),
                            ]),
                        ]),

                    Section::make('Detail Lembur')
                        ->icon('heroicon-o-clock')
                        ->schema([
                            Grid::make(3)->schema([
                                TextEntry::make('approved_hours')
                                    ->label('Durasi Lembur')
                                    ->state(function (CasualOvertimeRequest $record): ?string {
                                        $hours = $record->approved_hours;
                                        if ($hours === null) {
                                            return null;
                                        }
                                        $totalMins = (int) round($hours * 60);
                                        $h = intdiv($totalMins, 60);
                                        $m = $totalMins % 60;

                                        return $h > 0 ? "{$h}j {$m}m" : "{$m}m";
                                    })
                                    ->placeholder('-'),

                                TextEntry::make('overtime_fee')
                                    ->label('Fee Lembur')
                                    ->money('IDR')
                                    ->placeholder('-'),

                                TextEntry::make('created_at')
                                    ->label('Dicatat Pada')
                                    ->dateTime('d M Y H:i'),
                            ]),

                            TextEntry::make('reason')
                                ->label('Alasan')
                                ->columnSpanFull()
                                ->placeholder('-'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Staff')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('posisi')
                    ->label('Posisi')
                    ->state(fn (CasualOvertimeRequest $record): ?string => $record->effectivePosition()?->name)
                    ->placeholder('-'),

                TextColumn::make('clockRecord.date')
                    ->label('Tanggal Kerja')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('approved_hours')
                    ->label('Durasi Lembur')
                    ->state(function (CasualOvertimeRequest $record): ?string {
                        $hours = $record->approved_hours;
                        if ($hours === null) {
                            return null;
                        }
                        $totalMins = (int) round($hours * 60);
                        $h = intdiv($totalMins, 60);
                        $m = $totalMins % 60;

                        return $h > 0 ? "{$h}j {$m}m" : "{$m}m";
                    })
                    ->placeholder('-'),

                TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(40)
                    ->placeholder('-'),

                TextColumn::make('overtime_fee')
                    ->label('Fee Lembur')
                    ->money('IDR')
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label('Dicatat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('Lihat')->color('info'),
                DeleteAction::make()->iconButton()->tooltip('Hapus')->color('danger'),
            ])
            ->toolbarActions([
                ExportAction::make()->icon('heroicon-o-arrow-down-tray')->color('success')->iconButton()->tooltip('Export Excel')->exporter(CasualOvertimeRequestExporter::class),

                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCasualOvertimeRequests::route('/'),
            'view' => ViewCasualOvertimeRequest::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user.casualPosition', 'clockRecord', 'reviewedBy']);
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
