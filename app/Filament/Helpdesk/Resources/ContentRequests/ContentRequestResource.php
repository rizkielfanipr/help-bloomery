<?php

namespace App\Filament\Helpdesk\Resources\ContentRequests;

use App\Enums\ContentRequestStatus;
use App\Filament\Exports\ContentRequestExporter;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\ContentRequests\Pages\EditContentRequest;
use App\Filament\Helpdesk\Resources\ContentRequests\Pages\ListContentRequests;
use App\Filament\Helpdesk\Resources\ContentRequests\Pages\ViewContentRequest;
use App\Models\ContentRequest;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ContentRequestResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'content requests';

    protected static ?string $model = ContentRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-video-camera';

    protected static string|UnitEnum|null $navigationGroup = 'Brand Marketing';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Permintaan Konten';

    protected static ?string $pluralModelLabel = 'Permintaan Konten';

    public static function getNavigationBadge(): ?string
    {
        return (string) ContentRequest::whereIn('status', [ContentRequestStatus::Submitted, ContentRequestStatus::InProgress, ContentRequestStatus::Approval])->count() ?: null;
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
                    TextEntry::make('code')->label('Kode')->badge()->color('info'),
                    TextEntry::make('requester.name')->label('Pemohon'),
                    TextEntry::make('branch.name')->label('Cabang')->placeholder('-'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (ContentRequestStatus $state) => $state->getLabel())
                        ->color(fn (ContentRequestStatus $state) => $state->getColor()),
                    TextEntry::make('jenis_konten')
                        ->label('Jenis Konten')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => $state === 'video' ? 'Video' : 'Foto'),
                    TextEntry::make('platform_tujuan')->label('Platform Tujuan')->placeholder('-'),
                    TextEntry::make('created_at')->label('Tanggal Pengajuan')->dateTime('d M Y H:i'),
                ]),
            ]),

            Section::make('Detail Konten')->schema([
                TextEntry::make('judul_konten')->label('Judul Konten'),
                TextEntry::make('tujuan_konten')->label('Tujuan Konten')->columnSpanFull(),
                TextEntry::make('link_contoh_konten')->label('Link Contoh Konten')->url(fn (?string $state): ?string => $state)->openUrlInNewTab()->placeholder('-'),
            ]),

            Section::make('Lampiran')
                ->schema([
                    TextEntry::make('attachments')
                        ->label('')
                        ->state(fn (ContentRequest $record): string => implode(', ', array_map(
                            fn ($path) => basename($path),
                            $record->attachments ?? []
                        )))
                        ->placeholder('Tidak ada lampiran'),
                ])
                ->hidden(fn (ContentRequest $record): bool => empty($record->attachments)),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('status')
                    ->label('Status')
                    ->options(collect(ContentRequestStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()]))
                    ->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->copyable()
                    ->weight('semibold'),

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

                TextColumn::make('judul_konten')
                    ->label('Judul')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('jenis_konten')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'video' ? 'Video' : 'Foto')
                    ->color(fn (string $state): string => $state === 'video' ? 'violet' : 'info'),

                TextColumn::make('platform_tujuan')
                    ->label('Platform')
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ContentRequestStatus $state) => $state->getLabel())
                    ->color(fn (ContentRequestStatus $state) => $state->getColor()),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(ContentRequestStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])),

                SelectFilter::make('jenis_konten')
                    ->label('Jenis Konten')
                    ->options(['photo' => 'Foto', 'video' => 'Video']),
            ])
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('Lihat'),
                EditAction::make()->iconButton()->tooltip('Edit'),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                ExportAction::make()->icon('heroicon-o-arrow-down-tray')->color('success')->iconButton()->tooltip('Export Excel')->exporter(ContentRequestExporter::class),

                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentRequests::route('/'),
            'view' => ViewContentRequest::route('/{record}'),
            'edit' => EditContentRequest::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['requester', 'branch']);
    }
}
