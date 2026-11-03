<?php

namespace App\Services\BrandMarketing\DesignMaster;

use App\Filament\Helpdesk\Resources\MarketingDesignBundles\MarketingDesignBundleResource;
use App\Models\MarketingDesignBundleVersion;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Database notifications for submit, revision required, and publish (PRD FR-14). Callers send these
 * after commit, so a rolled back transition never announces anything.
 */
class MarketingDesignNotifier
{
    public function submitted(MarketingDesignBundleVersion $version, User $actor): void
    {
        $this->send(
            $this->usersWithPermission('review design master bundles', $actor),
            Notification::make()->info()->title('Master Design menunggu review'),
            $version,
        );
    }

    public function revisionRequired(MarketingDesignBundleVersion $version, User $actor): void
    {
        $this->send(
            User::query()->where('is_active', true)->whereKey($version->created_by)->whereKeyNot($actor->id)->get(),
            Notification::make()->warning()->title('Master Design perlu revisi'),
            $version,
        );
    }

    public function published(MarketingDesignBundleVersion $version, User $actor): void
    {
        $this->send(
            $this->usersWithPermission('download design master production files', $actor),
            Notification::make()->success()->title('Master Design baru dipublikasikan'),
            $version,
        );
    }

    /** @return Collection<int, User> */
    private function usersWithPermission(string $permission, User $actor): Collection
    {
        return User::query()->where('is_active', true)->permission($permission)->whereKeyNot($actor->id)->get();
    }

    /** @param  Collection<int, User>  $recipients */
    private function send(Collection $recipients, Notification $notification, MarketingDesignBundleVersion $version): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        $bundle = $version->bundle;

        $notification
            ->body("{$bundle->code} · {$bundle->name} {$version->label()}")
            ->actions([
                Action::make('open')->label('Buka')->url(MarketingDesignBundleResource::getUrl('view', ['record' => $bundle], panel: 'helpdesk')),
            ])
            ->sendToDatabase($recipients);
    }
}
