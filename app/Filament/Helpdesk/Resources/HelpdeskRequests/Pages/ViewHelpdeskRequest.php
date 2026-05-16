<?php

namespace App\Filament\Helpdesk\Resources\HelpdeskRequests\Pages;

use App\Enums\FormFieldType;
use App\Filament\Helpdesk\Resources\HelpdeskRequests\HelpdeskRequestResource;
use App\Models\HelpdeskFormField;
use App\Models\HelpdeskRequest;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Activity;

class ViewHelpdeskRequest extends ViewRecord
{
    protected static string $resource = HelpdeskRequestResource::class;

    protected function getHeaderActions(): array
    {
        /** @var HelpdeskRequest $record */
        $record = $this->getRecord();

        $actions = [EditAction::make()];

        if (! auth()->user()->hasAnyRole(['super_admin', 'helpdesk_manager', 'helpdesk_staff'])) {
            return $actions;
        }

        $statuses = collect($record->template?->statuses ?? []);
        $currentStatus = $statuses->firstWhere('value', $record->status);

        if (! $currentStatus || ($currentStatus['is_final'] ?? false)) {
            return $actions;
        }

        $branches = collect($currentStatus['branches'] ?? []);
        $redirectUrl = $this->getResource()::getUrl('view', ['record' => $record]);

        if ($branches->isNotEmpty()) {
            foreach ($branches as $branch) {
                $nextValue = $branch['next_status'];
                $branchLabel = $branch['label'];
                $color = $this->hexToNamedColor($branch['color'] ?? '#6b7280');

                $actions[] = Action::make("transition_{$nextValue}")
                    ->label($branchLabel)
                    ->color($color)
                    ->requiresConfirmation()
                    ->modalHeading("Konfirmasi: {$branchLabel}")
                    ->modalDescription("Yakin ingin mengubah status menjadi \"{$branchLabel}\"?")
                    ->action(function () use ($record, $nextValue, $branchLabel, $redirectUrl): void {
                        $oldStatus = $record->status;
                        $record->update(['status' => $nextValue]);

                        activity()
                            ->performedOn($record)
                            ->causedBy(auth()->user())
                            ->withProperties(['from' => $oldStatus, 'to' => $nextValue, 'label' => $branchLabel])
                            ->log('status_changed');

                        Notification::make()
                            ->title("Status diperbarui ke: {$branchLabel}")
                            ->success()
                            ->send();

                        $this->redirect($redirectUrl);
                    });
            }
        } else {
            $currentIndex = $statuses->search(fn ($s) => $s['value'] === $record->status);
            $nextStatus = $statuses->get($currentIndex + 1);

            if ($nextStatus) {
                $nextValue = $nextStatus['value'];
                $nextLabel = $nextStatus['label'];
                $color = $this->hexToNamedColor($nextStatus['color'] ?? '#6b7280');

                $actions[] = Action::make("advance_to_{$nextValue}")
                    ->label("Lanjutkan ke: {$nextLabel}")
                    ->color($color)
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Perubahan Status')
                    ->modalDescription("Yakin ingin melanjutkan status ke \"{$nextLabel}\"?")
                    ->action(function () use ($record, $nextValue, $nextLabel, $redirectUrl): void {
                        $oldStatus = $record->status;
                        $record->update(['status' => $nextValue]);

                        activity()
                            ->performedOn($record)
                            ->causedBy(auth()->user())
                            ->withProperties(['from' => $oldStatus, 'to' => $nextValue, 'label' => $nextLabel])
                            ->log('status_changed');

                        Notification::make()
                            ->title("Status diperbarui ke: {$nextLabel}")
                            ->success()
                            ->send();

                        $this->redirect($redirectUrl);
                    });
            }
        }

        return $actions;
    }

