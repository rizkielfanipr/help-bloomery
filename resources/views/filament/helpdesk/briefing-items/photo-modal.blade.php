@php
    $photoCount = count($record->photo_paths ?? []);
    $photoColumns = match (true) {
        $photoCount === 1 => 1,
        $photoCount <= 4 => 2,
        default => 3,
    };
    $photoHeight = match (true) {
        $photoCount === 1 => 'min(380px, 40dvh)',
        $photoCount === 2 => 'min(340px, 36dvh)',
        $photoCount <= 4 => 'min(240px, 24dvh)',
        default => 'min(190px, 19dvh)',
    };
@endphp

<div class="space-y-4 p-1">
    {{-- Review status --}}
    @if ($record->review_status)
        <div class="flex items-center gap-2">
            <span @class([
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                'bg-green-100 text-green-800' => $record->review_status->value === 'approved',
                'bg-red-100 text-red-800'     => $record->review_status->value === 'rejected',
                'bg-yellow-100 text-yellow-800' => $record->review_status->value === 'pending',
            ])>
                {{ $record->review_status->getLabel() }}
            </span>
            @if ($record->reviewer)
                <span class="text-xs text-gray-400">oleh {{ $record->reviewer->name }}</span>
            @endif
        </div>
    @endif

    {{-- Rejection reason --}}
    @if ($record->rejection_reason)
        <div class="rounded-lg border border-red-200 bg-red-50 p-3">
            <p class="text-xs font-medium text-red-700">Alasan Penolakan</p>
            <p class="mt-0.5 text-sm text-red-900">{{ $record->rejection_reason }}</p>
        </div>
    @endif

    {{-- Notes --}}
    @if ($record->notes)
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Catatan</p>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->notes }}</p>
        </div>
    @endif

    {{-- Photos --}}
    @if ($record->photo_paths)
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Foto</p>
            <div class="grid gap-3"
                 style="grid-template-columns: repeat({{ $photoColumns }}, minmax(0, 1fr));">
                @foreach ($record->photo_paths as $index => $path)
                    <div class="flex items-center justify-center overflow-hidden rounded-xl bg-black"
                         style="height: {{ $photoHeight }};{{ $photoCount === 1 ? ' max-width: 720px; width: 100%; margin-inline: auto;' : '' }}">
                        <img
                            src="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}"
                            alt="Foto bukti {{ $index + 1 }}"
                            class="h-full w-full object-contain"
                        >
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Approve / Reject actions --}}
    @if ($record->review_status !== \App\Enums\BriefingReviewStatus::Approved)
        <div class="flex items-center gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
            {{ $action->getModalAction('approve') }}
            {{ $action->getModalAction('reject') }}
        </div>
    @endif
</div>
