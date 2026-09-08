<?php

namespace App\Filament\Helpdesk\Resources\StoreSops;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\StoreSops\Pages\CreateStoreSop;
use App\Filament\Helpdesk\Resources\StoreSops\Pages\EditStoreSop;
use App\Filament\Helpdesk\Resources\StoreSops\Pages\ListStoreSops;
use App\Filament\Helpdesk\Resources\StoreSops\Pages\ViewStoreSop;
use App\Models\StoreSop;
use App\Services\StoreSopPublisher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class StoreSopResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'store sops';

    protected static ?string $model = StoreSop::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|UnitEnum|null $navigationGroup = 'Operational';

    protected static ?string $navigationLabel = 'SOP Store';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi SOP')->schema([
                TextInput::make('code')->label('Kode SOP')->required()->maxLength(50)->unique(ignoreRecord: true),
                TextInput::make('title')->label('Judul SOP')->required()->maxLength(255),
                TextInput::make('version')->label('Versi')->required()->default('1.0')->maxLength(30),
                TextInput::make('category')->label('Kategori')->maxLength(100),
                DatePicker::make('effective_date')->label('Berlaku Mulai')->required()->default(today()),
                Select::make('branches')->label('Target Branch')->relationship('branches', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('name'))->multiple()->searchable()->preload()->required(),
                Textarea::make('summary')->label('Ringkasan')->rows(4)->maxLength(3000)->columnSpanFull(),
                FileUpload::make('file_path')->label('Dokumen SOP')->disk('b2')->directory('operational/store-sops')->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])->maxSize(20480)->storeFileNamesIn('original_name')->required(fn (?StoreSop $record): bool => $record === null)->columnSpanFull(),
                Hidden::make('status')->default('draft'),
            ])->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Detail SOP')->schema([
                TextEntry::make('code')->label('Kode'),
                TextEntry::make('title')->label('Judul'),
                TextEntry::make('version')->label('Versi'),
                TextEntry::make('category')->label('Kategori')->placeholder('—'),
                TextEntry::make('effective_date')->label('Berlaku Mulai')->date('d M Y'),
                TextEntry::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'published' => 'success',
                    'archived' => 'danger',
                    default => 'gray',
                }),
                TextEntry::make('branches.name')->label('Target Branch')->badge()->separator(','),
                TextEntry::make('summary')->label('Ringkasan')->placeholder('—')->columnSpanFull(),
                TextEntry::make('file_path')->label('Dokumen')->formatStateUsing(fn (): string => 'Download Dokumen SOP')->url(fn (StoreSop $record): string => $record->downloadUrl())->openUrlInNewTab(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            TextColumn::make('title')->label('Judul')->searchable()->wrap(),
            TextColumn::make('version')->label('Versi')->badge(),
            TextColumn::make('branches.name')->label('Branch')->badge()->separator(','),
            TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                'published' => 'success',
                'archived' => 'danger',
                default => 'gray',
            }),
            TextColumn::make('assignments_count')->label('Penerima')->counts('assignments'),
            TextColumn::make('published_at')->label('Dipublikasikan')->dateTime('d M Y H:i')->placeholder('—'),
        ])->defaultSort('created_at', 'desc')->recordActions([
            ViewAction::make()->iconButton(),
            EditAction::make()->iconButton()->visible(fn (StoreSop $record): bool => $record->status === 'draft'),
            Action::make('publish')->label('Publish')->icon('heroicon-o-paper-airplane')->color('success')->requiresConfirmation()->modalDescription('SOP akan langsung diberikan kepada Supervisor Store yang menangani branch terpilih.')->visible(fn (StoreSop $record): bool => $record->status === 'draft' && auth()->user()?->can('publish store sops'))->action(function (StoreSop $record): void {
                $count = app(StoreSopPublisher::class)->publish($record, auth()->user());
                Notification::make()->title('SOP berhasil dipublikasikan')->body($count.' assignment Supervisor Store dibuat.')->success()->send();
            }),
            Action::make('recipients')->label('Rekap')->icon('heroicon-o-users')->color('info')->visible(fn (): bool => auth()->user()?->can('view store sop reports') ?? false)->modalHeading(fn (StoreSop $record): string => 'Rekap Penerima — '.$record->title)->modalWidth(Width::FourExtraLarge)->modalSubmitAction(false)->modalContent(fn (StoreSop $record) => view('filament.helpdesk.store-sops.recipients', ['record' => $record->load('assignments.user', 'assignments.branch')])),
            Action::make('archive')->label('Arsipkan')->icon('heroicon-o-archive-box')->color('warning')->requiresConfirmation()->modalDescription('SOP ini akan diarsipkan dan tidak lagi muncul sebagai tugas aktif.')->visible(fn (StoreSop $record): bool => $record->status === 'published' && auth()->user()?->can('edit store sops'))->action(function (StoreSop $record): void {
                $record->update(['status' => 'archived']);
                Notification::make()->title('SOP berhasil diarsipkan')->warning()->send();
            }),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStoreSops::route('/'),
            'create' => CreateStoreSop::route('/create'),
            'view' => ViewStoreSop::route('/{record}'),
            'edit' => EditStoreSop::route('/{record}/edit'),
        ];
    }
}
