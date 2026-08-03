<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected array $branchAccessIds = [];

    protected ?int $primaryBranchId = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $ids = $this->record->accessibleBranches()->pluck('branches.id');
        if ($this->record->branch_id) {
            $ids->push($this->record->branch_id);
        }

        $data['branch_access_ids'] = $ids->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $data['primary_branch_id'] = $this->record->primaryBranchId();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $accessAllBranches = (bool) ($data['access_all_branches'] ?? false);
        $this->branchAccessIds = $accessAllBranches ? [] : ($data['branch_access_ids'] ?? []);
        $this->primaryBranchId = ! $accessAllBranches && isset($data['primary_branch_id'])
            ? (int) $data['primary_branch_id']
            : null;
        unset($data['branch_access_ids'], $data['primary_branch_id']);
        $data['branch_id'] = $this->primaryBranchId;

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->syncBranchAccess($this->branchAccessIds, $this->primaryBranchId);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
