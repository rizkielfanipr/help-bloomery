@php
    $jakarta = fn ($date) => $date ? $date->copy()->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB' : '-';
@endphp
<div class="space-y-4">
    @forelse ($cycles as $cycle)
        <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700" wire:key="cycle-{{ $cycle->id }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Siklus #{{ $cycle->cycle_number }} · {{ $cycle->supplierCandidate?->supplier_name ?? 'Supplier belum dipilih' }}</p>
                <x-helpdesk.monitoring.status-badge :label="$cycle->status->getLabel()" :color="$cycle->status->getColor()" />
            </div>
            <div class="mt-3 grid gap-3 md:grid-cols-3">
                @foreach (['handover' => ['Penyerahan Purchasing', $cycle->handedOverBy?->name, $cycle->handed_over_at, $cycle->quantity ? rtrim(rtrim(number_format((float) $cycle->quantity, 4, '.', ''), '0'), '.').' '.$cycle->uom_name : null, $cycle->handover_notes], 'receipt' => ['Penerimaan R&D', $cycle->receivedBy?->name, $cycle->received_at, $cycle->received_quantity ? rtrim(rtrim(number_format((float) $cycle->received_quantity, 4, '.', ''), '0'), '.').' · '.(\App\Actions\Rnd\Material\ConfirmMaterialSampleReceiptAction::CONDITIONS[$cycle->received_condition] ?? $cycle->received_condition) : null, $cycle->receipt_notes]] as $kind => [$title, $actor, $at, $detail, $notes])
                    <div class="rounded-lg bg-gray-50 p-3 text-xs dark:bg-gray-800/50">
                        <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $title }}</p>
                        <p class="mt-1 text-gray-600 dark:text-gray-300">{{ $actor ?? '-' }} · {{ $jakarta($at) }}</p>
                        @if ($detail)<p class="text-gray-600 dark:text-gray-300">{{ $detail }}</p>@endif
                        @if ($notes)<p class="mt-1 text-gray-500">{{ $notes }}</p>@endif
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($cycle->attachments->where('kind', $kind) as $attachment)
                                <a href="{{ route('helpdesk.rnd-material-sample-attachments.show', $attachment) }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-md border border-gray-200 dark:border-gray-700" title="{{ $attachment->original_name }}">
                                    <img src="{{ route('helpdesk.rnd-material-sample-attachments.show', $attachment) }}" alt="{{ $title }} {{ $attachment->original_name }}" class="h-16 w-16 object-cover" loading="lazy">
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <div class="rounded-lg bg-gray-50 p-3 text-xs dark:bg-gray-800/50">
                    <p class="font-semibold text-gray-800 dark:text-gray-200">Evaluasi R&D</p>
                    <p class="mt-1 text-gray-600 dark:text-gray-300">{{ $cycle->evaluatedBy?->name ?? '-' }} · {{ $jakarta($cycle->evaluated_at) }}</p>
                    @if ($cycle->evaluation_reason)<p class="mt-1 text-gray-500">{{ $cycle->evaluation_reason }}</p>@endif
                </div>
            </div>
        </section>
    @empty
        <p class="text-sm text-gray-500">Belum ada siklus sampel.</p>
    @endforelse
</div>
