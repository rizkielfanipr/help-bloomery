<?php

namespace App\Enums;

/**
 * Where a WIP Shelf Life master was created from (docs/rnd-wip-shelf-life-prd.md §14.3). It only
 * selects the authorization rule; both sources write the same master record.
 */
enum RndWipShelfLifeSource: string
{
    case ShelfLifeMenu = 'shelf_life_menu';
    case Project = 'project';
    case InternalMemo = 'internal_memo';

    /**
     * Permissions the actor must all hold to create a missing master from this source (§15).
     *
     * @return list<string>
     */
    public function requiredPermissions(): array
    {
        return match ($this) {
            self::ShelfLifeMenu => ['manage wip shelf life'],
            self::Project => ['edit rnd projects', 'manage wip shelf life'],
            self::InternalMemo => ['update rnd internal memo', 'manage wip shelf life'],
        };
    }
}
