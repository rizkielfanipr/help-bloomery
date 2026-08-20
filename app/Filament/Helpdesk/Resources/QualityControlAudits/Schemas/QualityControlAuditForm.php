<?php

namespace App\Filament\Helpdesk\Resources\QualityControlAudits\Schemas;

use App\Models\Branch;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QualityControlAuditForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Audit')->schema([
                    Hidden::make('auditor_id')->default(fn (): ?int => auth()->id()),
                    Grid::make(3)->schema([
                        TextInput::make('audit_number')
                            ->label('Nomor Audit')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Dibuat otomatis'),
                        Select::make('branch_id')
                            ->label('Store/Branch')
                            ->options(function () {
                                $query = Branch::query()->where('is_active', true)->orderBy('name');
                                $user = auth()->user();

                                if ($user && ! $user->canAccessAllBranches()) {
                                    $query->whereIn('id', $user->accessibleBranchIds());
                                }

                                return $query->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required(),
                        DatePicker::make('audit_date')
                            ->label('Tanggal Audit')
                            ->default(today())
                            ->required(),
                    ]),
                    Grid::make(3)->schema([
                        Select::make('audit_type')
                            ->label('Jenis Audit')
                            ->options([
                                'routine' => 'Rutin',
                                'follow_up' => 'Follow Up',
                                'surprise' => 'Surprise Audit',
                            ])
                            ->default('routine')
                            ->required(),
                        TextInput::make('store_leader_name')
                            ->label('Store Leader')
                            ->maxLength(255),
                        Toggle::make('store_leader_present')
                            ->label('Store Leader Hadir'),
                    ]),
                    Select::make('status')
                        ->label('Status Audit')
                        ->options([
                            'draft' => 'Draft',
                            'submitted' => 'Submitted',
                        ])
                        ->default('draft')
                        ->required(),
                ]),

                Section::make('Hasil Penilaian')
                    ->visibleOn(['edit', 'view'])
                    ->schema([
                        Grid::make(3)->schema([
                            Placeholder::make('score')->label('Nilai')->content(fn ($record): string => number_format((float) $record?->score, 2).'%'),
                            Placeholder::make('rating')->label('Status')->content(fn ($record): string => match ($record?->rating) {
                                'green' => 'Green', 'yellow' => 'Yellow', 'red' => 'Red', default => 'Belum Dinilai',
                            }),
                            Placeholder::make('points')->label('Perolehan Poin')->content(fn ($record): string => ($record?->earned_points ?? 0).'/'.($record?->maximum_points ?? 0)),
                        ]),
                    ]),

                Section::make('Ringkasan Audit')
                    ->visibleOn(['edit', 'view'])
                    ->schema([
                        Textarea::make('top_findings')->label('Top 3 Findings')->rows(3),
                        Textarea::make('corrective_action_required')->label('Corrective Action Required')->rows(3),
                        Textarea::make('overall_notes')->label('Overall Notes')->rows(3),
                    ]),
            ]);
    }
}
