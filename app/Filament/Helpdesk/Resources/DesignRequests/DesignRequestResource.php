<?php

namespace App\Filament\Helpdesk\Resources\DesignRequests;

use App\Enums\RequestStatus;
use App\Filament\Helpdesk\Resources\DesignRequests\Pages\EditDesignRequest;
use App\Filament\Helpdesk\Resources\DesignRequests\Pages\ListDesignRequests;
use App\Filament\Helpdesk\Resources\DesignRequests\Pages\ViewDesignRequest;
use App\Models\DesignCategory;
use App\Models\DesignRequest;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DesignRequestResource extends Resource
{
    protected static ?string $model = DesignRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static string|UnitEnum|null $navigationGroup = 'Brand Marketing';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Permintaan Design';

    protected static ?string $pluralModelLabel = 'Permintaan Design';

    public static function getNavigationBadge(): ?string
    {
        return (string) DesignRequest::whereIn('status', [RequestStatus::Submitted, RequestStatus::InReview])->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Permintaan')->schema([
                Grid::make(3)->schema([
                    TextEntry::make('requester.name')->label('Pemohon'),
                    TextEntry::make('branch.name')->label('Cabang')->placeholder('-'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (RequestStatus $state) => $state->getLabel())
                        ->color(fn (RequestStatus $state) => $state->getColor()),
                    TextEntry::make('category.name')->label('Kategori')->placeholder('-'),
                    TextEntry::make('assignee.name')->label('Dikerjakan oleh')->placeholder('Belum ditugaskan'),
                    TextEntry::make('created_at')->label('Tanggal Pengajuan')->dateTime('d M Y H:i'),
                ]),
            ]),

            Section::make('Detail Brief')->schema([
                TextEntry::make('judul_permintaan')->label('Judul Permintaan'),
                TextEntry::make('ringkasan_brief')->label('Brief Design')->columnSpanFull(),
            ]),

            Section::make('Lampiran')
                ->schema([
                    TextEntry::make('attachments')
                        ->label('')
                        ->state(fn (DesignRequest $record): string => implode(', ', array_map(
                            fn ($path) => basename($path),
                            $record->attachments ?? []
                        )))
                        ->placeholder('Tidak ada lampiran'),
                ])
                ->hidden(fn (DesignRequest $record): bool => empty($record->attachments)),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('status')
                    ->label('Status')
                    ->options(collect(RequestStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()]))
                    ->required(),

                Select::make('assignee_id')
                    ->label('Ditugaskan ke')
                    ->options(User::all()->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('requester.name')
                    ->label('Pemohon')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('judul_permintaan')
                    ->label('Judul')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('info')
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (RequestStatus $state) => $state->getLabel())
                    ->color(fn (RequestStatus $state) => $state->getColor()),

                TextColumn::make('assignee.name')
                    ->label('PIC')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(RequestStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])),

                SelectFilter::make('design_category_id')
                    ->label('Kategori')
                    ->options(DesignCategory::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')),
            ])
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('Lihat'),
                EditAction::make()->iconButton()->tooltip('Edit'),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDesignRequests::route('/'),
            'view' => ViewDesignRequest::route('/{record}'),
            'edit' => EditDesignRequest::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['requester', 'assignee', 'branch', 'category']);
    }
}
