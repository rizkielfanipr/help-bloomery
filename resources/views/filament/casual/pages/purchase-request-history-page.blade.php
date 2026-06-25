@php
    use App\Enums\PurchaseRequestStatus;
    use App\Enums\PurchaseType;

    $statusColors = [
        'submitted'  => 'bg-gray-100 text-gray-600',
        'in_process' => 'bg-amber-100 text-amber-700',
        'purchased'  => 'bg-blue-100 text-blue-700',
        'delivered'  => 'bg-violet-100 text-violet-700',
        'completed'  => 'bg-green-100 text-green-700',
    ];
@endphp

<div class="flex flex-col bg-violet-600 dark:bg-violet-900"
     style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         VIOLET HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('filament.casual.pages.launcher-page') }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Riwayat Pengajuan</span>
        </div>

        <p class="text-violet-200">{{ auth()->user()->branch?->name ?? 'Tanpa Cabang' }}</p>
        <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        @php $requests = $this->requests(); @endphp

        @if($requests->isEmpty())
            <div class="flex flex-col items-center justify-center px-5 py-16 text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Belum ada pengajuan</p>
                <p class="mt-1 text-xs text-slate-400">Pengajuan pembelian barang Anda akan muncul di sini</p>
                <a href="{{ route('filament.casual.pages.purchase-request-page') }}"
                   class="mt-4 rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white">
                    Buat Pengajuan
                </a>
            </div>
        @else
            <div class="flex flex-col gap-3 px-5">
                @foreach($requests as $request)
                    @php
                        $statusValue = $request->status instanceof PurchaseRequestStatus
                            ? $request->status->value
                            : $request->status;
                        $statusLabel = $request->status instanceof PurchaseRequestStatus
                            ? $request->status->getLabel()
                            : $statusValue;
                        $statusColor = $statusColors[$statusValue] ?? 'bg-gray-100 text-gray-600';
                        $isExpanded = $expandedId === $request->id;
                        $isDelivered = $statusValue === 'delivered';
                        $typeLabel = $request->purchase_type instanceof PurchaseType
                            ? $request->purchase_type->getLabel()
                            : $request->purchase_type;
                    @endphp

                    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">

                        {{-- Card header (always visible) --}}
                        <button wire:click="toggleItem({{ $request->id }})" type="button"
                                class="flex w-full items-start gap-3 p-4 text-left">

                            {{-- Status dot --}}
                            <div class="mt-0.5 flex-shrink-0">
                                @if($statusValue === 'completed')
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100">
                                        <svg class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                        </svg>
                                    </div>
                                @elseif($statusValue === 'delivered')
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-100">
                                        <svg class="h-4 w-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                                        </svg>
                                    </div>
                                @elseif($statusValue === 'purchased')
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100">
                                        <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                                        </svg>
                                    </div>
                                @elseif($statusValue === 'in_process')
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100">
                                        <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                        </svg>
                                    </div>
                                @else
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100">
                                        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/>
                                        </svg>
                                    </div>
                                @endif
                            </div>

                            {{-- Main info --}}
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $request->item_name }}</p>
                                    <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusColor }}">
                                        {{ $statusLabel }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $request->division }} • Qty: {{ $request->quantity }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-400">
                                    {{ $request->created_at->locale('id')->isoFormat('D MMM Y, HH:mm') }}
                                </p>
                            </div>

                            {{-- Chevron --}}
                            <svg class="h-4 w-4 flex-shrink-0 text-gray-400 transition-transform {{ $isExpanded ? 'rotate-180' : '' }}"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                            </svg>
                        </button>

                        {{-- Expanded detail --}}
                        @if($isExpanded)
                            <div class="border-t border-gray-100 dark:border-gray-800">

                                {{-- Details grid --}}
                                <div class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800">

                                    <div class="bg-white px-4 py-3 dark:bg-gray-900">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Jenis</p>
                                        <p class="mt-0.5 text-xs font-medium text-slate-700 dark:text-slate-200">{{ $typeLabel }}</p>
                                    </div>

                                    <div class="bg-white px-4 py-3 dark:bg-gray-900">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Jumlah</p>
                                        <p class="mt-0.5 text-xs font-medium text-slate-700 dark:text-slate-200">{{ $request->quantity }} pcs</p>
                                    </div>

                                    @if($request->journal_item_number)
                                        <div class="bg-white px-4 py-3 dark:bg-gray-900">
                                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">No. Item Journal</p>
                                            <p class="mt-0.5 text-xs font-medium text-slate-700 dark:text-slate-200">{{ $request->journal_item_number }}</p>
                                        </div>
                                    @endif

                                    @if($request->purchase_request_number)
                                        <div class="bg-white px-4 py-3 dark:bg-gray-900">
                                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">No. PR</p>
                                            <p class="mt-0.5 text-xs font-medium text-slate-700 dark:text-slate-200">{{ $request->purchase_request_number }}</p>
                                        </div>
                                    @endif

                                </div>

                                {{-- Reason --}}
                                <div class="bg-white px-4 py-3 dark:bg-gray-900">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Alasan Pembelian</p>
                                    <p class="mt-1 text-xs text-slate-700 dark:text-slate-300">{{ $request->purchase_reason }}</p>
                                </div>

                                {{-- E-Commerce link --}}
                                @if($request->ecommerce_link)
                                    <div class="border-t border-gray-100 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Link e-Commerce</p>
                                        <a href="{{ $request->ecommerce_link }}" target="_blank"
                                           class="mt-0.5 block truncate text-xs font-medium text-violet-600 underline">
                                            {{ $request->ecommerce_link }}
                                        </a>
                                    </div>
                                @endif

                                {{-- Attachments --}}
                                @if($request->attachment_paths && count($request->attachment_paths) > 0)
                                    <div class="border-t border-gray-100 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                                        <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Lampiran</p>
                                        <div class="grid grid-cols-3 gap-2">
                                            @foreach($request->attachment_paths as $path)
                                                <a href="{{ \Illuminate\Support\Facades\Storage::url($path) }}" target="_blank"
                                                   class="aspect-square overflow-hidden rounded-lg ring-1 ring-gray-200">
                                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($path) }}"
                                                         class="h-full w-full object-cover">
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Admin notes --}}
                                @if($request->admin_notes)
                                    <div class="border-t border-gray-100 bg-amber-50 px-4 py-3 dark:border-gray-800 dark:bg-amber-900/20">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-600">Catatan Admin</p>
                                        <p class="mt-1 text-xs text-amber-800 dark:text-amber-300">{{ $request->admin_notes }}</p>
                                    </div>
                                @endif

                                {{-- Status timeline --}}
                                <div class="border-t border-gray-100 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                                    <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Progress</p>
                                    @php
                                        $steps = [
                                            ['value' => 'submitted',  'label' => 'Diajukan'],
                                            ['value' => 'in_process', 'label' => 'Diproses'],
                                            ['value' => 'purchased',  'label' => 'Dibeli'],
                                            ['value' => 'delivered',  'label' => 'Dikirim'],
                                            ['value' => 'completed',  'label' => 'Selesai'],
                                        ];
                                        $stepValues = array_column($steps, 'value');
                                        $currentIndex = array_search($statusValue, $stepValues);
                                    @endphp
                                    <div class="flex items-center gap-0">
                                        @foreach($steps as $i => $step)
                                            @php
                                                $isDone = $i <= $currentIndex;
                                                $isCurrent = $i === $currentIndex;
                                            @endphp
                                            <div class="flex flex-1 flex-col items-center">
                                                <div class="h-2 w-2 rounded-full {{ $isDone ? 'bg-violet-600' : 'bg-gray-200' }} {{ $isCurrent ? 'ring-2 ring-violet-200 ring-offset-1' : '' }}"></div>
                                                <p class="mt-1 text-center text-[9px] leading-tight {{ $isDone ? 'font-semibold text-violet-700' : 'text-slate-400' }}">{{ $step['label'] }}</p>
                                            </div>
                                            @if(!$loop->last)
                                                <div class="mb-3 h-0.5 flex-1 {{ $i < $currentIndex ? 'bg-violet-600' : 'bg-gray-200' }}"></div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Confirm received button (only when delivered) --}}
                                @if($isDelivered)
                                    <div class="border-t border-gray-100 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                                        <button wire:click="confirmReceived({{ $request->id }})"
                                                wire:loading.attr="disabled"
                                                wire:confirm="Konfirmasi bahwa barang sudah diterima?"
                                                type="button"
                                                class="w-full rounded-xl bg-green-600 py-3 text-sm font-semibold text-white shadow-sm transition active:scale-95 disabled:opacity-60">
                                            <span wire:loading.remove wire:target="confirmReceived({{ $request->id }})">
                                                Konfirmasi Diterima
                                            </span>
                                            <span wire:loading wire:target="confirmReceived({{ $request->id }})">
                                                Mengkonfirmasi...
                                            </span>
                                        </button>
                                    </div>
                                @endif

                            </div>
                        @endif

                    </div>
                @endforeach
            </div>
        @endif

    </div>

    {{-- ════════════════════════════════════════════
         BOTTOM NAV
    ════════════════════════════════════════════ --}}
    <x-purchase-request.bottom-nav active="history" />

</div>
