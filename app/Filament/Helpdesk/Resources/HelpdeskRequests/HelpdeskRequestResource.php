<?php

namespace App\Filament\Helpdesk\Resources\HelpdeskRequests;

use App\Enums\FormFieldType;
use App\Filament\Helpdesk\Resources\HelpdeskRequests\Pages\CreateHelpdeskRequest;
use App\Filament\Helpdesk\Resources\HelpdeskRequests\Pages\EditHelpdeskRequest;
use App\Filament\Helpdesk\Resources\HelpdeskRequests\Pages\ListHelpdeskRequests;
use App\Filament\Helpdesk\Resources\HelpdeskRequests\Pages\ViewHelpdeskRequest;
use App\Models\Department;
use App\Models\HelpdeskFormField;
use App\Models\HelpdeskFormTemplate;
use App\Models\HelpdeskRequest;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class HelpdeskRequestResource extends Resource
{
    protected static ?string $model = HelpdeskRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Permintaan';
    }

    public static function getNavigationGroup(): string
    {
        return 'Helpdesk';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'helpdesk_manager', 'helpdesk_staff']);
    }

    public static function getNavigationItems(): array
    {
        $user = auth()->user();

        $templates = HelpdeskFormTemplate::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (HelpdeskFormTemplate $t): bool => $t->isAccessibleByUser($user));

        if ($templates->isEmpty()) {
            return parent::getNavigationItems();
        }

        $indexUrl = static::getUrl('index');

        return $templates->map(function (HelpdeskFormTemplate $template) use ($indexUrl): NavigationItem {
            $filterUrl = $indexUrl.'?'.http_build_query([
                'tableFilters' => [
                    'helpdesk_form_template_id' => ['value' => $template->id],
                ],
            ]);

            return NavigationItem::make($template->name)
                ->group(static::getNavigationGroup())
                ->icon(static::getNavigationIcon() ?? 'heroicon-o-inbox-stack')
                ->url($filterUrl)
                ->sort(static::getNavigationSort())
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.*')
                    && ((int) (request()->query('tableFilters')['helpdesk_form_template_id']['value'] ?? 0)) === $template->id
                );
        })->toArray();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Permintaan')
                    ->schema([
                        Select::make('helpdesk_form_template_id')
                            ->label('Jenis Permintaan')
                            ->options(function (): array {
                                $user = auth()->user();

                                return HelpdeskFormTemplate::where('is_active', true)
                                    ->get()
                                    ->filter(fn (HelpdeskFormTemplate $t): bool => $t->isAccessibleByUser($user))
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('data', []);
                            }),

                        Select::make('department_id')
                            ->label('Departemen')
                            ->options(Department::pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),

                        Hidden::make('requester_id'),
                    ]),

                Section::make('Detail Permintaan')
                    ->schema(fn (Get $get): array => self::buildDynamicFields($get('helpdesk_form_template_id')))
                    ->live()
                    ->key('dynamicFormFields')
                    ->hidden(fn (Get $get): bool => ! $get('helpdesk_form_template_id')),

            ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function buildDynamicFields(?int $templateId): array
    {
        if (! $templateId) {
            return [];
        }

        $fields = HelpdeskFormField::where('helpdesk_form_template_id', $templateId)
            ->orderBy('sort_order')
            ->get();

        if ($fields->isEmpty()) {
            return [];
        }

        return $fields->map(function (HelpdeskFormField $field): mixed {
            $fieldName = "data.field_{$field->id}";

            return match ($field->type) {
                FormFieldType::Text => TextInput::make($fieldName)
                    ->label($field->label)
                    ->hint($field->hint)
                    ->placeholder($field->placeholder)
                    ->required($field->is_required),

                FormFieldType::Textarea => RichEditor::make($fieldName)
                    ->label($field->label)
                    ->hint($field->hint)
                    ->toolbarButtons(['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'link', 'undo', 'redo'])
                    ->required($field->is_required),

                FormFieldType::Number => TextInput::make($fieldName)
                    ->label($field->label)
                    ->hint($field->hint)
                    ->placeholder($field->placeholder)
                    ->numeric()
                    ->required($field->is_required),

                FormFieldType::Select => Select::make($fieldName)
                    ->label($field->label)
                    ->hint($field->hint)
                    ->options(
                        collect($field->options ?? [])
                            ->pluck('label', 'value')
                            ->toArray()
                    )
                    ->required($field->is_required),

                FormFieldType::File => FileUpload::make($fieldName)
                    ->label($field->label)
                    ->hint($field->hint)
                    ->multiple()
                    ->disk('public')
                    ->directory('helpdesk-requests')
                    ->maxSize(10240)
                    ->required($field->is_required),

                FormFieldType::Date => DatePicker::make($fieldName)
                    ->label($field->label)
                    ->hint($field->hint)
                    ->required($field->is_required),

                FormFieldType::Toggle => Toggle::make($fieldName)
                    ->label($field->label)
                    ->hint($field->hint)
                    ->required($field->is_required),
            };
        })->values()->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('requester.name')
                    ->label('Pemohon')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('template.name')
                    ->label('Jenis Permintaan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('department.name')
                    ->label('Departemen')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label('Status')
                    ->html()
                    ->formatStateUsing(function (string $state, HelpdeskRequest $record): HtmlString {
                        $status = collect($record->template?->statuses ?? [])->firstWhere('value', $state);
                        $label = e($status['label'] ?? $state);
                        $hex = ltrim($status['color'] ?? '#9ca3af', '#');
                        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];

                        return new HtmlString("<span style=\"background:rgba({$r},{$g},{$b},0.15);color:#{$hex};padding:2px 10px;border-radius:6px;font-size:0.75rem;font-weight:500;display:inline-block;\">{$label}</span>");
                    }),

                TextColumn::make('assignee.name')
                    ->label('Ditugaskan Ke')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('helpdesk_form_template_id')
                    ->label('Jenis Permintaan')
                    ->options(HelpdeskFormTemplate::pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(fn (): array => HelpdeskRequest::query()
                        ->with('template')
                        ->get()
                        ->flatMap(fn (HelpdeskRequest $r) => collect($r->template?->statuses ?? []))
                        ->unique('value')
                        ->pluck('label', 'value')
                        ->toArray()
                    ),

                SelectFilter::make('department_id')
                    ->label('Departemen')
                    ->options(Department::pluck('name', 'id'))
                    ->searchable(),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with('template');

        if ($user->hasAnyRole(['super_admin', 'helpdesk_manager'])) {
            return $query;
        }

        $accessibleTemplateIds = HelpdeskFormTemplate::where('is_active', true)
            ->get()
            ->filter(fn (HelpdeskFormTemplate $t): bool => $t->isAccessibleByUser($user))
            ->pluck('id');

        return $query
            ->where('requester_id', $user->id)
            ->whereIn('helpdesk_form_template_id', $accessibleTemplateIds);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHelpdeskRequests::route('/'),
            'create' => CreateHelpdeskRequest::route('/create'),
            'view' => ViewHelpdeskRequest::route('/{record}'),
            'edit' => EditHelpdeskRequest::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
