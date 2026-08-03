@php
    use App\Enums\ServiceRequestStatus;
    use Illuminate\Support\Facades\Storage;

    $statusConfig = [
        'submitted'    => ['label' => 'Submitted',    'bg' => 'bg-blue-100',   'text' => 'text-blue-700'],
        'in_progress'  => ['label' => 'In Progress',  'bg' => 'bg-amber-100',  'text' => 'text-amber-700'],
        'warranty'     => ['label' => 'Warranty',     'bg' => 'bg-purple-100', 'text' => 'text-purple-700'],
        're_submitted' => ['label' => 'Re-submitted', 'bg' => 'bg-red-100',    'text' => 'text-red-700'],
        'completed'    => ['label' => 'Completed',    'bg' => 'bg-blue-100',   'text' => 'text-blue-700'],
    ];
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900"
     style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         HEADER
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
            <span class="text-base font-semibold text-white">Riwayat Request</span>
        </div>

        <p class="text-blue-200">{{ auth()->user()->branch?->name ?? auth()->user()->name }}</p>
        <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        @php $requests = $this->requests(); @endphp

        @if($requests->isEmpty())
            <div class="flex flex-col items-center justify-center px-5 py-16 text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-900/20">
                    <svg class="h-8 w-8 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z"/>
                    </svg>
                </div>
                <p class="font-semibold text-gray-900 dark:text-white">Belum Ada Request</p>
                <p class="mt-1 text-sm text-gray-400">Permintaan teknisi yang Anda ajukan akan muncul di sini.</p>
                <a href="{{ \App\Filament\Casual\Pages\TechnicianRequestPage::getUrl() }}"
                   class="mt-5 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                    Buat Request Baru
                </a>
            </div>
        @else
            <div class="flex flex-col gap-3 px-5">
                @foreach($requests as $request)
                    @php
                        $statusValue = $request->status instanceof ServiceRequestStatus
                            ? $request->status->value
                            : $request->status;
                        $cfg = $statusConfig[$statusValue] ?? ['label' => $statusValue, 'bg' => 'bg-gray-100', 'text' => 'text-gray-600'];
                        $isExpanded = $expandedId === $request->id;
                        $lastRepair = $request->repairs->sortByDesc('completed_at')->first();
                    @endphp

                    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">

                        {{-- Card header --}}
                        <button wire:click="toggleItem({{ $request->id }})" type="button"
                                class="flex w-full items-start gap-3 p-4 text-left">

                            {{-- Status icon --}}
                            <div class="mt-0.5 flex-shrink-0">
                                @if($statusValue === 'completed')
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20">
                                        <svg class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                        </svg>
                                    </div>
                                @elseif($statusValue === 'in_progress')
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-900/20">
                                        <svg class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z"/>
                                        </svg>
                                    </div>
                                @elseif($statusValue === 'warranty')
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-900/20">
                                        <svg class="h-5 w-5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                                        </svg>
                                    </div>
                                @elseif($statusValue === 're_submitted')
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-50 dark:bg-red-900/20">
                                        <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                                        </svg>
                                    </div>
                                @else
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20">
                                        <svg class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                                        </svg>
                                    </div>
                                @endif
                            </div>

                            {{-- Info --}}
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                        SR-{{ str_pad($request->id, 4, '0', STR_PAD_LEFT) }}
                                    </p>
                                    <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $cfg['bg'] }} {{ $cfg['text'] }}">
                                        {{ $cfg['label'] }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    Jadwal: {{ $request->scheduled_date->format('d M Y') }}
                                </p>
                                @if($request->technician)
                                    <p class="mt-0.5 text-xs text-slate-400">
                                        Teknisi: {{ $request->technician->name }}
                                    </p>
                                @else
                                    <p class="mt-0.5 text-xs text-slate-400">Menunggu teknisi...</p>
                                @endif
                            </div>

                            {{-- Chevron --}}
                            <svg class="h-4 w-4 flex-shrink-0 text-gray-400 transition-transform {{ $isExpanded ? 'rotate-180' : '' }}"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                            </svg>
                        </button>

                        {{-- Expanded detail --}}
                        @if($isExpanded)
                            @php $repairs = $request->repairs->sortBy('cycle'); @endphp
                            <div class="border-t border-gray-100 dark:border-gray-800">

                                {{-- ── PERMINTAAN AWAL ── --}}
                                <div class="bg-gray-50 px-4 py-3 dark:bg-gray-800/40">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Permintaan Awal</p>
                                </div>
                                <div class="bg-white px-4 py-3 dark:bg-gray-900">
                                    @if($request->requestor_notes)
                                        <p class="text-xs leading-relaxed text-slate-700 dark:text-slate-300">{{ $request->requestor_notes }}</p>
                                    @endif
                                    @if($request->attachments && count($request->attachments) > 0)
                                        <div class="mt-2 grid grid-cols-3 gap-2">
                                            @foreach($request->attachments as $path)
                                                <a href="{{ Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}" target="_blank"
                                                   class="aspect-square overflow-hidden rounded-lg ring-1 ring-gray-200">
                                                    <img src="{{ Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}" class="h-full w-full object-cover">
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if(!$request->requestor_notes && (!$request->attachments || count($request->attachments) === 0))
                                        <p class="text-xs text-slate-400">Tidak ada catatan tambahan.</p>
                                    @endif
                                </div>

                                {{-- ── TIAP SIKLUS (kendala + tindak lanjut dalam 1 blok) ── --}}
                                @foreach($repairs as $repair)
                                    @php
                                        $isFirst      = $repair->cycle === 1;
                                        $cycleNum     = $repair->cycle;
                                        $isDone       = $repair->completed_at !== null;
                                        $isActive     = !$isDone;
                                    @endphp

                                    {{-- Separator dengan label siklus --}}
                                    <div class="flex items-center gap-3 border-t border-gray-100 bg-gray-50 px-4 py-2.5 dark:border-gray-800 dark:bg-gray-800/40">
                                        @if($isFirst)
                                            <div class="flex h-5 w-5 items-center justify-center rounded-full bg-orange-500 text-[10px] font-bold text-white">1</div>
                                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Perbaikan Pertama</p>
                                        @else
                                            <div class="flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">{{ $cycleNum }}</div>
                                            <p class="text-[10px] font-bold uppercase tracking-widest text-red-500">Pengaduan Ulang #{{ $cycleNum - 1 }}</p>
                                        @endif
                                        @if($repair->technician)
                                            <p class="ml-auto text-[10px] text-gray-400">{{ $repair->technician->name }}</p>
                                        @endif
                                    </div>

                                    {{-- Kendala garansi user (jika ada, hanya siklus > 1) --}}
                                    @if($repair->warranty_claim_notes)
                                        <div class="bg-red-50 px-4 py-3 dark:bg-red-900/10">
                                            <div class="mb-1.5 flex items-center gap-1.5">
                                                <svg class="h-3.5 w-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                                                </svg>
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-red-600">Kendala yang Dilaporkan</p>
                                            </div>
                                            <p class="text-xs leading-relaxed text-red-800 dark:text-red-300">{{ $repair->warranty_claim_notes }}</p>
                                            @if(!empty($repair->warranty_claim_attachments))
                                                <div class="mt-2 grid grid-cols-3 gap-2">
                                                    @foreach($repair->warranty_claim_attachments as $path)
                                                        <a href="{{ Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}" target="_blank"
                                                           class="aspect-square overflow-hidden rounded-lg ring-1 ring-red-200">
                                                            <img src="{{ Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}" class="h-full w-full object-cover">
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Tindak lanjut teknisi --}}
                                    <div class="bg-white px-4 py-3 dark:bg-gray-900">
                                        @if($isActive)
                                            <div class="flex items-center gap-2">
                                                <div class="h-2 w-2 animate-pulse rounded-full bg-blue-400"></div>
                                                <p class="text-xs font-semibold text-blue-500">Sedang dikerjakan teknisi...</p>
                                            </div>
                                        @else
                                            @if($repair->before_notes)
                                                <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-500">Kondisi Sebelum</p>
                                                <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-400">{{ $repair->before_notes }}</p>
                                            @endif
                                            @if($repair->after_notes)
                                                <p class="mt-2 text-[10px] font-semibold uppercase tracking-wider text-blue-600">Hasil Perbaikan</p>
                                                <p class="mt-0.5 text-xs text-slate-700 dark:text-slate-300">{{ $repair->after_notes }}</p>
                                            @endif
                                            @if($repair->completed_at)
                                                <p class="mt-2 text-[10px] text-slate-400">Selesai {{ $repair->completed_at->format('d M Y, H:i') }}</p>
                                            @endif
                                            @if($repair->warranty_expires_at)
                                                <div class="mt-2 inline-flex items-center gap-1 rounded-md bg-purple-50 px-2 py-1 dark:bg-purple-900/20">
                                                    <svg class="h-3 w-3 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                                                    </svg>
                                                    <p class="text-[10px] font-semibold text-purple-700 dark:text-purple-400">Garansi s/d {{ $repair->warranty_expires_at->format('d M Y') }}</p>
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                @endforeach

                                {{-- Pengaduan belum ditangani (status re_submitted, belum ada repair baru) --}}
                                @if($statusValue === 're_submitted' && $request->warranty_claim_notes)
                                    <div class="flex items-center gap-3 border-t border-gray-100 bg-gray-50 px-4 py-2.5 dark:border-gray-800 dark:bg-gray-800/40">
                                        <div class="flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
                                            {{ $repairs->count() + 1 }}
                                        </div>
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-red-500">Pengaduan Ulang #{{ $repairs->count() }}</p>
                                    </div>
                                    <div class="bg-red-50 px-4 py-3 dark:bg-red-900/10">
                                        <div class="mb-1.5 flex items-center gap-1.5">
                                            <svg class="h-3.5 w-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                                            </svg>
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-red-600">Kendala yang Dilaporkan</p>
                                        </div>
                                        <p class="text-xs leading-relaxed text-red-800 dark:text-red-300">{{ $request->warranty_claim_notes }}</p>
                                        @if(!empty($request->warranty_claim_attachments))
                                            <div class="mt-2 grid grid-cols-3 gap-2">
                                                @foreach($request->warranty_claim_attachments as $path)
                                                    <a href="{{ Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}" target="_blank"
                                                       class="aspect-square overflow-hidden rounded-lg ring-1 ring-red-200">
                                                        <img src="{{ Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}" class="h-full w-full object-cover">
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <div class="bg-white px-4 py-3 dark:bg-gray-900">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-2 animate-pulse rounded-full bg-amber-400"></div>
                                            <p class="text-xs font-semibold text-amber-600">Menunggu teknisi mengambil pekerjaan...</p>
                                        </div>
                                    </div>
                                @endif

                                {{-- Garansi aktif + tombol lapor --}}
                                @if($statusValue === 'warranty' && $request->warranty_expires_at)
                                    <div class="border-t border-purple-100 bg-purple-50 px-4 py-3 dark:border-purple-900/30 dark:bg-purple-900/10">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-purple-600">Masa Garansi Aktif</p>
                                                <p class="mt-0.5 text-xs text-purple-800 dark:text-purple-300">
                                                    Berlaku hingga {{ $request->warranty_expires_at->format('d M Y') }}
                                                </p>
                                            </div>
                                            <svg class="h-5 w-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                                            </svg>
                                        </div>
                                    </div>

                                    @if($claimingId === $request->id)
                                        <div class="border-t border-red-100 bg-red-50 px-4 py-4 dark:border-red-900/30 dark:bg-red-900/10">
                                            <p class="mb-3 text-xs font-semibold text-red-700 dark:text-red-400">Laporkan Kendala Garansi</p>
                                            <textarea wire:model="claimNotes" rows="3"
                                                      placeholder="Jelaskan kendala yang terjadi..."
                                                      class="w-full resize-none rounded-xl border border-red-200 bg-white px-3 py-2.5 text-sm text-slate-700 placeholder-slate-300 focus:border-red-400 focus:outline-none focus:ring-1 focus:ring-red-400 dark:border-red-800 dark:bg-gray-900 dark:text-slate-200"></textarea>
                                            @error('claimNotes')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                            @if(count($claimAttachments) > 0)
                                                <div class="mt-3 grid grid-cols-3 gap-2">
                                                    @foreach($claimAttachments as $index => $attachment)
                                                        <div class="relative aspect-square">
                                                            <img src="{{ $attachment->temporaryUrl() }}"
                                                                 class="h-full w-full rounded-lg object-cover ring-1 ring-red-200">
                                                            <button wire:click="removeClaimAttachment({{ $index }})" type="button"
                                                                    class="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white shadow">
                                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                            <label class="mt-3 flex cursor-pointer items-center gap-2 rounded-xl border-2 border-dashed border-red-200 py-3 transition hover:border-red-400 hover:bg-red-100/50 dark:border-red-800 dark:hover:border-red-600">
                                                <svg class="ml-3 h-4 w-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                                                </svg>
                                                <span class="text-xs text-red-400">Tambah foto kendala</span>
                                                <input type="file" wire:model="claimAttachments" multiple accept="image/*" class="hidden">
                                            </label>
                                            @error('claimAttachments.*')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                            <div class="mt-3 flex gap-2">
                                                <button wire:click="submitClaim" wire:loading.attr="disabled"
                                                        class="flex-1 rounded-xl bg-red-600 py-2.5 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60">
                                                    <span wire:loading.remove wire:target="submitClaim">Kirim Pengaduan</span>
                                                    <span wire:loading wire:target="submitClaim">Mengirim...</span>
                                                </button>
                                                <button wire:click="cancelClaim" type="button"
                                                        class="rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-600 transition active:scale-95 dark:bg-gray-800 dark:text-gray-300">
                                                    Batal
                                                </button>
                                            </div>
                                        </div>
                                    @else
                                        <div class="border-t border-gray-100 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                                            <button wire:click="startClaim({{ $request->id }})" type="button"
                                                    class="w-full rounded-xl bg-red-600 py-3 text-sm font-semibold text-white transition active:scale-95">
                                                Laporkan Kendala Garansi
                                            </button>
                                        </div>
                                    @endif
                                @endif

                            </div>
                        @endif

                    </div>
                @endforeach
            </div>
        @endif

    </div>

    <x-technician-request.bottom-nav active="history" />

</div>
