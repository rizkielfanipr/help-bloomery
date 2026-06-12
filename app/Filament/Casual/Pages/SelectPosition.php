<?php

namespace App\Filament\Casual\Pages;

use App\Models\CasualPositionOpening;
use App\Models\CasualPositionRegistration;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class SelectPosition extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.casual.pages.select-position';

    protected static string $layout = 'filament.casual.layouts.bare';

    public function mount(): void
    {
        $user = auth()->user();

        if ($user->casual_position_id === null || $user->casual_shift_id === null) {
            return;
        }

        $registration = $user->casualPositionRegistration()
            ->with(['opening.casualShift'])
            ->first();

        if ($registration) {
            $shiftStart = Carbon::parse(
                $registration->opening->work_date->toDateString()
                .' '
                .$registration->opening->casualShift->start_time
            );

            if (now()->gte($shiftStart)) {
                $this->redirect(route('filament.casual.pages.clock-page'));

                return;
            }
        }

        $this->redirect(route('filament.casual.pages.shift-detail-page'));
    }

    public function registerOpening(int $openingId): void
    {
        $user = auth()->user();

        $opening = CasualPositionOpening::with(['casualPosition', 'casualShift'])
            ->where('id', $openingId)
            ->where('is_active', true)
            ->where('work_date', '>=', today())
            ->firstOrFail();

        try {
            DB::transaction(function () use ($user, $opening): void {
                $count = CasualPositionRegistration::where('casual_position_opening_id', $opening->id)
                    ->lockForUpdate()
                    ->count();

                if ($count >= $opening->total_slots) {
                    Notification::make()
                        ->title('Slot penuh')
                        ->body('Maaf, slot untuk posisi ini sudah penuh. Coba lowongan lain.')
                        ->danger()
                        ->send();

                    return;
                }

                CasualPositionRegistration::create([
                    'casual_position_opening_id' => $opening->id,
                    'user_id' => $user->id,
                ]);

                $user->update([
                    'casual_position_id' => $opening->casual_position_id,
                    'casual_shift_id' => $opening->casual_shift_id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            Notification::make()
                ->title('Sudah terdaftar')
                ->body('Anda sudah terdaftar untuk lowongan ini.')
                ->warning()
                ->send();

            return;
        }

        if ($user->fresh()->casual_position_id === null) {
            return;
        }

        Notification::make()
            ->title('Berhasil mendaftar!')
            ->body('Posisi: '.$opening->casualPosition->name.' — '.$opening->work_date->translatedFormat('d M Y'))
            ->success()
            ->send();

        $this->redirect(route('filament.casual.pages.shift-detail-page'));
    }

    public function getOpenings()
    {
        return CasualPositionOpening::available()
            ->with(['casualPosition', 'casualShift'])
            ->withCount('registrations')
            ->get();
    }
}
