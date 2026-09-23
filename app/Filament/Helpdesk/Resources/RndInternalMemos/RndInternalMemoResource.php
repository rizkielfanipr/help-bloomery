<?php

namespace App\Filament\Helpdesk\Resources\RndInternalMemos;

use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ListRndInternalMemos;
use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ViewRndInternalMemo;
use App\Models\RndInternalMemo;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * docs/rnd-internal-memo-prd.md. Every action here is gated by RndInternalMemoPolicy
 * (auto-discovered by Laravel for the RndInternalMemo model), never by role name.
 */
class RndInternalMemoResource extends Resource
{
    protected static ?string $model = RndInternalMemo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Research & Development';

    protected static ?string $navigationLabel = 'Memo Internal';

    protected static ?string $modelLabel = 'Memo Internal';

    protected static ?string $pluralModelLabel = 'Memo Internal';

    protected static ?string $slug = 'rnd-internal-memos';

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->can('viewAny', RndInternalMemo::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->can('view', $record) ?? false;
    }

    public static function canCreate(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->can('create', RndInternalMemo::class) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->can('delete', $record) ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRndInternalMemos::route('/'),
            'view' => ViewRndInternalMemo::route('/{record}'),
        ];
    }
}
