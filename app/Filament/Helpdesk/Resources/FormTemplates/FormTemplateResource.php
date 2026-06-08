<?php

namespace App\Filament\Helpdesk\Resources\FormTemplates;

use App\Enums\FormFieldType;
use App\Filament\Helpdesk\Resources\FormTemplates\Pages\CreateFormTemplate;
use App\Filament\Helpdesk\Resources\FormTemplates\Pages\EditFormTemplate;
use App\Filament\Helpdesk\Resources\FormTemplates\Pages\ListFormTemplates;
use App\Models\HelpdeskFormTemplate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class FormTemplateResource extends Resource
{
    protected static ?string $model = HelpdeskFormTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return 'Template Form';
    }

    public static function getNavigationGroup(): string
    {
        return 'Pengaturan';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'helpdesk_manager']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Template')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Template')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->nullable(),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->helperText('Template aktif akan muncul sebagai menu di sidebar')
                            ->default(true),
                    ]),

                Section::make('Alur Status')
                    ->description('Tentukan urutan status permintaan untuk template ini. Drag untuk mengubah urutan.')
                    ->schema([
                        Repeater::make('statuses')
                            ->label('')
                            ->schema([
                                TextInput::make('label')
                                    ->label('Label Status')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                        if (! $get('value') && $state) {
                                            $set('value', Str::slug($state, '_'));
                                        }
                                    }),

                                TextInput::make('value')
                                    ->label('Nilai (slug)')
                                    ->required()
                                    ->helperText('Huruf kecil & underscore'),

                                ColorPicker::make('color')
                                    ->label('Warna Badge')
                                    ->default('#6b7280')
                                    ->required(),

                                Toggle::make('is_final')
                                    ->label('Status Terakhir')
                                    ->helperText('Tandai jika ini adalah status akhir dari alur')
                                    ->default(false),

                                Repeater::make('branches')
                                    ->label('Percabangan')
                                    ->helperText('Isi jika status ini memiliki beberapa kemungkinan tindak lanjut, contoh: Setujui / Tolak.')
                                    ->schema([
                                        TextInput::make('label')
                                            ->label('Label Aksi')
                                            ->placeholder('Contoh: Setujui')
                                            ->required(),

                                        TextInput::make('next_status')
                                            ->label('Status Tujuan (value)')
                                            ->placeholder('Contoh: disetujui')
                                            ->helperText('Harus sama dengan nilai (slug) status tujuan')
                                            ->required(),

                                        ColorPicker::make('color')
                                            ->label('Warna')
                                            ->default('#6b7280'),

                                        Toggle::make('is_final')
                                            ->label('Status Terakhir')
                                            ->helperText('Aktifkan jika tindakan ini mengakhiri alur')
                                            ->default(false),
                                    ])
                                    ->columns(4)
                                    ->addActionLabel('Tambah Cabang')
                                    ->columnSpanFull()
                                    ->collapsible()
                                    ->collapsed()
                                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                    ->default([]),
                            ])
                            ->columns(4)
                            ->reorderable()
                            ->addActionLabel('Tambah Status')
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->default([
                                ['label' => 'Diajukan', 'value' => 'diajukan', 'color' => '#3b82f6', 'is_final' => false, 'branches' => []],
                                [
                                    'label' => 'Sedang Ditinjau',
                                    'value' => 'sedang_ditinjau',
                                    'color' => '#f59e0b',
                                    'is_final' => false,
                                    'branches' => [
                                        ['label' => 'Setujui', 'next_status' => 'disetujui', 'color' => '#22c55e', 'is_final' => false],
                                        ['label' => 'Tolak', 'next_status' => 'ditolak', 'color' => '#ef4444', 'is_final' => true],
                                    ],
                                ],
                                ['label' => 'Disetujui', 'value' => 'disetujui', 'color' => '#22c55e', 'is_final' => true, 'branches' => []],
                                ['label' => 'Ditolak', 'value' => 'ditolak', 'color' => '#ef4444', 'is_final' => true, 'branches' => []],
                            ]),
                    ]),

                Section::make('Field Form')
                    ->description('Tambahkan field-field yang akan ditampilkan saat user membuat permintaan.')
                    ->schema([
                        Repeater::make('fields')
                            ->label('')
                            ->relationship()
                            ->schema([
                                TextInput::make('label')
                                    ->label('Label Field')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->columnSpan(2),

                                Select::make('type')
                                    ->label('Tipe Data')
                                    ->options(FormFieldType::class)
                                    ->required()
                                    ->live()
                                    ->columnSpan(1),

                                Toggle::make('is_required')
                                    ->label('Wajib Diisi')
                                    ->columnSpan(1),

                                TextInput::make('hint')
                                    ->label('Petunjuk')
                                    ->placeholder('Contoh: Isi dengan jelas...')
                                    ->nullable()
                                    ->live(onBlur: true)
                                    ->columnSpan(2),

                                TextInput::make('placeholder')
                                    ->label('Placeholder')
                                    ->nullable()
                                    ->live(onBlur: true)
                                    ->columnSpan(2)
                                    ->hidden(function (Get $get): bool {
                                        $type = $get('type');
                                        $value = $type instanceof FormFieldType ? $type->value : $type;

                                        return in_array($value, [
                                            FormFieldType::Toggle->value,
                                            FormFieldType::File->value,
                                            FormFieldType::Date->value,
                                        ]);
                                    }),

                                Repeater::make('options')
                                    ->label('Pilihan')
                                    ->schema([
                                        TextInput::make('label')
                                            ->label('Label')
                                            ->required(),
                                        TextInput::make('value')
                                            ->label('Nilai')
                                            ->required(),
                                    ])
                                    ->columns(2)
                                    ->columnSpanFull()
                                    ->addActionLabel('Tambah Pilihan')
                                    ->visible(function (Get $get): bool {
                                        $type = $get('type');
                                        $value = $type instanceof FormFieldType ? $type->value : $type;

                                        return $value === FormFieldType::Select->value;
                                    }),

                            ])
                            ->columns(4)
                            ->orderColumn('sort_order')
                            ->reorderable()
                            ->collapsible()
                            ->addActionLabel('Tambah Field')
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
                    ]),
            ]);
    }

    private static function buildFieldPreview(string|FormFieldType|null $type, ?string $label, ?string $hint, ?string $placeholder): HtmlString
    {
        if (! $type) {
            return new HtmlString('');
        }

        $type = $type instanceof FormFieldType ? $type->value : $type;

        $safeLabel = e($label ?: 'Label Field');
        $safeHint = e($hint ?: '');
        $safePlaceholder = e($placeholder ?: '');

        $hintHtml = $safeHint
            ? "<p class=\"mt-0.5 text-xs text-gray-500 dark:text-gray-400\">{$safeHint}</p>"
            : '';

        $inputHtml = match ($type) {
            FormFieldType::Text->value => "
                <div class=\"flex rounded-lg shadow-sm ring-1 ring-gray-950/10 dark:ring-white/20 overflow-hidden\">
                    <input type=\"text\" disabled class=\"flex-1 px-3 py-1.5 text-sm text-gray-400 bg-white dark:bg-white/5 border-0 outline-none\" placeholder=\"{$safePlaceholder}\">
                </div>",

            FormFieldType::Number->value => "
                <div class=\"flex rounded-lg shadow-sm ring-1 ring-gray-950/10 dark:ring-white/20 overflow-hidden\">
                    <input type=\"number\" disabled class=\"flex-1 px-3 py-1.5 text-sm text-gray-400 bg-white dark:bg-white/5 border-0 outline-none\" placeholder=\"{$safePlaceholder}\">
                </div>",

            FormFieldType::Textarea->value => "
                <div class=\"rounded-lg shadow-sm ring-1 ring-gray-950/10 dark:ring-white/20 overflow-hidden\">
                    <div class=\"px-3 py-2 text-sm text-gray-400 bg-white dark:bg-white/5 min-h-[60px]\">{$safePlaceholder}</div>
                    <div class=\"border-t border-gray-950/10 dark:border-white/20 bg-gray-50 dark:bg-white/5 px-2 py-1 flex gap-1\">
                        <span class=\"text-xs text-gray-400 px-1.5 py-0.5 rounded bg-gray-100 dark:bg-white/10\">B</span>
                        <span class=\"text-xs text-gray-400 px-1.5 py-0.5 rounded bg-gray-100 dark:bg-white/10 italic\">I</span>
                        <span class=\"text-xs text-gray-400 px-1.5 py-0.5 rounded bg-gray-100 dark:bg-white/10\">• List</span>
                        <span class=\"text-xs text-gray-400 px-1.5 py-0.5 rounded bg-gray-100 dark:bg-white/10\">Link</span>
                    </div>
                </div>",

            FormFieldType::Select->value => '
                <div class="flex rounded-lg shadow-sm ring-1 ring-gray-950/10 dark:ring-white/20 overflow-hidden">
                    <div class="flex-1 flex items-center justify-between px-3 py-1.5 bg-white dark:bg-white/5">
                        <span class="text-sm text-gray-400">Pilih opsi...</span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>',

            FormFieldType::Date->value => '
                <div class="flex rounded-lg shadow-sm ring-1 ring-gray-950/10 dark:ring-white/20 overflow-hidden">
                    <div class="flex-1 flex items-center justify-between px-3 py-1.5 bg-white dark:bg-white/5">
                        <span class="text-sm text-gray-400">dd/mm/yyyy</span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                </div>',

            FormFieldType::File->value => '
                <div class="rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 p-4 text-center bg-white dark:bg-white/5">
                    <svg class="mx-auto w-6 h-6 text-gray-400 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <p class="text-xs text-gray-400">Klik atau drag file ke sini</p>
                </div>',

            FormFieldType::Toggle->value => '
                <div class="flex items-center gap-3">
                    <button type="button" class="relative inline-flex h-5 w-9 rounded-full bg-gray-200 dark:bg-white/20 cursor-default">
                        <span class="inline-block h-4 w-4 translate-x-0.5 translate-y-0.5 rounded-full bg-white shadow"></span>
                    </button>
                    <span class="text-sm text-gray-400">Tidak</span>
                </div>',

            default => '<div class="text-sm text-gray-400">—</div>',
        };

        $typeLabel = FormFieldType::from($type)->getLabel();

        return new HtmlString("
            <div class=\"rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-3 bg-gray-50/50 dark:bg-white/[0.02]\">
                <p class=\"text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2\">Preview — {$typeLabel}</p>
                <p class=\"text-sm font-medium text-gray-700 dark:text-gray-200 mb-1\">{$safeLabel}</p>
                {$hintHtml}
                <div class=\"mt-1.5\">{$inputHtml}</div>
            </div>
        ");
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Template')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('fields_count')
                    ->label('Jumlah Field')
                    ->counts('fields')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('requests_count')
                    ->label('Permintaan')
                    ->counts('requests')
                    ->badge()
                    ->color('primary'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->tooltip('Edit')->color('warning'),
                DeleteAction::make()->iconButton()->tooltip('Hapus')->color('danger'),
            ])
            ->toolbarActions([
                Action::make('create')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Tambah Template')
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
            'index' => ListFormTemplates::route('/'),
            'create' => CreateFormTemplate::route('/create'),
            'edit' => EditFormTemplate::route('/{record}/edit'),
        ];
    }
}
