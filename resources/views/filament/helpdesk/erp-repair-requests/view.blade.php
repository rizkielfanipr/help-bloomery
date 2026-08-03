<x-filament-panels::page>
@php
    $status = $record->status;
    $nextStatuses = $this->nextStatusOptions();
    $fieldClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 dark:border-gray-700 dark:bg-gray-900 dark:text-white';
    $labelClass = 'mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $timelineSteps = [
        [\App\Enums\ItRequestStatus::Submitted, 'heroicon-o-document-text'],
        [\App\Enums\ItRequestStatus::Review, 'heroicon-o-magnifying-glass'],
        [\App\Enums\ItRequestStatus::Progress, 'heroicon-o-wrench-screwdriver'],
        [\App\Enums\ItRequestStatus::Completed, 'heroicon-o-check-badge'],
    ];
    $statusOrder = [
        \App\Enums\ItRequestStatus::Submitted->value => 0,
        \App\Enums\ItRequestStatus::Review->value => 1,
        \App\Enums\ItRequestStatus::Progress->value => 2,
        \App\Enums\ItRequestStatus::Waiting->value => 2,
        \App\Enums\ItRequestStatus::Escalated->value => 2,
        \App\Enums\ItRequestStatus::Completed->value => 3,
    ];
    $currentOrder = $statusOrder[$status->value] ?? -1;
@endphp

