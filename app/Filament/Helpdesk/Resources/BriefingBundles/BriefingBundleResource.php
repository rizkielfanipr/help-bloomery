<?php

namespace App\Filament\Helpdesk\Resources\BriefingBundles;

use App\Actions\Briefing\BriefingBundleReviewResult;
use App\Actions\Briefing\ReopenBriefingBundleAction;
use App\Actions\Briefing\ReviewBriefingBundleAction;
use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingPeriod;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\BriefingBundles\Pages\ListBriefingBundles;
use App\Models\Branch;
use App\Models\BriefingRecord;
use App\Services\BriefingBundleDetailBuilder;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * docs/daily-briefing-bundle-approval-prd.md §13 (Phase 5): Monitoring Poin as one row per branch
 * bundle, with a single review modal. Every list, count, filter option and mutation goes through
 * the same branch scope as getEloquentQuery(), and the review buttons are only a convenience —
 * the actions re-check everything server-side.
 */
class BriefingBundleResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'briefing items';

    protected static ?string $permissionLabel = 'Monitoring Poin Briefing';

    protected static ?string $model = BriefingRecord::class;

    protected static ?string $slug = 'briefing-bundles';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';

    protected static ?string $navigationLabel = 'Monitoring Poin';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Bundle Briefing';

    protected static ?string $pluralModelLabel = 'Monitoring Poin Briefing';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('period_start')
                    ->label('TANGGAL')
                    ->date('d M Y')
                    ->weight('semibold')
                    ->description(fn (BriefingRecord $record): ?string => $record->period !== BriefingPeriod::Daily
                        ? 's/d '.$record->period_end?->format('d M Y')
                        : null)
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('CABANG')
                    ->placeholder('-'),

                TextColumn::make('period')
                    ->label('PERIODE')
                    ->badge(),

                TextColumn::make('progress')
                    ->label('PROGRES')
                    ->badge()
                    ->color(fn (BriefingRecord $record): string => $record->required_count > 0 && $record->filled_count >= $record->required_count ? 'success' : 'gray')
                    ->state(fn (BriefingRecord $record): string => $record->filled_count.'/'.$record->required_count.' poin'),

                TextColumn::make('completed_at')
                    ->label('WAKTU LENGKAP')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum lengkap')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('STATUS')
                    ->badge(),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Lihat Detail')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->tooltip('Lihat Detail')
                    ->color('gray')
                    ->modalHeading(fn (BriefingRecord $record): string => 'Bundle '.$record->period->getLabel().' · '.($record->branch?->name ?? '-'))
                    ->modalWidth(Width::FiveExtraLarge)
                    ->extraModalWindowAttributes(['class' => 'briefing-bundle-modal'])
                    ->modalContent(fn (BriefingRecord $record, Action $action): View => view('filament.helpdesk.briefing-bundles.review-modal', [
                        'detail' => app(BriefingBundleDetailBuilder::class)->build($record),
                        'action' => $action,
                    ]))
                    ->registerModalActions([
                        static::approveAction(),
                        static::rejectAction(),
                        static::reopenAction(),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->visible(fn (): bool => static::canViewAny()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('STATUS')
                    ->options(BriefingBundleStatus::class),

                SelectFilter::make('branch_id')
                    ->label('CABANG')
                    ->options(fn (): array => static::branchFilterOptions()),

                SelectFilter::make('period')
                    ->label('PERIODE')
                    ->options(BriefingPeriod::class),

                Filter::make('period_start')
                    ->label('TANGGAL')
                    ->schema([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $from): Builder => $q->whereDate('period_start', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $q, string $until): Builder => $q->whereDate('period_start', '<=', $until)))
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        ($data['from'] ?? null) ? 'Dari: '.Carbon::parse($data['from'])->isoFormat('D MMM Y') : null,
                        ($data['until'] ?? null) ? 'Sampai: '.Carbon::parse($data['until'])->isoFormat('D MMM Y') : null,
                    ]))),

                TernaryFilter::make('completed_at')
                    ->label('KELENGKAPAN')
                    ->nullable()
                    ->trueLabel('Sudah lengkap')
                    ->falseLabel('Belum lengkap'),

            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->hiddenFilterIndicators()
            ->extraAttributes(['class' => 'briefing-bundle-table'])
            ->defaultSort('period_start', 'desc')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBriefingBundles::route('/'),
        ];
    }

    /**
     * Branch scope comes from the bundle's own branch_id snapshot, never the contributors' current
     * branch. Fails closed: without `view briefing items` no row is returned. Only rows that are
     * bundles (have a status) are listed; ungrouped legacy rows wait for the grouping command.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->whereNotNull('status')
            ->with(['branch:id,name'])
            ->withCount([
                'requirements as required_count' => fn (Builder $q): Builder => $q->where('is_required', true),
                'items as filled_count' => fn (Builder $q): Builder => $q->where('is_completed', true)->where('requires_correction', false),
            ]);

        $user = auth()->user();

        if (! $user?->can('view briefing items')) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->canAccessAllBranches()) {
            return $query;
        }

        // Same scope as User::accessibleBranchIds(), expressed as a subquery: Filament rebuilds this
        // query several times per render, and resolving the IDs eagerly would cost a query each time.
        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('branch_id', $user->accessibleBranches()->getQuery()->select('branches.id'));

            if ($user->branch_id) {
                $scope->orWhere('branch_id', $user->branch_id);
            }
        });
    }

    /**
     * @return Collection<int, Branch>
     */
    /**
     * Options for the CABANG header filter, limited to the viewer's branch scope.
     *
     * @return array<int, string>
     */
    public static function branchFilterOptions(): array
    {
        return static::accessibleBranches()->pluck('name', 'id')->all();
    }

    private static function accessibleBranches(): Collection
    {
        $user = auth()->user();

        return Branch::query()
            ->when(! $user->canAccessAllBranches(), fn (Builder $q): Builder => $q->whereIn('id', $user->accessibleBranchIds()))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Setujui Bundle')
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->requiresConfirmation()
            ->modalHeading('Setujui seluruh bundle?')
            ->modalDescription('Semua poin dalam bundle ini akan disetujui sekaligus dan tidak dapat diubah staff tanpa dibuka kembali.')
            ->fillForm(fn (BriefingRecord $record): array => ['seen_updated_at' => $record->updated_at?->toIso8601String()])
            ->schema([
                Hidden::make('seen_updated_at'),
                Textarea::make('notes')->label('Catatan (opsional)')->rows(2),
            ])
            ->action(fn (array $data, BriefingRecord $record): bool => static::notify(app(ReviewBriefingBundleAction::class)->approve(
                auth()->user(),
                $record,
                $data['notes'] ?? null,
                static::seenAt($data),
            )))
            ->cancelParentActions()
            ->visible(fn (BriefingRecord $record): bool => static::showsReviewButton($record, BriefingBundleStatus::SupervisorReview));
    }

    private static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Tolak Bundle')
            ->color('danger')
            ->icon('heroicon-o-x-circle')
            ->modalHeading('Tolak bundle')
            ->modalWidth(Width::ThreeExtraLarge)
            ->extraModalWindowAttributes(['class' => 'briefing-bundle-modal'])
            ->fillForm(fn (BriefingRecord $record): array => [
                'seen_updated_at' => $record->updated_at?->toIso8601String(),
                'reopen_for_correction' => true,
            ])
            ->schema(fn (BriefingRecord $record): array => [
                Hidden::make('seen_updated_at'),
                Textarea::make('reason')->label('Alasan penolakan')->required()->rows(3),
                Section::make('Poin yang perlu diperbaiki')
                    ->description('Tandai poin yang harus diperbaiki staff. Catatan per poin bersifat opsional.')
                    ->schema($record->requirements()->orderBy('sort_order')->get()->flatMap(fn ($requirement): array => [
                        Toggle::make("corrections.{$requirement->task_key}.flag")->label($requirement->label)->live(),
                        Textarea::make("corrections.{$requirement->task_key}.note")
                            ->label('Catatan untuk '.$requirement->label)
                            ->rows(2)
                            ->visible(fn (Get $get): bool => (bool) $get("corrections.{$requirement->task_key}.flag")),
                    ])->all()),
                Toggle::make('reopen_for_correction')
                    ->label('Langsung buka untuk perbaikan')
                    ->helperText('Staff dapat memperbaiki poin yang ditandai tanpa menunggu langkah buka kembali terpisah.'),
            ])
            ->action(function (array $data, BriefingRecord $record): bool {
                $corrections = collect($data['corrections'] ?? [])
                    ->filter(fn (array $correction): bool => (bool) ($correction['flag'] ?? false))
                    ->map(fn (array $correction): ?string => $correction['note'] ?? null)
                    ->all();

                return static::notify(app(ReviewBriefingBundleAction::class)->reject(
                    auth()->user(),
                    $record,
                    (string) $data['reason'],
                    $corrections,
                    (bool) ($data['reopen_for_correction'] ?? false),
                    static::seenAt($data),
                ));
            })
            ->cancelParentActions()
            ->visible(fn (BriefingRecord $record): bool => static::showsReviewButton($record, BriefingBundleStatus::SupervisorReview));
    }

    private static function reopenAction(): Action
    {
        return Action::make('reopen')
            ->label('Buka Kembali')
            ->color('warning')
            ->icon('heroicon-o-arrow-path')
            ->modalHeading('Buka kembali bundle')
            ->modalDescription('Bundle yang sudah disetujui akan kehilangan status disetujui sampai disetujui ulang.')
            ->schema([
                Textarea::make('reason')->label('Alasan membuka kembali')->required()->rows(3),
            ])
            ->action(fn (array $data, BriefingRecord $record): bool => static::notify(
                app(ReopenBriefingBundleAction::class)->execute(auth()->user(), $record, (string) $data['reason']),
            ))
            ->cancelParentActions()
            ->visible(fn (BriefingRecord $record): bool => static::showsReviewButton($record, BriefingBundleStatus::Approved, BriefingBundleStatus::Rejected));
    }

    /**
     * Button visibility only. Every row already passed the branch scope in getEloquentQuery(), so
     * repeating the per-branch policy here would query the user's branch access once per row; the
     * review actions still run the full policy on the locked row when a button is used.
     */
    private static function showsReviewButton(BriefingRecord $record, BriefingBundleStatus ...$statuses): bool
    {
        return (auth()->user()?->can('review briefing items') ?? false)
            && in_array($record->status, $statuses, true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function seenAt(array $data): ?Carbon
    {
        return filled($data['seen_updated_at'] ?? null) ? Carbon::parse($data['seen_updated_at']) : null;
    }

    private static function notify(BriefingBundleReviewResult $result): bool
    {
        Notification::make()
            ->title($result->message)
            ->status($result->successful ? 'success' : 'danger')
            ->send();

        return $result->successful;
    }
}
