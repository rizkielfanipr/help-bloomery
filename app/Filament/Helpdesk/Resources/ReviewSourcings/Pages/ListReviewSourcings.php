<?php

namespace App\Filament\Helpdesk\Resources\ReviewSourcings\Pages;

use App\Enums\MaterialSourcingStatus;
use App\Filament\Helpdesk\Resources\ReviewSourcings\ReviewSourcingResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReviewSourcings extends ListRecords
{
    protected static string $resource = ReviewSourcingResource::class;

    protected ?string $subheading = 'Konfirmasi sampel dari Purchasing, evaluasi sampel, dan pilih supplier sebelum masuk Product Pricelist.';

    /**
     * Keep filter state inside Livewire without synchronizing it to the URL; the filters are
     * rendered inline in the column headers.
     *
     * @var array<string, mixed>|null
     */
    public ?array $tableFilters = null;

    public function getTabs(): array
    {
        return [
            'needs_action' => Tab::make('Needs Action')
                ->badge(fn (): int => $this->countIn(ReviewSourcingResource::NEEDS_ACTION_STATUSES))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('sourcing_status', $this->values(ReviewSourcingResource::NEEDS_ACTION_STATUSES))),
            'to_receive' => Tab::make('To Receive')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('sourcing_status', MaterialSourcingStatus::SampleHandedOver->value)),
            'to_evaluate' => Tab::make('To Evaluate')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('sourcing_status', [MaterialSourcingStatus::SampleReceived->value, MaterialSourcingStatus::UnderRndReview->value, MaterialSourcingStatus::PendingRndReview->value])),
            'all' => Tab::make('All'),
        ];
    }

    /**
     * @param  list<MaterialSourcingStatus>  $statuses
     */
    private function countIn(array $statuses): int
    {
        return ReviewSourcingResource::scopeReviewQueue(ReviewSourcingResource::getEloquentQuery())->whereIn('sourcing_status', $this->values($statuses))->count();
    }

    /**
     * @param  list<MaterialSourcingStatus>  $statuses
     * @return list<string>
     */
    private function values(array $statuses): array
    {
        return array_map(fn (MaterialSourcingStatus $status): string => $status->value, $statuses);
    }
}
