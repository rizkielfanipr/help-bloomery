<?php

namespace App\Filament\Helpdesk\Resources\StoreSops;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\StoreSops\Pages\CreateStoreSop;
use App\Filament\Helpdesk\Resources\StoreSops\Pages\EditStoreSop;
use App\Filament\Helpdesk\Resources\StoreSops\Pages\ListStoreSops;
use App\Filament\Helpdesk\Resources\StoreSops\Pages\ViewStoreSop;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\StoreSop;
use App\Models\StoreSopCategory;
use App\Services\StoreSopPublisher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                TextInput::make('code')->label('Nomor SOP')->required()->maxLength(50)->unique(ignoreRecord: true),
                TextInput::make('title')->label('Judul SOP')->required()->maxLength(255),
                Select::make('store_sop_category_id')->label('Kategori SOP')->options(StoreSopCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id'))->searchable()->preload()->required(),
                Select::make('brand_id')->label('Brand')->options(Brand::query()->whereHas('branches', fn ($query) => $query->where('is_active', true))->orderBy('name')->pluck('name', 'id'))->searchable()->preload()->required()->live()->afterStateUpdated(fn (Set $set): mixed => $set('branches', [])),
                Select::make('branches')->label('Target Branch')->relationship('branches', 'name')->options(fn (Get $get): array => Branch::query()->where('is_active', true)->where('brand_id', $get('brand_id'))->orderBy('name')->pluck('name', 'id')->all())->multiple()->searchable()->preload()->required()->disabled(fn (Get $get): bool => blank($get('brand_id')))->helperText('Branch otomatis mengikuti Brand yang dipilih.'),
                DatePicker::make('effective_date')->label('Berlaku Mulai')->required()->default(today()),
                DatePicker::make('expires_at')->label('Berlaku Sampai')->required()->rule('after_or_equal:effective_date'),
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
                TextEntry::make('code')->label('Nomor SOP'),
                TextEntry::make('title')->label('Judul'),
                TextEntry::make('category.name')->label('Kategori')->placeholder('—'),
                TextEntry::make('brand.name')->label('Brand')->placeholder('—'),
                TextEntry::make('effective_date')->label('Berlaku Mulai')->date('d M Y'),
                TextEntry::make('expires_at')->label('Berlaku Sampai')->date('d M Y')->placeholder('Tanpa batas'),
                TextEntry::make('display_status')->label('Status')->badge()->formatStateUsing(fn (StoreSop $record): string => $record->display_status_label)->color(fn (string $state): string => match ($state) {
                    'ongoing' => 'success',
                    'expired' => 'danger',
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
            TextColumn::make('code')->label('Nomor SOP')->searchable()->sortable(),
            TextColumn::make('title')->label('Judul')->searchable()->wrap(),
            TextColumn::make('category.name')->label('Kategori')->badge()->sortable(),
            TextColumn::make('brand.name')->label('Brand')->badge()->sortable(),
            TextColumn::make('branches.name')->label('Branch')->badge()->separator(','),
            TextColumn::make('display_status')->label('Status')->badge()->formatStateUsing(fn (StoreSop $record): string => $record->display_status_label)->color(fn (string $state): string => match ($state) {
                'ongoing' => 'success',
                'expired' => 'danger',
                default => 'gray',
            }),
            TextColumn::make('expires_at')->label('Berlaku Sampai')->date('d M Y')->placeholder('Tanpa batas')->sortable(),
            TextColumn::make('branches_count')->label('Branch Tujuan')->counts('branches'),
            TextColumn::make('published_at')->label('Dipublikasikan')->dateTime('d M Y H:i')->placeholder('—'),
        ])->defaultSort('created_at', 'desc')->recordActions([
            ViewAction::make()->iconButton(),
            EditAction::make()->iconButton()->visible(fn (StoreSop $record): bool => $record->status === 'draft'),
            Action::make('publish')->label('Publish')->icon('heroicon-o-paper-airplane')->color('success')->requiresConfirmation()->modalDescription('SOP akan tersedia untuk semua pengguna yang memiliki akses SOP Store pada branch terpilih.')->visible(fn (StoreSop $record): bool => $record->status === 'draft' && auth()->user()?->can('publish store sops'))->action(function (StoreSop $record): void {
                $count = app(StoreSopPublisher::class)->publish($record, auth()->user());
                Notification::make()->title('SOP berhasil dipublikasikan')->body($count.' branch menerima SOP.')->success()->send();
            }),
            Action::make('recipients')->label('Rekap')->icon('heroicon-o-users')->color('info')->visible(fn (): bool => auth()->user()?->can('view store sop reports') ?? false)->modalHeading(fn (StoreSop $record): string => 'Rekap Penerima — '.$record->title)->modalWidth(Width::FourExtraLarge)->modalSubmitAction(false)->modalContent(fn (StoreSop $record) => view('filament.helpdesk.store-sops.recipients', ['record' => $record->load('branches', 'assignments.user', 'assignments.branch')])),
            DeleteAction::make()->iconButton()->tooltip('Hapus SOP')->requiresConfirmation(),
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
