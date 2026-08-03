<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected array $branchAccessIds = [];

    protected ?int $primaryBranchId = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $accessAllBranches = (bool) ($data['access_all_branches'] ?? false);
        $this->branchAccessIds = $accessAllBranches ? [] : ($data['branch_access_ids'] ?? []);
        $this->primaryBranchId = ! $accessAllBranches && isset($data['primary_branch_id'])
            ? (int) $data['primary_branch_id']
            : null;
        unset($data['branch_access_ids'], $data['primary_branch_id']);
        $data['branch_id'] = $this->primaryBranchId;
        $data['is_active'] = true;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncBranchAccess($this->branchAccessIds, $this->primaryBranchId);
    }
}
