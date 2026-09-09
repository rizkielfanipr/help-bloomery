<?php

namespace App\Filament\Helpdesk\Resources\Assets;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\Assets\Pages\CreateAsset;
use App\Filament\Helpdesk\Resources\Assets\Pages\EditAsset;
use App\Filament\Helpdesk\Resources\Assets\Pages\ListAssets;
use App\Models\Asset;
use App\Models\Branch;
use App\Observers\AssetObserver;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class AssetResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'assets';

    protected static ?string $model = Asset::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static string|UnitEnum|null $navigationGroup = 'Technician';

    protected static ?string $navigationLabel = 'Asset QR';

    protected static ?string $modelLabel = 'Asset';

    protected static ?string $pluralModelLabel = 'Asset';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Asset')->schema([
                TextInput::make('asset_number')->label('Nomor Asset')->disabled()->dehydrated(false)->placeholder('Dibuat otomatis setelah disimpan'),
                TextInput::make('name')->label('Nama Asset')->required()->maxLength(255),
                TextInput::make('category')->label('Kategori')->maxLength(100),
                TextInput::make('brand')->label('Brand Asset')->maxLength(100),
                TextInput::make('model')->label('Model')->maxLength(100),
                TextInput::make('serial_number')->label('Nomor Seri')->maxLength(100),
            ])->columns(2),
            Section::make('Penempatan')->schema([
                Select::make('branch_id')->label('Branch')->options(fn (): array => Branch::query()->where('is_active', true)->when(! auth()->user()?->canAccessAllBranches(), fn ($query) => $query->whereIn('id', auth()->user()->accessibleBranchIds()))->orderBy('name')->pluck('name', 'id')->all())->searchable()->preload()->required(),
                Toggle::make('is_active')->label('Aktif')->default(true),
                Textarea::make('notes')->label('Catatan')->rows(3)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('asset_number')->label('Nomor Asset')->badge()->searchable()->sortable(),
            TextColumn::make('name')->label('Nama Asset')->searchable()->sortable(),
            TextColumn::make('category')->label('Kategori')->placeholder('—')->searchable(),
            TextColumn::make('branch.name')->label('Branch')->sortable(),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->defaultSort('created_at', 'desc')->recordActions([
            Action::make('preview_qr')->label('QR')->icon('heroicon-o-qr-code')->iconButton()->tooltip('Lihat QR')->modalHeading(fn (Asset $record): string => $record->asset_number)->modalWidth(Width::Small)->modalSubmitAction(false)->modalContent(fn (Asset $record) => view('filament.helpdesk.assets.qr-preview', ['asset' => $record])),
            Action::make('download_qr')->label('Download QR')->icon('heroicon-o-arrow-down-tray')->iconButton()->tooltip('Download QR')->url(fn (Asset $record): string => route('helpdesk.assets.qr', $record))->openUrlInNewTab()->visible(fn (): bool => auth()->user()?->can('generate asset qr codes') ?? false),
            Action::make('print_label')->label('Cetak Label')->icon('heroicon-o-printer')->iconButton()->tooltip('Cetak Label')->url(fn (Asset $record): string => route('helpdesk.assets.label-pdf', $record))->openUrlInNewTab()->visible(fn (): bool => auth()->user()?->can('print asset labels') ?? false),
            Action::make('regenerate_qr')->label('Generate Ulang QR')->icon('heroicon-o-arrow-path')->iconButton()->tooltip('Generate Ulang QR')->requiresConfirmation()->visible(fn (): bool => auth()->user()?->can('generate asset qr codes') ?? false)->action(function (Asset $record): void {
                app(AssetObserver::class)->regenerate($record);
                Notification::make()->title('QR Code berhasil dibuat ulang')->success()->send();
            }),
            EditAction::make()->iconButton(),
            DeleteAction::make()->iconButton()->requiresConfirmation(),
        ])->toolbarActions([
            BulkActionGroup::make([
                BulkAction::make('print_labels')->label('Cetak Label')->icon('heroicon-o-qr-code')->visible(fn (): bool => auth()->user()?->can('print asset labels') ?? false)->action(fn (Collection $records) => redirect()->route('helpdesk.assets.labels-pdf', ['ids' => $records->pluck('id')->all()])),
                DeleteBulkAction::make(),
            ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('branch')->when(! auth()->user()?->canAccessAllBranches(), fn (Builder $query) => $query->whereIn('branch_id', auth()->user()->accessibleBranchIds()));
    }

    public static function canView($record): bool
    {
        return parent::canView($record) && (auth()->user()?->canAccessBranch($record->branch_id) ?? false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssets::route('/'),
            'create' => CreateAsset::route('/create'),
            'edit' => EditAsset::route('/{record}/edit'),
        ];
    }
}
