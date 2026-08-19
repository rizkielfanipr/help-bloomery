<div>
@php
    $trip      = $this->tripModel;
    $route     = $trip->tripRoute;
    $waypoints = $route->waypoints;
    $checkins  = $trip->waypointCheckins->keyBy('trip_route_waypoint_id');
    $requiresAttachment = $this->settings->require_checkin_photo;
    $completedCount = $checkins->filter(fn($c) => $c->checked_in_at)->count();
    $totalCount     = $waypoints->count();
    $progress       = $totalCount > 0 ? round($completedCount / $totalCount * 100) : 0;
@endphp

<div class="flex flex-col bg-emerald-600" style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-6 pt-14">
        <div class="mb-3 flex items-center gap-3">
            <a href="{{ \App\Filament\Driver\Pages\TripDashboard::getUrl() }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-emerald-200">Perjalanan Aktif</p>
                <p class="truncate text-base font-semibold text-white">{{ $route->name }}</p>
            </div>
            <div class="shrink-0 text-right">
                <span class="text-2xl font-bold text-white">{{ $completedCount }}/{{ $totalCount }}</span>
                <p class="text-[10px] text-emerald-200">titik</p>
            </div>
        </div>

        <p class="mb-2 text-xs text-emerald-200">{{ $trip->vehicle->license_plate }} · Mulai {{ $trip->started_at->format('H:i') }}</p>

        {{-- Progress bar --}}
        <div class="h-1.5 overflow-hidden rounded-full bg-white/20">
            <div class="h-1.5 rounded-full bg-white transition-all duration-500" style="width:{{ $progress }}%"></div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-10 pt-6 dark:bg-gray-950">

        {{-- Odometer start card --}}
        @if(!$trip->odo_start)
            <div class="mx-5 mb-5 flex items-center gap-3 overflow-hidden rounded-2xl bg-amber-50 px-4 py-3.5 ring-1 ring-amber-200 dark:bg-amber-900/20 dark:ring-amber-700/40">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500">
                    <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Odometer Awal Belum Diisi</p>
                    <p class="text-xs text-amber-600 dark:text-amber-400">Catat angka odometer kendaraan sekarang</p>
                </div>
                <button wire:click="$dispatch('open-modal', {id: 'odo-start-modal'})"
                        class="shrink-0 flex items-center gap-1.5 rounded-xl bg-amber-500 px-3 py-2 text-xs font-semibold text-white transition active:scale-95 active:bg-amber-600">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    Isi Sekarang
                </button>
            </div>
        @else
            <div class="mx-5 mb-5 flex items-center gap-3 overflow-hidden rounded-2xl bg-white px-4 py-3 ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-green-50 dark:bg-green-900/20">
                    <svg class="h-4.5 w-4.5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Odometer Awal</p>
                    <p class="font-mono text-sm font-bold text-gray-900 dark:text-white">{{ number_format($trip->odo_start) }} km</p>
                </div>
                @if($trip->odo_start_photo)
                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl ring-2 ring-green-100 dark:ring-green-900/40">
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($trip->odo_start_photo, now()->addHour()) }}"
                             class="h-full w-full object-cover" alt="Odo awal">
                    </div>
                @endif
                <button wire:click="$dispatch('open-modal', {id: 'odo-start-modal'})"
                        class="shrink-0 flex items-center gap-1 rounded-lg bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-500 transition active:bg-gray-200 dark:bg-gray-800 dark:text-gray-400">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
                    </svg>
                    Edit
                </button>
            </div>
        @endif

        {{-- Waypoints timeline --}}
        <div class="mx-5">
            <p class="mb-3 text-[11px] font-semibold uppercase tracking-widest text-slate-400">Titik Perjalanan</p>

            <div class="relative">
                {{-- Vertical line --}}
                <div class="absolute bottom-4 left-4 top-4 w-px bg-gray-200 dark:bg-gray-700"></div>

                <div class="space-y-3">
                    @foreach($waypoints as $waypoint)
                        @php
                            $checkin     = $checkins[$waypoint->id] ?? null;
                            $isDone      = $checkin && $checkin->checked_in_at;
                            $hasAttach   = $checkin && $checkin->attachment_path;
                            $isReady     = $isDone && (!$requiresAttachment || $hasAttach);
                        @endphp

                        <div class="flex gap-4">
                            {{-- Dot --}}
                            <div class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 ring-gray-50 dark:ring-gray-950
                                {{ $isReady ? 'bg-emerald-500' : ($isDone ? 'bg-amber-400' : 'bg-gray-200 dark:bg-gray-700') }}">
                                @if($isReady)
                                    <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                @elseif($isDone)
                                    <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/></svg>
                                @else
                                    <span class="h-2 w-2 rounded-full bg-gray-400 dark:bg-gray-500"></span>
                                @endif
                            </div>

                            {{-- Content --}}
                            <div class="min-w-0 flex-1 overflow-hidden rounded-2xl bg-white pb-4 ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                                <div class="flex items-start justify-between gap-2 px-4 pt-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ $waypoint->name }}
                                        </p>
                                        @if($waypoint->description)
                                            <p class="mt-0.5 text-xs text-gray-400">{{ $waypoint->description }}</p>
                                        @endif
                                        @if($isDone)
                                            <p class="mt-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                ✓ Check-in: {{ $checkin->checked_in_at->format('H:i') }}
                                            </p>
                                            @if($hasAttach)
                                                <p class="text-xs text-blue-500">📎 Bukti terupload</p>
                                            @elseif($requiresAttachment)
                                                <p class="text-xs text-red-500">⚠ Belum upload bukti</p>
                                            @endif
                                        @else
                                            <p class="mt-1 text-xs text-gray-400">Belum check-in</p>
                                        @endif
                                    </div>

                                    @if($checkin)
                                        <button wire:click="openCheckinModal({{ $checkin->id }})"
                                                class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold transition
                                                    {{ $isReady ? 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' : 'bg-emerald-600 text-white active:scale-95' }}">
                                            {{ $isReady ? 'Edit' : 'Check-in' }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Odometer end card --}}
        <div class="mx-5 mt-5">
            @if(!$trip->odo_end)
                <div class="flex items-center gap-3 overflow-hidden rounded-2xl bg-white px-4 py-3.5 ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800">
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Odometer Akhir</p>
                        <p class="text-xs text-gray-400">Belum diisi</p>
                    </div>
                    <button wire:click="$dispatch('open-modal', {id: 'odo-end-modal'})"
                            class="shrink-0 flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition active:scale-95 active:bg-emerald-700">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        Isi Sekarang
                    </button>
                </div>
            @else
                <div class="flex items-center gap-3 overflow-hidden rounded-2xl bg-white px-4 py-3 ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-green-50 dark:bg-green-900/20">
                        <svg class="h-4.5 w-4.5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Odometer Akhir</p>
                        <p class="font-mono text-sm font-bold text-gray-900 dark:text-white">{{ number_format($trip->odo_end) }} km</p>
                        @if($trip->odo_start)
                            <p class="text-[11px] text-gray-400">+{{ number_format($trip->odo_end - $trip->odo_start) }} km dari awal</p>
                        @endif
                    </div>
                    @if($trip->odo_end_photo)
                        <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl ring-2 ring-green-100 dark:ring-green-900/40">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($trip->odo_end_photo, now()->addHour()) }}"
                                 class="h-full w-full object-cover" alt="Odo akhir">
                        </div>
                    @endif
                    <button wire:click="$dispatch('open-modal', {id: 'odo-end-modal'})"
                            class="shrink-0 flex items-center gap-1 rounded-lg bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-500 transition active:bg-gray-200 dark:bg-gray-800 dark:text-gray-400">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
                        </svg>
                        Edit
                    </button>
                </div>
            @endif
        </div>

        {{-- Complete trip button --}}
        <div class="mx-5 mt-6">
            @if(!$trip->allWaypointsCompleted())
                <p class="mb-3 text-center text-xs text-gray-400">
                    Selesaikan semua titik{{ $requiresAttachment ? ' beserta upload bukti' : '' }} untuk mengakhiri perjalanan.
                </p>
            @endif
            <button wire:click="openFuelModal"
                    @disabled(!$trip->allWaypointsCompleted())
                    class="flex w-full items-center justify-center gap-2 rounded-2xl py-4 text-sm font-bold text-white transition
                        {{ $trip->allWaypointsCompleted() ? 'bg-emerald-600 active:scale-95 active:bg-emerald-700' : 'cursor-not-allowed bg-gray-200 text-gray-400 dark:bg-gray-800 dark:text-gray-600' }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5"/>
                </svg>
                Selesaikan Perjalanan
            </button>
        </div>

    </div>

</div>

{{-- ══ ODO START MODAL ══ --}}
<x-filament::modal id="odo-start-modal" width="sm">
    <x-slot name="heading">Odometer Awal</x-slot>
    <x-slot name="description">Catat angka odometer kendaraan saat memulai perjalanan</x-slot>
    <div
        x-data="{
            km: '{{ $trip->odo_start ?? '' }}',
            photo: null,
            uploading: false,

            async pickGallery() {
                this.$refs.odoStartFile.click();
            },

            async onFilePicked(event) {
                const file = event.target.files[0];
                if (!file) return;
                this.uploading = true;
                await this.$nextTick();
                const c   = this.$refs.odoStartCanvas;
                const url = URL.createObjectURL(file);
                const img = new Image();
                img.onload = () => {
                    c.width = img.naturalWidth; c.height = img.naturalHeight;
                    const ctx = c.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    URL.revokeObjectURL(url);
                    const now = new Date(), pad = n => n.toString().padStart(2,'0');
                    const ts = pad(now.getDate()) + '/' + pad(now.getMonth()+1) + '/' + now.getFullYear()
                             + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
                    const bh = 52;
                    ctx.fillStyle = 'rgba(0,0,0,0.60)';
                    ctx.fillRect(0, c.height - bh, c.width, bh);
                    ctx.fillStyle = '#fff'; ctx.font = 'bold 22px monospace';
                    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                    ctx.fillText(ts, c.width / 2, c.height - bh / 2);
                    this.photo = c.toDataURL('image/jpeg', 0.85);
                    c.toBlob((blob) => {
                        const stamped = new File([blob], 'odo-start.jpg', { type: 'image/jpeg' });
                        this.$wire.upload('odoStartPhoto', stamped,
                            () => { this.uploading = false; },
                            () => { this.uploading = false; }
                        );
                    }, 'image/jpeg', 0.85);
                };
                img.src = url;
                event.target.value = '';
            }
        }">
        <input x-ref="odoStartFile" type="file" accept="image/*" class="hidden" @change="onFilePicked($event)">
        <canvas x-ref="odoStartCanvas" class="hidden"></canvas>

        <div class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Angka Kilometer</label>
                <div class="flex items-center gap-2 overflow-hidden rounded-xl border-2 border-gray-200 bg-white px-3 focus-within:border-emerald-500 dark:border-gray-700 dark:bg-gray-800">
                    <input x-model="km" type="number" min="0" placeholder="Contoh: 12500"
                           class="flex-1 bg-transparent py-3 text-sm font-semibold text-gray-900 outline-none placeholder:font-normal placeholder:text-gray-400 dark:text-white">
                    <span class="shrink-0 text-xs font-medium text-gray-400">km</span>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Foto Odometer <span class="text-gray-400 font-normal">(opsional)</span></label>
                <template x-if="!photo">
                    <button @click="pickGallery()" type="button"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-200 py-4 text-sm font-medium text-gray-400 transition active:bg-gray-50 dark:border-gray-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                        </svg>
                        Pilih / Ambil Foto
                    </button>
                </template>
                <template x-if="photo">
                    <div class="relative overflow-hidden rounded-xl">
                        <img :src="photo" class="h-32 w-full object-cover">
                        <button @click="photo = null; $wire.set('odoStartPhoto', null)" type="button"
                                class="absolute right-2 top-2 rounded-lg bg-black/50 px-2.5 py-1 text-xs font-semibold text-white">
                            Ulangi
                        </button>
                        <div x-show="uploading" class="absolute inset-0 flex items-center justify-center bg-black/40">
                            <svg class="h-6 w-6 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
    <x-slot name="footerActions">
        <x-filament::button
            x-on:click="if (km && parseInt(km) > 0) $wire.saveOdoStart(parseInt(km))"
            color="success"
            icon="heroicon-m-check">
            Simpan Odometer
        </x-filament::button>
        <x-filament::button color="gray" outlined
            wire:click="$dispatch('close-modal', {id: 'odo-start-modal'})">
            Batal
        </x-filament::button>
    </x-slot>
</x-filament::modal>

{{-- ══ ODO END MODAL ══ --}}
<x-filament::modal id="odo-end-modal" width="sm">
    <x-slot name="heading">Odometer Akhir</x-slot>
    <x-slot name="description">Catat angka odometer kendaraan di akhir perjalanan</x-slot>
    <div
        x-data="{
            km: '',
            photo: null,
            uploading: false,

            async onFilePicked(event) {
                const file = event.target.files[0];
                if (!file) return;
                this.uploading = true;
                await this.$nextTick();
                const c   = this.$refs.odoEndCanvas;
                const url = URL.createObjectURL(file);
                const img = new Image();
                img.onload = () => {
                    c.width = img.naturalWidth; c.height = img.naturalHeight;
                    const ctx = c.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    URL.revokeObjectURL(url);
                    const now = new Date(), pad = n => n.toString().padStart(2,'0');
                    const ts = pad(now.getDate()) + '/' + pad(now.getMonth()+1) + '/' + now.getFullYear()
                             + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
                    const bh = 52;
                    ctx.fillStyle = 'rgba(0,0,0,0.60)';
                    ctx.fillRect(0, c.height - bh, c.width, bh);
                    ctx.fillStyle = '#fff'; ctx.font = 'bold 22px monospace';
                    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                    ctx.fillText(ts, c.width / 2, c.height - bh / 2);
                    this.photo = c.toDataURL('image/jpeg', 0.85);
                    c.toBlob((blob) => {
                        const stamped = new File([blob], 'odo-end.jpg', { type: 'image/jpeg' });
                        this.$wire.upload('odoEndPhoto', stamped,
                            () => { this.uploading = false; },
                            () => { this.uploading = false; }
                        );
                    }, 'image/jpeg', 0.85);
                };
                img.src = url;
                event.target.value = '';
            }
        }">
        <input x-ref="odoEndFile" type="file" accept="image/*" class="hidden" @change="onFilePicked($event)">
        <canvas x-ref="odoEndCanvas" class="hidden"></canvas>

        <div class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Angka Kilometer</label>
                <div class="flex items-center gap-2 overflow-hidden rounded-xl border-2 bg-white px-3 dark:bg-gray-800"
                     :class="km && parseInt(km) <= {{ (int) ($trip->odo_start ?? 0) }} && {{ (int) ($trip->odo_start ?? 0) }} > 0
                         ? 'border-red-400 focus-within:border-red-500'
                         : 'border-gray-200 focus-within:border-emerald-500 dark:border-gray-700'">
                    <input x-model="km" type="number" min="0" placeholder="Contoh: 12650"
                           class="flex-1 bg-transparent py-3 text-sm font-semibold text-gray-900 outline-none placeholder:font-normal placeholder:text-gray-400 dark:text-white">
                    <span class="shrink-0 text-xs font-medium text-gray-400">km</span>
                </div>
                @if($trip->odo_start)
                    <p x-show="km && parseInt(km) <= {{ (int) $trip->odo_start }}"
                       class="mt-1.5 text-xs font-medium text-red-500"
                       style="display:none">
                        Harus lebih dari odometer awal ({{ number_format($trip->odo_start) }} km)
                    </p>
                @endif
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Foto Odometer <span class="text-gray-400 font-normal">(opsional)</span></label>
                <template x-if="!photo">
                    <button @click="$refs.odoEndFile.click()" type="button"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-200 py-4 text-sm font-medium text-gray-400 transition active:bg-gray-50 dark:border-gray-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                        </svg>
                        Pilih / Ambil Foto
                    </button>
                </template>
                <template x-if="photo">
                    <div class="relative overflow-hidden rounded-xl">
                        <img :src="photo" class="h-32 w-full object-cover">
                        <button @click="photo = null; $wire.set('odoEndPhoto', null)" type="button"
                                class="absolute right-2 top-2 rounded-lg bg-black/50 px-2.5 py-1 text-xs font-semibold text-white">
                            Ulangi
                        </button>
                        <div x-show="uploading" class="absolute inset-0 flex items-center justify-center bg-black/40">
                            <svg class="h-6 w-6 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
    <x-slot name="footerActions">
        <x-filament::button
            x-on:click="if (km && parseInt(km) > 0 && !({{ (int) ($trip->odo_start ?? 0) }} > 0 && parseInt(km) <= {{ (int) ($trip->odo_start ?? 0) }})) $wire.saveOdoEnd(parseInt(km))"
            x-bind:class="!km || parseInt(km) <= 0 || ({{ (int) ($trip->odo_start ?? 0) }} > 0 && parseInt(km) <= {{ (int) ($trip->odo_start ?? 0) }}) ? 'opacity-40 cursor-not-allowed' : ''"
            color="success"
            icon="heroicon-m-check">
            Simpan Odometer
        </x-filament::button>
        <x-filament::button color="gray" outlined
            wire:click="$dispatch('close-modal', {id: 'odo-end-modal'})">
            Batal
        </x-filament::button>
    </x-slot>