<div class="mx-auto w-full max-w-6xl space-y-5">
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-700 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="font-mono text-sm font-semibold text-blue-600 dark:text-blue-400">{{ $record->ticket_number }}</p>
                <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $record->module?->name ?? 'IT Request' }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $record->requestType?->name ?? 'Request' }} · {{ $record->branch?->name ?? 'No Branch' }}</p>
            </div>
            <div class="flex shrink-0 flex-col items-start gap-1 sm:items-end">
                <span @class([
                    'rounded-md border px-2.5 py-1 text-xs font-semibold',
                    'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300' => $status === \App\Enums\ItRequestStatus::Submitted,
                    'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300' => in_array($status, [\App\Enums\ItRequestStatus::Review, \App\Enums\ItRequestStatus::Waiting], true),
                    'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300' => $status === \App\Enums\ItRequestStatus::Progress,
                    'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300' => in_array($status, [\App\Enums\ItRequestStatus::Escalated, \App\Enums\ItRequestStatus::Cancelled], true),
                    'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300' => $status === \App\Enums\ItRequestStatus::Completed,
                ])>{{ $status->getLabel() }}</span>
                <span class="text-xs text-gray-400">{{ $record->created_at->format('d M Y, H:i') }}</span>
            </div>
        </div>

        <div class="grid gap-x-8 gap-y-5 px-6 py-5 sm:grid-cols-2 lg:grid-cols-4">
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Requester</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->requester?->name ?? '-' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Branch</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->branch?->name ?? '-' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Request Type</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->requestType?->name ?? '-' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">ERP Module</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->module?->name ?? '-' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Classification</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->work_classification === 'major_project' ? 'Major Project' : ($record->work_classification === 'standard' ? 'Standard' : 'Not classified') }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Priority</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ ucfirst($record->priority) }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Assignee</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->assignee?->name ?? 'Unassigned' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Due Date</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->due_at?->format('d M Y, H:i') ?? 'Not set' }}</p></div>
        </div>

        <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Description</p>
            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $record->keterangan }}</p>
        </div>

        @if($record->it_notes || $record->escalation_reason || $record->resolution_note)
            <div class="grid border-t border-gray-200 dark:border-gray-700 md:grid-cols-3">
                @if($record->it_notes)<div class="px-6 py-5"><p class="text-xs font-medium uppercase tracking-wide text-gray-400">IT Notes</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $record->it_notes }}</p></div>@endif
                @if($record->escalation_reason)<div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700 md:border-l md:border-t-0"><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Escalation · {{ str($record->escalation_target)->replace('_', ' ')->title() }}</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $record->escalation_reason }}</p></div>@endif
                @if($record->resolution_note)<div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700 md:border-l md:border-t-0"><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Resolution</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $record->resolution_note }}</p></div>@endif
            </div>
        @endif

        @if($this->canFollowUp())
            <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">IT Follow-up</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div><label class="{{ $labelClass }}">Assignee</label><select wire:model="assigneeId" class="{{ $fieldClass }}"><option value="">Select PIC</option>@foreach($this->assigneeOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>@error('assigneeId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror</div>
                    <div><label class="{{ $labelClass }}">Classification</label><select wire:model="classification" class="{{ $fieldClass }}"><option value="">Select class</option><option value="standard">Standard</option><option value="major_project">Major Project</option></select>@error('classification')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror</div>
                    <div><label class="{{ $labelClass }}">Priority</label><select wire:model="priority" class="{{ $fieldClass }}">@foreach(['low', 'medium', 'high', 'critical'] as $value)<option value="{{ $value }}">{{ ucfirst($value) }}</option>@endforeach</select></div>
                    <div><label class="{{ $labelClass }}">Due Date</label><input type="datetime-local" wire:model="dueAt" class="{{ $fieldClass }}"></div>
                    <div class="sm:col-span-2 lg:col-span-4"><label class="{{ $labelClass }}">IT Notes</label><textarea wire:model="itNotes" rows="3" class="{{ $fieldClass }}" placeholder="Add investigation notes or progress..."></textarea></div>
                </div>
                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    <button wire:click="saveFollowUp" wire:loading.attr="disabled" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">Save Notes</button>
                    @foreach($nextStatuses as $value => $label)
                        <button wire:click="transitionTo('{{ $value }}')" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-600 hover:bg-blue-50 disabled:opacity-50 dark:border-blue-900 dark:bg-gray-900"><x-heroicon-o-arrow-right class="h-4 w-4" />{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            @if($status === \App\Enums\ItRequestStatus::Progress)
                <div class="grid border-t border-gray-200 dark:border-gray-700 lg:grid-cols-2">
                    <div class="px-6 py-5 lg:border-r lg:border-gray-200 dark:lg:border-gray-700"><label class="{{ $labelClass }}">Escalation Target</label><select wire:model="escalationTarget" class="{{ $fieldClass }}"><option value="">Select target</option><option value="it_level_2">IT Level 2</option><option value="developer">Developer</option><option value="vendor">Vendor</option><option value="other">Other</option></select><textarea wire:model="escalationReason" rows="3" class="{{ $fieldClass }} mt-3" placeholder="Escalation reason..."></textarea>@error('escalationReason')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror<div class="mt-3 flex justify-end"><button wire:click="escalate" class="rounded-lg border border-amber-200 bg-white px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50 dark:border-amber-900 dark:bg-gray-900">Escalate</button></div></div>
                    <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700 lg:border-t-0"><label class="{{ $labelClass }}">Resolution Note</label><textarea wire:model="resolutionNote" rows="3" class="{{ $fieldClass }}" placeholder="Describe the resolution..."></textarea>@error('resolutionNote')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror<div class="mt-3 flex justify-end"><button wire:click="complete" class="rounded-lg border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900 dark:bg-gray-900">Complete</button></div></div>
                </div>
            @endif
        @endif

        <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
            <div class="overflow-x-auto pb-1">
                <div class="flex min-w-[760px] items-start">
                    @foreach($timelineSteps as [$stepStatus, $icon])
                        @php
                            $isReached = $currentOrder >= ($statusOrder[$stepStatus->value] ?? 99);
                            $activity = $record->activities->first(fn ($item) => $item->to_status === $stepStatus->value);
                        @endphp
                        <div class="flex min-w-0 flex-1 items-start">
                            <div class="w-full text-center">
                                <div @class(['mx-auto flex h-9 w-9 items-center justify-center rounded-full border', 'border-blue-200 bg-blue-50 text-blue-600 dark:border-blue-900 dark:bg-blue-950/30' => $isReached && $stepStatus !== \App\Enums\ItRequestStatus::Completed, 'border-emerald-200 bg-emerald-50 text-emerald-600 dark:border-emerald-900 dark:bg-emerald-950/30' => $isReached && $stepStatus === \App\Enums\ItRequestStatus::Completed, 'border-gray-200 bg-gray-50 text-gray-400 dark:border-gray-700 dark:bg-gray-800' => ! $isReached])><x-dynamic-component :component="$icon" class="h-4 w-4" /></div>
                                <p @class(['mt-2 text-xs font-semibold', 'text-gray-800 dark:text-gray-200' => $isReached, 'text-gray-400' => ! $isReached])>{{ $stepStatus->getLabel() }}</p>
                                @if($activity)<p class="mt-1 text-[11px] text-gray-500">{{ $activity->created_at->format('d M Y, H:i') }}</p><p class="text-[11px] text-gray-400">oleh {{ $activity->actor?->name ?? 'System' }}</p>@else<p class="mt-1 text-[11px] text-gray-400">{{ $isReached && $stepStatus === \App\Enums\ItRequestStatus::Submitted ? $record->created_at->format('d M Y, H:i') : 'Belum diproses' }}</p>@endif
                            </div>
                            @if(! $loop->last)<div @class(['mt-4 h-px w-10 shrink-0', 'bg-blue-300 dark:bg-blue-800' => $currentOrder > $loop->index, 'bg-gray-200 dark:bg-gray-700' => $currentOrder <= $loop->index])></div>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    @if(! empty($record->attachments))
        <section class="rounded-xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-700 dark:bg-gray-900"><h2 class="text-sm font-semibold text-gray-900 dark:text-white">Attachments</h2><div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@foreach($record->attachments as $path)<a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}" target="_blank" class="rounded-lg border border-gray-200 p-3 text-sm font-semibold text-gray-700 hover:border-blue-300 hover:text-blue-600 dark:border-gray-700 dark:text-gray-300">↗ {{ basename($path) }}</a>@endforeach</div></section>
    @endif

    @if($record->activities->isNotEmpty())
        <section class="rounded-xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-700 dark:bg-gray-900"><h2 class="text-sm font-semibold text-gray-900 dark:text-white">Activity History</h2><div class="mt-4 divide-y divide-gray-100 dark:divide-gray-800">@foreach($record->activities as $activity)<div class="py-3 first:pt-0 last:pb-0"><div class="flex flex-wrap items-center justify-between gap-2"><p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ ucwords(str_replace('_', ' ', $activity->action)) }}</p><time class="text-xs text-gray-400">{{ $activity->created_at->format('d M Y, H:i') }}</time></div><p class="mt-1 text-xs text-gray-500">{{ $activity->actor?->name ?? 'System' }}@if($activity->from_status || $activity->to_status) · {{ $activity->from_status ?: 'New' }} → {{ $activity->to_status ?: '-' }}@endif</p>@if($activity->notes)<p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $activity->notes }}</p>@endif</div>@endforeach</div></section>
    @endif
</div>
</x-filament-panels::page>
