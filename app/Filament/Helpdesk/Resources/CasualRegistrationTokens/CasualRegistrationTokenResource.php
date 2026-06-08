<?php

namespace App\Filament\Helpdesk\Resources\CasualRegistrationTokens;

use App\Filament\Helpdesk\Resources\CasualRegistrationTokens\Pages\CreateCasualRegistrationToken;
use App\Filament\Helpdesk\Resources\CasualRegistrationTokens\Pages\ListCasualRegistrationTokens;
use App\Models\CasualRegistrationToken;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CasualRegistrationTokenResource extends Resource
{
    protected static ?string $model = CasualRegistrationToken::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'Casual Staff';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Token Registrasi';

    protected static ?string $pluralModelLabel = 'Token Registrasi';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Buat Token Registrasi')
                    ->schema([
                        TextInput::make('label')
                            ->label('Keterangan (opsional)')
                            ->placeholder('Contoh: Batch rekrutmen Juni 2026')
                            ->nullable(),

                        DateTimePicker::make('expires_at')
                            ->label('Berlaku Sampai')
                            ->nullable()
                            ->helperText('Kosongkan jika tidak ada batas waktu'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('token')
                    ->label('Token')
                    ->copyable()
                    ->copyMessage('Token disalin!')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('label')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('expires_at')
                    ->label('Berlaku Sampai')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Tidak ada batas')
                    ->sortable(),

                IconColumn::make('used_at')
                    ->label('Sudah Dipakai')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->used_at !== null),

                TextColumn::make('usedBy.name')
                    ->label('Dipakai Oleh')
                    ->placeholder('-'),

                TextColumn::make('used_at')
                    ->label('Dipakai Pada')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('createdBy.name')
                    ->label('Dibuat Oleh')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('used')
                    ->label('Status')
                    ->options([
                        'unused' => 'Belum Dipakai',
                        'used' => 'Sudah Dipakai',
                    ])
                    ->query(function ($query, $state) {
                        if ($state['value'] === 'unused') {
                            $query->whereNull('used_at');
                        } elseif ($state['value'] === 'used') {
                            $query->whereNotNull('used_at');
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                DeleteAction::make()->iconButton()->tooltip('Hapus')->color('danger')
                    ->hidden(fn ($record) => $record->used_at !== null),
            ])
            ->toolbarActions([
                Action::make('create')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Tambah Token')
                    ->url(static::getUrl('create')),

                Action::make('import_excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Import Excel')
                    ->action(function (): void {
                        Notification::make()
                            ->title('Segera hadir')
                            ->body('Fitur Import Excel belum tersedia.')
                            ->warning()
                            ->send();
                    }),

                Action::make('export_excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('Export Excel')
                    ->action(function (): void {
                        Notification::make()
                            ->title('Segera hadir')
                            ->body('Fitur Export Excel belum tersedia.')
                            ->warning()
                            ->send();
                    }),

                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn ($records) => $records?->filter(fn ($r) => $r->used_at !== null)->isNotEmpty()),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCasualRegistrationTokens::route('/'),
            'create' => CreateCasualRegistrationToken::route('/create'),
        ];
    }
}
