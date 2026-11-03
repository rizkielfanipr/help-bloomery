<?php

namespace App\Filament\Helpdesk\Resources\VendorComplianceIncidents\Pages;

use App\Actions\Purchasing\VendorCompliance\ReviewLateDeliveryIncidentAction;
use App\Filament\Helpdesk\Resources\VendorComplianceIncidents\VendorComplianceIncidentResource;
use App\Models\VendorComplianceIncident;
use App\Policies\VendorComplianceIncidentPolicy;
use App\Services\Purchasing\VendorCompliance\LateDeliveryScoring;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Alignment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ViewVendorComplianceIncident extends ViewRecord
{
    protected static string $resource = VendorComplianceIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmLiability')->label('Konfirmasi Pelanggaran')->icon('heroicon-o-exclamation-triangle')->color('danger')
                ->visible(fn (VendorComplianceIncident $record): bool => $this->canReview($record))
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Pelanggaran Keterlambatan?')
                ->modalDescription(function (VendorComplianceIncident $record): string {
                    $score = app(LateDeliveryScoring::class)->score((int) $record->delay_days);

                    return "Keterlambatan maksimum {$record->delay_days} hari menjadi severity {$score['severity']} dengan {$score['points']} poin demerit (".LateDeliveryScoring::VERSION.'). Keputusan ini final.';
                })
                ->modalSubmitActionLabel('Konfirmasi')
                ->modalFooterActionsAlignment(Alignment::End)
                ->action(fn (VendorComplianceIncident $record, Action $action) => $this->runGuarded($action, function () use ($record): void {
                    app(ReviewLateDeliveryIncidentAction::class)->confirm(auth()->user(), $record);
                    $this->refreshRecord('Pelanggaran keterlambatan dikonfirmasi');
                })),

            Action::make('waiveLiability')->label('Berikan Pengecualian')->icon('heroicon-o-shield-check')->color('gray')
                ->visible(fn (VendorComplianceIncident $record): bool => $this->canReview($record))
                ->modalHeading('Berikan Pengecualian Keterlambatan')
                ->modalDescription('Vendor tidak menerima poin demerit dan insiden ditutup. Keputusan ini final.')
                ->schema([
                    Textarea::make('waiver_reason')->label('Alasan Pengecualian')->required()->minLength(5)->maxLength(2000)->rows(3)
                        ->placeholder('Contoh: tanggal dibutuhkan PO diubah setelah pengiriman dijadwalkan.'),
                ])
                ->modalSubmitActionLabel('Kecualikan')
                ->action(fn (array $data, VendorComplianceIncident $record, Action $action) => $this->runGuarded($action, function () use ($data, $record): void {
                    app(ReviewLateDeliveryIncidentAction::class)->waive(auth()->user(), $record, (string) $data['waiver_reason']);
                    $this->refreshRecord('Keterlambatan dikecualikan');
                })),

            EditAction::make(),
        ];
    }

    /** The Policy is asked directly: `Gate::before` would show SUPERADMIN buttons for decided incidents. */
    private function canReview(VendorComplianceIncident $record): bool
    {
        $user = auth()->user();

        return $user !== null && app(VendorComplianceIncidentPolicy::class)->reviewLiability($user, $record);
    }

    private function runGuarded(Action $action, \Closure $callback): void
    {
        try {
            $callback();
        } catch (ValidationException|AuthorizationException $exception) {
            $body = $exception instanceof ValidationException ? collect($exception->errors())->flatten()->implode(' ') : $exception->getMessage();
            Notification::make()->danger()->title('Keputusan tidak dapat disimpan')->body($body)->persistent()->send();
            $this->record->refresh();
            $action->halt();
        }
    }

    private function refreshRecord(string $title): void
    {
        $this->record->refresh();
        Notification::make()->success()->title($title)->send();
    }
}
