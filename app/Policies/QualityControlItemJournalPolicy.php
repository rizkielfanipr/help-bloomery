<?php

namespace App\Policies;

use App\Models\QualityControlItemJournal;
use App\Models\User;

class QualityControlItemJournalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view quality control item journals');
    }

    public function view(User $user, QualityControlItemJournal $journal): bool
    {
        return $this->viewAny($user)
            && ($user->can('view all quality control item journals') || $journal->created_by === $user->id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->can('create quality control item journals');
    }

    public function submit(User $user): bool
    {
        return $this->create($user) && $user->can('submit quality control item journals');
    }

    public function retryAttachments(User $user, QualityControlItemJournal $journal): bool
    {
        return $this->view($user, $journal);
    }

    public function deleteAttachments(User $user, QualityControlItemJournal $journal): bool
    {
        return $this->view($user, $journal)
            && $user->can('delete quality control item journal attachments');
    }

    public function update(User $user, QualityControlItemJournal $journal): bool
    {
        return false;
    }

    public function delete(User $user, QualityControlItemJournal $journal): bool
    {
        return false;
    }
}