    private function hexToNamedColor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) < 6) {
            return 'primary';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        if ($r > 180 && $r > $g * 1.5 && $r > $b * 1.5) {
            return 'danger';
        }
        if ($g > 150 && $g > $r * 1.3 && $g > $b) {
            return 'success';
        }
        if ($r > 180 && $g > 130 && $b < 100) {
            return 'warning';
        }
        if ($b > 150 && $b >= $r) {
            return 'info';
        }

        return 'primary';
    }

    public function infolist(Schema $schema): Schema
    {
        /** @var HelpdeskRequest $record */
        $record = $this->getRecord();

        $fields = HelpdeskFormField::where('helpdesk_form_template_id', $record->helpdesk_form_template_id)
            ->orderBy('sort_order')
            ->get();

        $dynamicEntries = $fields->map(function (HelpdeskFormField $field) use ($record): TextEntry {
            $key = "field_{$field->id}";
            $value = data_get($record->data, $key);

            $entry = TextEntry::make("data.field_{$field->id}")
                ->label($field->label);

            return match ($field->type) {
                FormFieldType::Toggle => $entry->state($value ? 'Ya' : 'Tidak'),
                FormFieldType::File => $entry->state(
                    is_array($value) ? implode(', ', $value) : ($value ?? '-')
                ),
                FormFieldType::Textarea => $entry->state($value ?? '')->html(),
                default => $entry->state($value ?? '-'),
            };
        })->values()->all();

        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Permintaan')
                    ->schema([
                        TextEntry::make('template.name')
                            ->label('Jenis Permintaan'),

                        TextEntry::make('department.name')
                            ->label('Departemen')
                            ->placeholder('-'),

                        TextEntry::make('requester.name')
                            ->label('Pemohon'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->html()
                            ->formatStateUsing(function (string $state) use ($record): HtmlString {
                                $status = collect($record->template?->statuses ?? [])->firstWhere('value', $state);
                                $label = e($status['label'] ?? $state);
                                $hex = ltrim($status['color'] ?? '#9ca3af', '#');
                                [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];

                                return new HtmlString("<span style=\"background:rgba({$r},{$g},{$b},0.15);color:#{$hex};padding:2px 10px;border-radius:6px;font-size:0.75rem;font-weight:500;display:inline-block;\">{$label}</span>");
                            }),

                        TextEntry::make('assignee.name')
                            ->label('Ditugaskan Ke')
                            ->placeholder('-'),

                        TextEntry::make('created_at')
                            ->label('Tanggal Dibuat')
                            ->dateTime('d M Y H:i'),
                    ]),

                Section::make('Detail Permintaan')
                    ->schema($dynamicEntries)
                    ->hidden(fn (): bool => empty($dynamicEntries)),

                Section::make('Catatan Manager')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Catatan')
                            ->placeholder('Belum ada catatan'),
                    ])
                    ->hidden(fn (): bool => ! $record->notes),

                Section::make('Riwayat Status')
                    ->schema([
                        TextEntry::make('status_history')
                            ->label('')
                            ->html()
                            ->state(function () use ($record): HtmlString {
                                $activities = Activity::where('subject_type', HelpdeskRequest::class)
                                    ->where('subject_id', $record->id)
                                    ->where('description', 'status_changed')
                                    ->orderBy('created_at', 'asc')
                                    ->with('causer')
                                    ->get();

                                if ($activities->isEmpty()) {
                                    return new HtmlString('<p class="text-sm text-gray-400 italic">Belum ada perubahan status.</p>');
                                }

                                $statuses = collect($record->template?->statuses ?? []);

                                $items = $activities->map(function (Activity $activity) use ($statuses): string {
                                    $fromValue = $activity->properties['from'] ?? null;
                                    $toValue = $activity->properties['to'] ?? null;

                                    $fromStatus = $statuses->firstWhere('value', $fromValue);
                                    $toStatus = $statuses->firstWhere('value', $toValue);

                                    $fromLabel = e($fromStatus['label'] ?? $fromValue ?? '-');
                                    $toLabel = e($toStatus['label'] ?? $toValue ?? '-');

                                    $toHex = ltrim($toStatus['color'] ?? '9ca3af', '#');
                                    [$r, $g, $b] = array_map('hexdec', str_split(str_pad($toHex, 6, '0'), 2));
                                    $badge = "<span style=\"background:rgba({$r},{$g},{$b},0.15);color:#{$toHex};padding:1px 8px;border-radius:4px;font-size:0.75rem;font-weight:500;display:inline-block\">{$toLabel}</span>";

                                    $causedBy = e($activity->causer?->name ?? 'System');
                                    $date = $activity->created_at->format('d M Y H:i');

                                    return "<div class=\"flex items-start gap-3 py-2 border-b border-gray-100 last:border-0\">
                                        <div class=\"flex-shrink-0 w-2 h-2 mt-1.5 rounded-full bg-primary-500\"></div>
                                        <div class=\"flex-1\">
                                            <div class=\"text-sm\">
                                                <span class=\"text-gray-500 text-xs\">{$fromLabel}</span>
                                                <span class=\"text-gray-400 mx-1\">→</span>
                                                {$badge}
                                            </div>
                                            <div class=\"text-xs text-gray-400 mt-0.5\">{$causedBy} · {$date}</div>
                                        </div>
                                    </div>";
                                })->implode('');

                                return new HtmlString("<div>{$items}</div>");
                            }),
                    ]),
            ]);
    }
}
