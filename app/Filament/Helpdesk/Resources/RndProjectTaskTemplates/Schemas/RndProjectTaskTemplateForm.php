<?php

namespace App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\Schemas;

use App\Enums\RndProjectTaskCategory;
use App\Enums\RndProjectTaskPriority;
use App\Models\Branch;
use App\Models\RndProjectTaskTemplate;
use App\Models\RndProjectTaskTemplateCheckpoint;
use App\Models\User;
use App\Services\Rnd\ProjectTask\ProjectTaskAssigneeResolver;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class RndProjectTaskTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Template')
                ->description('Template hanya menyimpan blueprint checkpoint. Branch dan PIC dipilih saat template diterapkan ke Project.')
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Template')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->placeholder('Contoh: Checklist Launching Menu'),
                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->helperText('Template nonaktif tidak dapat diterapkan, tetapi Task yang sudah dibuat tetap ada.')
                        ->default(true),
                    Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(3)
                        ->columnSpanFull()
                        ->placeholder('Kapan template ini digunakan dan apa tujuannya...'),
                ])
                ->columns(2),

            Section::make('Checkpoint')
                ->description('Urutan checkpoint menjadi urutan Task. Branch & PIC disimpan di template; tanggal assign dan deadline diatur saat template digunakan pada Project.')
                ->schema([
                    Repeater::make('checkpoints')
                        ->hiddenLabel()
                        ->relationship()
                        ->orderColumn('sort_order')
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->minItems(1)
                        ->maxItems(RndProjectTaskTemplate::MAX_CHECKPOINTS)
                        ->addActionLabel('Tambah Checkpoint')
                        ->itemLabel(fn (array $state): ?string => filled($state['title'] ?? null) ? $state['title'] : 'Checkpoint baru')
                        ->validationMessages([
                            'min' => 'Template wajib memiliki minimal satu checkpoint.',
                            'max' => 'Template maksimal memiliki :max checkpoint.',
                        ])
                        ->schema(static::checkpointComponents())
                        ->columns(4),
                ]),
        ]);
    }

    /** @return array<int, Component> */
    private static function checkpointComponents(): array
    {
        return [
            TextInput::make('title')
                ->label('Nama Task')
                ->required()
                ->maxLength(255)
                ->columnSpan(2),
            Select::make('task_type')
                ->label('Kategori')
                ->options(RndProjectTaskCategory::class)
                ->required(),
            Select::make('priority')
                ->label('Prioritas')
                ->options(RndProjectTaskPriority::class)
                ->default(RndProjectTaskPriority::Medium->value)
                ->required(),
            static::branchPicRepeater(),
            Textarea::make('description')
                ->label('Instruksi Default')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    /**
     * Branch & PIC per checkpoint, stored in the checkpoint's Branch and PIC pivots. PICs are
     * limited to users eligible for the chosen Branch and are re-validated when the template is
     * applied, since access can change afterwards.
     */
    private static function branchPicRepeater(): Repeater
    {
        return Repeater::make('branch_pics')
            ->label('Branch & PIC')
            ->addActionLabel('Tambah Branch')
            ->defaultItems(0)
            ->columns(2)
            ->columnSpanFull()
            ->dehydrated(false)
            ->loadStateFromRelationshipsUsing(function (Repeater $component, ?RndProjectTaskTemplateCheckpoint $record): void {
                $items = [];

                foreach ($record?->load(['branches', 'picUsers'])->branchPicRows() ?? [] as $row) {
                    $uuid = $component->generateUuid();
                    $uuid !== null ? $items[$uuid] = $row : $items[] = $row;
                }

                $component->rawState($items);
            })
            ->saveRelationshipsUsing(fn (RndProjectTaskTemplateCheckpoint $record, ?array $state) => $record->syncBranchPics(array_values($state ?? [])))
            ->schema([
                Select::make('branch_id')
                    ->label('Branch')
                    ->options(fn (): array => Branch::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->distinct()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('user_ids', [])),
                Select::make('user_ids')
                    ->label('PIC')
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->options(fn (Get $get): array => filled($get('branch_id'))
                        ? app(ProjectTaskAssigneeResolver::class)
                            ->eligibleUsersForBranch((int) $get('branch_id'))
                            ->mapWithKeys(fn (User $user): array => [$user->id => $user->display_username])
                            ->all()
                        : [])
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $resolver = app(ProjectTaskAssigneeResolver::class);
                        $branchId = (int) $get('branch_id');
                        $ineligible = User::query()
                            ->whereKey((array) $value)
                            ->get()
                            ->reject(fn (User $user): bool => $resolver->isEligible($user, $branchId));

                        if ($ineligible->isNotEmpty() || User::query()->whereKey((array) $value)->count() !== count((array) $value)) {
                            $fail('PIC harus pengguna aktif yang dapat mengakses Branch tersebut.');
                        }
                    })
                    ->helperText('Hanya pengguna aktif yang memiliki akses ke Branch.'),
            ]);
    }
}
