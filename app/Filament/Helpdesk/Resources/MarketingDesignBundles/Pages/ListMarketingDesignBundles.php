<?php

namespace App\Filament\Helpdesk\Resources\MarketingDesignBundles\Pages;

use App\Actions\BrandMarketing\DesignMaster\CreateDesignBundleAction;
use App\Filament\Helpdesk\Resources\MarketingDesignBundles\MarketingDesignBundleResource;
use App\Models\MarketingDesignBundle;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;

class ListMarketingDesignBundles extends ListRecords
{
    protected static string $resource = MarketingDesignBundleResource::class;

    /** @var array<string, mixed>|null */
    public ?array $tableFilters = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createBundle')
                ->label('Buat Bundle')
                ->icon('heroicon-o-plus-circle')
                ->visible(fn (): bool => auth()->user()?->can('create', MarketingDesignBundle::class) ?? false)
                ->modalHeading('Buat Bundle Master Design')
                ->modalDescription('Sistem membuat Version 1 berstatus Draft. Item dan file ditambahkan di halaman detail.')
                ->modalSubmitActionLabel('Buat Bundle')
                ->schema([
                    ...MarketingDesignBundleResource::bundleFormSchema(),
                    Textarea::make('change_summary')->label('Ringkasan Version 1')->rows(2)->maxLength(5000),
                ])
                ->action(function (array $data, Action $action): void {
                    try {
                        $bundle = app(CreateDesignBundleAction::class)->execute(auth()->user(), $data);
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title('Bundle belum dapat dibuat')->body($exception->validator->errors()->first())->send();
                        $action->halt();

                        return;
                    }

                    Notification::make()->success()->title('Bundle berhasil dibuat')->send();
                    $this->redirect(MarketingDesignBundleResource::getUrl('view', ['record' => $bundle]));
                }),
        ];
    }
}
