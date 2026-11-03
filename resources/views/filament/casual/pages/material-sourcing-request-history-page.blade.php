@php
    use App\Enums\MaterialSourcingRequestStatus;

    $badge = [
        'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        'info' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'success' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'danger' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'gray' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
    ];
    $requests = $this->requests();
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ \App\Filament\Casual\Pages\LauncherPage::getUrl() }}" aria-label="Kembali ke launcher"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                <x-heroicon-o-clock class="h-5 w-5" />
            </div>
            <span class="text-base font-semibold text-white">Riwayat Request Sourcing</span>
        </div>
        <p class="text-blue-200">{{ auth()->user()->branch?->name ?? 'Tanpa Cabang' }}</p>
        <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">
        @if($requests->isEmpty())
            <div class="flex flex-col items-center justify-center px-5 py-16 text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <x-heroicon-o-inbox class="h-8 w-8 text-gray-400" />
                </div>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Belum ada Request Sourcing</p>
                <p class="mt-1 text-xs text-slate-400">Request yang Anda kirim akan muncul di sini.</p>
                <a href="{{ \App\Filament\Casual\Pages\MaterialSourcingRequestPage::getUrl(panel: 'casual') }}" class="mt-4 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">Buat Request</a>
            </div>
        @else
            <div class="flex flex-col gap-3 px-5">
                @foreach($requests as $request)
                    @php $isExpanded = $expandedId === $request->id; @endphp
                    <div wire:key="msr-{{ $request->id }}" class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                        <button type="button" wire:click="toggleItem({{ $request->id }})" aria-expanded="{{ $isExpanded ? 'true' : 'false' }}" class="flex w-full items-start justify-between gap-3 p-4 text-left">
                            <span class="min-w-0">
                                <span class="block font-mono text-xs font-bold text-blue-600 dark:text-blue-400">{{ $request->request_number }}</span>
                                <span class="mt-1 block break-words text-sm font-semibold text-slate-800 dark:text-white">{{ $request->product_name }}</span>
                                <span class="mt-0.5 block text-xs text-slate-400">{{ $request->product_code }} · {{ $request->submitted_at?->locale('id')->isoFormat('D MMM Y HH:mm') }}</span>
                            </span>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $badge[$request->status->getColor()] ?? $badge['gray'] }}">{{ $request->status->getLabel() }}</span>
                        </button>

                        @if(in_array($request->status, [MaterialSourcingRequestStatus::RevisionRequested, MaterialSourcingRequestStatus::Rejected, MaterialSourcingRequestStatus::Cancelled], true) && filled($request->last_decision_reason))
                            <div class="mx-4 mb-3 rounded-xl px-3 py-2 text-xs {{ $request->status === MaterialSourcingRequestStatus::RevisionRequested ? 'bg-amber-50 text-amber-800 dark:bg-amber-950/30 dark:text-amber-300' : 'bg-gray-50 text-slate-600 dark:bg-gray-800 dark:text-slate-300' }}">
                                <p class="font-semibold">{{ $request->status === MaterialSourcingRequestStatus::Cancelled ? 'Alasan pembatalan' : 'Catatan Purchasing' }}</p>
                                <p class="mt-0.5 whitespace-pre-line break-words">{{ $request->last_decision_reason }}</p>
                            </div>
                        @endif

                        @if($request->status === MaterialSourcingRequestStatus::Converted)
                            <div class="mx-4 mb-3 rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300">
                                <p class="font-semibold">Sudah menjadi sourcing</p>
                                <p class="mt-0.5">Referensi sourcing #{{ $request->rnd_product_esb_material_id }} · {{ $request->converted_at?->locale('id')->isoFormat('D MMM Y') }}</p>
                            </div>
                        @endif

                        @if($isExpanded)
                            <div class="space-y-2 border-t border-gray-100 px-4 py-3 text-xs text-slate-600 dark:border-gray-800 dark:text-slate-300">
                                <p><span class="font-semibold">Alasan:</span> <span class="whitespace-pre-line break-words">{{ $request->reason }}</span></p>
                                <p><span class="font-semibold">Estimasi kebutuhan:</span> {{ $request->formattedQuantity() ?? '-' }}</p>
                                <p><span class="font-semibold">Target tanggal:</span> {{ $request->target_date?->locale('id')->isoFormat('D MMM Y') ?? '-' }}</p>
                                @if(filled($request->notes))<p><span class="font-semibold">Catatan:</span> <span class="whitespace-pre-line break-words">{{ $request->notes }}</span></p>@endif
                                @foreach($request->attachmentList() as $index => $attachment)
                                    <a href="{{ route('material-sourcing-request-attachments.show', ['sourcingRequest' => $request->id, 'index' => $index]) }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-blue-600 dark:text-blue-400">
                                        <x-heroicon-o-paper-clip class="h-4 w-4 shrink-0" /><span class="truncate">{{ $attachment['name'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @if($request->status === MaterialSourcingRequestStatus::RevisionRequested)
                            <div class="border-t border-gray-100 p-3 dark:border-gray-800">
                                <a href="{{ \App\Filament\Casual\Pages\MaterialSourcingRequestPage::getUrl(['request' => $request->id], panel: 'casual') }}" class="block w-full rounded-xl bg-blue-600 py-2.5 text-center text-sm font-semibold text-white">Perbaiki & Kirim Ulang</a>
                            </div>
                        @endif

                        @if($request->status->canTransitionTo(MaterialSourcingRequestStatus::Cancelled))
                            <div class="border-t border-gray-100 p-3 dark:border-gray-800">
                                @if($cancellingId === $request->id)
                                    <label for="cancel-{{ $request->id }}" class="mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-300">Alasan pembatalan</label>
                                    <textarea id="cancel-{{ $request->id }}" wire:model="cancelReason" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-slate-200"></textarea>
                                    @error('cancelReason') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                    <div class="mt-2 flex gap-2">
                                        <button type="button" wire:click="stopCancel" class="flex-1 rounded-xl border border-gray-200 py-2.5 text-sm font-semibold text-slate-600 dark:border-gray-700 dark:text-slate-300">Kembali</button>
                                        <button type="button" wire:click="cancelRequest" wire:loading.attr="disabled" wire:target="cancelRequest" class="flex-1 rounded-xl bg-red-600 py-2.5 text-sm font-semibold text-white disabled:opacity-60">Batalkan Request</button>
                                    </div>
                                @else
                                    <button type="button" wire:click="startCancel({{ $request->id }})" class="w-full rounded-xl border border-red-200 py-2.5 text-sm font-semibold text-red-600 dark:border-red-900 dark:text-red-400">Batalkan</button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach

                @if($requests->hasPages())
                    <div class="flex items-center justify-between pt-2">
                        <button type="button" wire:click="previousPage" @disabled($requests->onFirstPage()) class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 disabled:opacity-40 dark:border-gray-700 dark:bg-gray-900 dark:text-slate-300">Sebelumnya</button>
                        <span class="text-xs text-slate-500">Halaman {{ $requests->currentPage() }} dari {{ $requests->lastPage() }}</span>
                        <button type="button" wire:click="nextPage" @disabled(! $requests->hasMorePages()) class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 disabled:opacity-40 dark:border-gray-700 dark:bg-gray-900 dark:text-slate-300">Berikutnya</button>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <x-material-sourcing-request.bottom-nav active="history" />
</div>
