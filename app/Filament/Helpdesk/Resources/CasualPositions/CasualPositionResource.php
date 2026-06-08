<?php

namespace App\Filament\Helpdesk\Resources\CasualPositions;

use App\Filament\Helpdesk\Resources\CasualPositions\Pages\CreateCasualPosition;
use App\Filament\Helpdesk\Resources\CasualPositions\Pages\EditCasualPosition;
use App\Filament\Helpdesk\Resources\CasualPositions\Pages\ListCasualPositions;
use App\Filament\Helpdesk\Resources\CasualPositions\Pages\ViewCasualPosition;
use App\Models\CasualPosition;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class CasualPositionResource extends Resource
{
    protected static ?string $model = CasualPosition::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|\UnitEnum|null $navigationGroup = 'Human Resources';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Posisi';

    protected static ?string $pluralModelLabel = 'Posisi Casual';

    private static function colLabel(string $icon, string $label): HtmlString
    {
        $svg = \Filament\Support\generate_icon_html($icon, size: IconSize::Small)?->toHtml() ?? '';

        return new HtmlString(
            '<span class="inline-flex items-center gap-1.5">'.$svg.e($label).'</span>'
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Posisi')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Posisi')
                            ->required()
                            ->placeholder('Contoh: Kasir, Pramuniaga, Loader'),

                        TextInput::make('fee_per_day')
                            ->label('Fee per Hari (Rp)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->prefix('Rp'),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->nullable()
                            ->rows(2)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(20)
            ->paginationPageOptions([20, 50, 100])
            ->searchPlaceholder('Cari nama...')
            ->columns([
                TextColumn::make('name')
                    ->label(static::colLabel('heroicon-m-briefcase', 'Nama Posisi'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('fee_per_day')
                    ->label(static::colLabel('heroicon-m-banknotes', 'Fee/Hari'))
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label(static::colLabel('heroicon-m-users', 'Staff Aktif'))
                    ->counts('users')
                    ->sortable(),

                TextColumn::make('is_active')
                    ->label(static::colLabel('heroicon-m-shield-check', 'Status'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Tidak Aktif')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),

                TextColumn::make('created_at')
                    ->label(static::colLabel('heroicon-m-calendar-days', 'Dibuat'))
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Aktif',
                        '0' => 'Tidak Aktif',
                    ])
                    ->placeholder('Semua Status')
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === null || $data['value'] === '') {
                            return $query;
                        }

                        return $query->where('is_active', (bool) $data['value']);
                    }),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filter')
                    ->icon('heroicon-m-funnel')
                    ->color('gray'),
            )
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('Lihat')->color('info'),
                EditAction::make()->iconButton()->tooltip('Edit')->color('warning'),
                DeleteAction::make()->iconButton()->tooltip('Hapus')->color('danger'),
            ])
            ->toolbarActions([
                Action::make('penerimaan_status')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Lanjutkan Permintaan Aktif')
                    ->action(function (): void {
                        Notification::make()
                            ->title('Segera hadir')
                            ->body('Fitur ini belum tersedia.')
                            ->warning()
                            ->send();
                    }),

                Action::make('create')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Tambah Posisi')
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
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCasualPositions::route('/'),
            'create' => CreateCasualPosition::route('/create'),
            'view' => ViewCasualPosition::route('/{record}'),
            'edit' => EditCasualPosition::route('/{record}/edit'),
        ];
    }
}
