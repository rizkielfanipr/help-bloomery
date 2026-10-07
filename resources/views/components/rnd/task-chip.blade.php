@props(['task'])

{{--
    A clickable calendar entry for one Task. Its colour follows the Task status (RndProjectTaskStatus::getColor(),
    docs/ui-consistency-prd.md §7); deadline conditions only add a left accent strip — red when overdue, amber when
    due within three days. The status text is always available too, so colour is never the only signal.
--}}
@php
    $toneClass = match ($task->status->getColor()) {
        'success' => 'border-emerald-300 bg-emerald-100 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-200',
        'warning' => 'border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-700 dark:bg-amber-950/60 dark:text-amber-200',
        'danger' => 'border-red-300 bg-red-100 text-red-800 dark:border-red-700 dark:bg-red-950/60 dark:text-red-200',
        'gray' => 'border-gray-300 bg-gray-100 text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300',
        default => 'border-blue-300 bg-blue-100 text-blue-800 dark:border-blue-700 dark:bg-blue-950/60 dark:text-blue-200',
    };
    $daysUntilDue = today()->diffInDays($task->due_date, false);
    $deadlineAccent = match (true) {
        $task->isOverdue() => 'border-l-4 border-l-red-600 dark:border-l-red-500',
        ! $task->status->isTerminal() && $daysUntilDue >= 0 && $daysUntilDue <= 3 => 'border-l-4 border-l-amber-500',
        default => null,
    };
@endphp

<button type="button" {{ $attributes->class(['border text-left hover:brightness-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500', $toneClass, $deadlineAccent]) }}>
    @if($task->isOverdue())
        <span class="sr-only">Terlambat: </span>
    @endif
    {{ $slot }}
</button>