</x-filament::modal>

{{-- ══ CHECKIN MODAL ══ --}}
<x-filament::modal id="checkin-modal" width="lg">
    <x-slot name="heading">Check-in Titik Perjalanan</x-slot>
    <div wire:key="checkin-form-{{ $this->checkinModalKey }}">
        {{ $this->checkinForm }}
    </div>
    <x-slot name="footerActions">
        <x-filament::button wire:click="saveCheckin" color="success" icon="heroicon-m-check">Simpan</x-filament::button>
        <x-filament::button color="gray" outlined wire:click="cancelCheckinModal">Batal</x-filament::button>
    </x-slot>
</x-filament::modal>

{{-- ══ FUEL CONFIRM ══ --}}
<x-filament::modal id="fuel-confirm" width="sm">
    <x-slot name="heading">Pengisian BBM</x-slot>
    <x-slot name="description">Apakah ada pengisian BBM dalam perjalanan ini?</x-slot>
    <x-slot name="footerActions">
        <x-filament::button wire:click="confirmHasFuel" color="success" icon="heroicon-m-check">Ya, Ada Pengisian</x-filament::button>
        <x-filament::button wire:click="confirmNoFuel" color="gray" outlined>Tidak</x-filament::button>
    </x-slot>
</x-filament::modal>
</div>
