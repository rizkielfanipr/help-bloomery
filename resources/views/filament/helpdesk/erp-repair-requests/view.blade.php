<x-filament-panels::page>
@php
    $status = $record->status;
    $fieldClass = 'w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white';
    $labelClass = 'mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $priorityColor = match($record->priority) {
        'critical' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
        'high' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        'low' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
        default => 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
    };
@endphp

<div class="mx-auto w-full max-w-[1600px] space-y-6">
    <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-blue-700 via-blue-600 to-indigo-600 p-6 text-white shadow-xl shadow-blue-900/15">
        <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-center">
            <div>
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span class="rounded-lg bg-white/10 px-3 py-1 font-mono text-sm font-bold ring-1 ring-white/15">{{ $record->ticket_number }}</span>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $status->getColor() === 'success' ? 'bg-emerald-400/20 text-emerald-200' : ($status->getColor() === 'danger' ? 'bg-red-400/20 text-red-200' : 'bg-blue-400/20 text-blue-200') }}">
                        {{ $status->getLabel() }}
                    </span>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $priorityColor }}">{{ ucfirst($record->priority) }}</span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight lg:text-3xl">{{ $record->module?->name ?? 'IT Request' }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-slate-300">{{ $record->requestType?->name ?? 'Request' }} · {{ $record->branch?->name ?? 'No Branch' }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3 text-sm lg:min-w-[380px]">
                <div class="rounded-xl bg-white/5 p-3 ring-1 ring-white/10">
                    <p class="text-xs text-slate-400">Submitted</p>
                    <p class="mt-1 font-semibold">{{ $record->created_at->format('d M Y, H:i') }}</p>
                </div>
                <div class="rounded-xl bg-white/5 p-3 ring-1 ring-white/10">
                    <p class="text-xs text-slate-400">Due Date</p>
                    <p class="mt-1 font-semibold">{{ $record->due_at?->format('d M Y, H:i') ?? 'Not set' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid items-start gap-6 xl:grid-cols-12">
        <div class="space-y-6 xl:col-span-7">
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Request Detail</h2>
                    <span class="text-xs text-gray-400">Created by {{ $record->requester?->name }}</span>
                </div>
                <dl class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-xs text-gray-400">Requester</dt><dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $record->requester?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-400">Branch</dt><dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $record->branch?->name ?? '-' }}</dd></div>
                    <div><dt class="text-xs text-gray-400">Request Type</dt><dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $record->requestType?->name ?? '-' }}</dd></div>
                    <div><dt class="text-xs text-gray-400">ERP Module</dt><dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $record->module?->name ?? '-' }}</dd></div>
                    <div><dt class="text-xs text-gray-400">Classification</dt><dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $record->work_classification === 'major_project' ? 'Major Project' : ($record->work_classification === 'standard' ? 'Standard' : 'Not classified') }}</dd></div>
                    <div><dt class="text-xs text-gray-400">Assignee</dt><dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $record->assignee?->name ?? 'Unassigned' }}</dd></div>
                </dl>
                <div class="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Description</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $record->keterangan }}</p>
                </div>
            </section>

            @if(! empty($record->attachments))
                <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                    <h2 class="mb-4 text-base font-bold text-gray-900 dark:text-white">Attachments</h2>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($record->attachments as $path)
                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}" target="_blank"
                               class="group flex items-center gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-primary-400 hover:bg-primary-50 dark:border-gray-700 dark:hover:bg-primary-500/10">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-500/15">↗</div>
                                <span class="min-w-0 truncate text-sm font-medium text-gray-700 group-hover:text-primary-700 dark:text-gray-300">{{ basename($path) }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if($record->it_notes || $record->escalation_reason || $record->resolution_note)
                <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                    <h2 class="mb-4 text-base font-bold text-gray-900 dark:text-white">IT Outcome</h2>
                    <div class="grid gap-4 lg:grid-cols-3">
                        @if($record->it_notes)
                            <div class="rounded-xl bg-blue-50 p-4 dark:bg-blue-950/30">
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">IT Notes</p>
                                <p class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $record->it_notes }}</p>
                            </div>
                        @endif
                        @if($record->escalation_reason)
                            <div class="rounded-xl bg-amber-50 p-4 dark:bg-amber-950/30">
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Escalated to {{ str($record->escalation_target)->replace('_', ' ')->title() }}</p>
                                <p class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $record->escalation_reason }}</p>
                            </div>
                        @endif
                        @if($record->resolution_note)
                            <div class="rounded-xl bg-emerald-50 p-4 dark:bg-emerald-950/30">
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Resolution</p>
                                <p class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $record->resolution_note }}</p>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                <h2 class="mb-5 text-base font-bold text-gray-900 dark:text-white">Activity Timeline</h2>
                <div class="relative space-y-5 before:absolute before:bottom-2 before:left-[7px] before:top-2 before:w-px before:bg-gray-200 dark:before:bg-gray-700">
                    @forelse($record->activities as $activity)
                        <div class="relative flex gap-4">
                            <span class="relative z-10 mt-1 h-[15px] w-[15px] shrink-0 rounded-full border-4 border-white bg-blue-500 ring-1 ring-blue-200 dark:border-gray-900"></span>
                            <div class="min-w-0 flex-1 rounded-xl bg-gray-50 p-4 dark:bg-gray-800/60">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ ucwords(str_replace('_', ' ', $activity->action)) }}</p>
                                    <time class="text-xs text-gray-400">{{ $activity->created_at->format('d M Y, H:i') }}</time>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">{{ $activity->actor?->name ?? 'System' }}
                                    @if($activity->from_status || $activity->to_status)
                                        · {{ $activity->from_status ?: 'New' }} → {{ $activity->to_status ?: '-' }}
                                    @endif
                                </p>
                                @if($activity->notes)<p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $activity->notes }}</p>@endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No activity recorded.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-6 xl:sticky xl:top-6 xl:col-span-5">
            @if($this->canFollowUp())
                <section class="rounded-2xl bg-white shadow-lg ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                    <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                        <h2 class="font-bold text-gray-900 dark:text-white">IT Follow-up</h2>
                        <p class="mt-1 text-xs text-gray-400">Triage, assign, and update ticket progress.</p>
                    </div>
                    <div class="grid gap-4 p-6 sm:grid-cols-2">
                        <div>
                            <label class="{{ $labelClass }}">Status</label>
                            <select wire:model="status" class="{{ $fieldClass }}">
                                @foreach([\App\Enums\ItRequestStatus::Submitted, \App\Enums\ItRequestStatus::Review, \App\Enums\ItRequestStatus::Progress, \App\Enums\ItRequestStatus::Waiting] as $option)
                                    <option value="{{ $option->value }}">{{ $option->getLabel() }}</option>
                                @endforeach
                            </select>
                            @error('status')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">Assignee</label>
                            <select wire:model="assigneeId" class="{{ $fieldClass }}">
                                <option value="">Select PIC</option>
                                @foreach($this->assigneeOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                            </select>
                            @error('assigneeId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">Classification</label>
                            <select wire:model="classification" class="{{ $fieldClass }}">
                                <option value="">Select class</option>
                                <option value="standard">Standard</option>
                                <option value="major_project">Major Project</option>
                            </select>
                            @error('classification')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">Priority</label>
                            <select wire:model="priority" class="{{ $fieldClass }}">
                                @foreach(['low', 'medium', 'high', 'critical'] as $value)<option value="{{ $value }}">{{ ucfirst($value) }}</option>@endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $labelClass }}">Due Date</label>
                            <input type="datetime-local" wire:model="dueAt" class="{{ $fieldClass }}">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $labelClass }}">IT Notes</label>
                            <textarea wire:model="itNotes" rows="4" class="{{ $fieldClass }}" placeholder="Add investigation notes, progress, or information needed from user..."></textarea>
                        </div>
                        <button wire:click="saveFollowUp" wire:loading.attr="disabled" class="sm:col-span-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50">
                            Save Follow-up
                        </button>
                    </div>
                </section>

                <div class="grid gap-6 lg:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                    <section class="rounded-2xl border border-amber-200 bg-amber-50/70 p-5 dark:border-amber-800 dark:bg-amber-950/20">
                        <h3 class="font-bold text-amber-900 dark:text-amber-200">Escalation</h3>
                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">Forward work that cannot be resolved internally.</p>
                        <select wire:model="escalationTarget" class="{{ $fieldClass }} mt-4">
                            <option value="">Select target</option>
                            <option value="it_level_2">IT Level 2</option>
                            <option value="developer">Developer</option>
                            <option value="vendor">Vendor</option>
                            <option value="other">Other</option>
                        </select>
                        @error('escalationTarget')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        <textarea wire:model="escalationReason" rows="3" class="{{ $fieldClass }} mt-3" placeholder="Escalation reason..."></textarea>
                        @error('escalationReason')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        <button wire:click="escalate" wire:loading.attr="disabled" class="mt-3 w-full rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">Escalate Ticket</button>
                    </section>

                    <section class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5 dark:border-emerald-800 dark:bg-emerald-950/20">
                        <h3 class="font-bold text-emerald-900 dark:text-emerald-200">Resolution</h3>
                        <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">Document the solution before closing.</p>
                        <textarea wire:model="resolutionNote" rows="5" class="{{ $fieldClass }} mt-4" placeholder="Describe the resolution..."></textarea>
                        @error('resolutionNote')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        <button wire:click="complete" wire:loading.attr="disabled" class="mt-3 w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Complete Ticket</button>
                    </section>
                </div>
            @endif
        </aside>
    </div>
</div>
</x-filament-panels::page>
