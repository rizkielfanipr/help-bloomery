@php
    $user      = auth()->user();
    $shift     = $user->casualShift;
    $record    = $this->todayRecord;
    $clockedIn = $record?->clock_in_at  !== null;
    $clockedOut= $record?->clock_out_at !== null;
    $stats     = $this->monthlyStats;
    $workHours = $this->workingHours;

    $firstName    = explode(' ', $user->name)[0];
    $monthName    = strtoupper(now()->locale('id')->isoFormat('MMM'));
    $unreadCount  = $this->unreadCount;

    $displayIn  = $clockedIn  ? $record->clock_in_at->format('H:i')  : ($shift ? \Carbon\Carbon::parse($shift->start_time)->format('H:i') : '--:--');
    $displayOut = $clockedOut ? $record->clock_out_at->format('H:i') : ($shift ? \Carbon\Carbon::parse($shift->end_time)->format('H:i')   : '--:--');
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900"
     style="min-height:100dvh"
     x-data="{
         action: null,
         lat: @entangle('latitude'),
         lng: @entangle('longitude'),
         locStatus: 'idle',
         locError: null,
         photo: null,
         stream: null,
         mockMode: false,
         uploading: false,
         camError: null,
         uploadProp: null,

         get hasPhoto()  { return !!this.photo; },
         get hasStream() { return !!this.stream || this.mockMode; },

         open(which) {
             this.action     = which;
             this.photo      = null;
             this.camError   = null;
             this.mockMode   = false;
             this.uploading  = false;
             this.uploadProp = which === 'in' ? 'clockInPhoto' : 'clockOutPhoto';
             this.$nextTick(() => this.startCamera());
             if (this.locStatus === 'idle') this.detectLocation();
         },

         close() {
             this.stopStream();
             this.mockMode   = false;
             this.$wire.set('clockInPhoto', null);
             this.$wire.set('clockOutPhoto', null);
             this.photo  = null;
             this.action = null;
         },

         async startCamera() {
             this.camError = null;
             this.mockMode = false;
             if (!navigator.mediaDevices?.getUserMedia) {
                 this.mockMode = true;
                 return;
             }
             try {
                 this.stream = await navigator.mediaDevices.getUserMedia({
                     video: { facingMode: 'user', width: { ideal: 720 }, height: { ideal: 720 } },
                     audio: false
                 });
                 await this.$nextTick();
                 const v = this.$refs.video;
                 if (v) { v.srcObject = this.stream; await v.play(); }
             } catch(e) {
                 this.mockMode = true;
             }
         },

         shoot() {
             const c = this.$refs.canvas;
             if (!c) return;
             if (this.mockMode) {
                 const s = 480;
                 c.width = c.height = s;
                 const ctx = c.getContext('2d');
                 const g = ctx.createLinearGradient(0, 0, s, s);
                 g.addColorStop(0, '#1e293b'); g.addColorStop(1, '#0f172a');
                 ctx.fillStyle = g; ctx.fillRect(0, 0, s, s);
                 ctx.fillStyle = 'rgba(255,255,255,0.15)';
                 ctx.font = 'bold 40px sans-serif'; ctx.textAlign = 'center';
                 ctx.fillText('[ TEST ]', s / 2, s / 2);
             } else {
                 const v = this.$refs.video;
                 if (!v) return;
                 const s = Math.min(v.videoWidth, v.videoHeight) || 480;
                 c.width = c.height = s;
                 const ctx = c.getContext('2d');
                 ctx.save(); ctx.scale(-1, 1);
                 ctx.drawImage(v, -(v.videoWidth - s) / 2 - s, -(v.videoHeight - s) / 2, v.videoWidth, v.videoHeight);
                 ctx.restore();
                 this.stopStream();
             }
             this.photo = c.toDataURL('image/jpeg', 0.85);
             c.toBlob((blob) => {
                 const file = new File([blob], 'selfie.jpg', { type: 'image/jpeg' });
                 this.uploading = true;
                 this.$wire.upload(this.uploadProp, file,
                     ()  => { this.uploading = false; },
                     ()  => { this.uploading = false; this.camError = 'Upload gagal, coba lagi.'; }
                 );
             }, 'image/jpeg', 0.85);
         },

         retake() {
             this.photo    = null;
             this.camError = null;
             this.$wire.set(this.uploadProp, null);
             this.startCamera();
         },

         stopStream() {
             if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
         },

         detectLocation() {
             this.locStatus = 'detecting';
             this.locError  = null;
             if (!navigator.geolocation) {
                 this.locStatus = 'error';
                 this.locError  = 'Browser tidak mendukung geolokasi.';
                 return;
             }
             navigator.geolocation.getCurrentPosition(
                 (pos) => {
                     this.lat       = pos.coords.latitude;
                     this.lng       = pos.coords.longitude;
                     this.locStatus = 'detected';
                 },
                 (err) => {
                     this.locStatus = 'error';
                     this.locError  = err.message;
                 },
                 { enableHighAccuracy: true, timeout: 15000 }
             );
         },

         confirm() {
             if (this.action === 'in') { this.$wire.clockIn(); } else { this.$wire.clockOut(); }
             this.stopStream();
             this.action = null;
             this.photo  = null;
         }
     }"
     @keydown.escape.window="close()">

    {{-- ════════════════════════════════════════════
         BLUE HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">

        {{-- Location row --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-1.5">
                <svg class="h-4 w-4 flex-shrink-0 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M9.69 18.933l.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 0 0 .281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 1 0 3 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 0 0 2.273 1.765 11.842 11.842 0 0 0 .976.544l.062.029.018.008.006.003zM10 11.25a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5z" clip-rule="evenodd"/>
                </svg>
                <span class="text-sm font-medium text-blue-100">
                    {{ $shift ? $shift->name : 'Lokasi Kerja' }}
                </span>
            </div>
            <a href="{{ \App\Filament\Casual\Pages\NotificationPage::getUrl() }}"
               class="relative flex h-9 w-9 items-center justify-center rounded-full bg-white/15 text-white transition active:bg-white/25">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                </svg>
                @if($unreadCount > 0)
                    <span class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-bold text-white">
                        {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                    </span>
                @endif
            </a>
        </div>

        {{-- Greeting --}}
        <div class="mt-5">
            <p class="text-sm font-medium text-blue-200">{{ now()->locale('id')->isoFormat('dddd, D MMMM YYYY') }}</p>
            <h1 class="mt-0.5 text-xl font-semibold text-white">Halo, {{ $user->name }}!</h1>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        {{-- ── Clock card ── --}}
        <div class="mx-5 mt-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">

            {{-- Shift badge + status --}}
            <div class="flex items-center justify-between px-5 pt-5">
                @if($shift)
                    <span class="rounded-full bg-teal-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-teal-700 dark:bg-teal-900/30 dark:text-teal-400">
                        {{ strtoupper($shift->name) }}
                    </span>
                @else
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                        Shift Belum Diatur
                    </span>
                @endif

                @if($clockedOut)
                    <span class="flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                        Selesai
                    </span>
                @elseif($clockedIn)
                    <span class="flex items-center gap-1.5 rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-blue-500"></span>
                        Bekerja
                    </span>
                @endif
            </div>

            {{-- Live clock + action button --}}
            <div class="flex items-center justify-between px-5 pb-5 pt-3">
                <div x-data="{ t: '' }"
                     x-init="const p = v => v.toString().padStart(2,'0');
                              const fmt = () => { const n = new Date(); t = p(n.getHours()) + ':' + p(n.getMinutes()) + ':' + p(n.getSeconds()); };
                              fmt(); setInterval(fmt, 1000);"
                     class="font-mono text-[1.9rem] font-bold tracking-tight text-gray-900 dark:text-white"
                     x-text="t">
                </div>

                @if(! $clockedIn && $shift)
                    <button @click="open('in')"
                            class="flex items-center gap-1.5 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white transition active:scale-95">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
                        </svg>
                        Clock In
                    </button>
                @elseif($clockedIn && ! $clockedOut)
                    <button @click="open('out')"
                            class="flex items-center gap-1.5 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-bold text-white transition active:scale-95">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15"/>
                        </svg>
                        Clock Out
                    </button>
                @elseif($clockedOut)
                    <div class="flex items-center gap-1.5 rounded-xl bg-green-50 px-4 py-2.5 text-sm font-bold text-green-600 dark:bg-green-900/20 dark:text-green-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                        Selesai
                    </div>
                @else
                    <div class="flex items-center gap-1.5 rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-bold text-gray-400 dark:bg-gray-800 dark:text-gray-600">
                        Clock In
                    </div>
                @endif
            </div>

            <div class="mx-5 border-t border-gray-100 dark:border-gray-800"></div>

            {{-- 3-column info row --}}
            <div class="flex divide-x divide-gray-100 px-2 py-4 dark:divide-gray-800">

                {{-- Check In --}}
                <div class="flex flex-1 flex-col items-center gap-1.5 px-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-900/20">
                        <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
                        </svg>
                    </div>
                    <p class="font-mono text-sm font-bold text-gray-900 dark:text-white">{{ $displayIn }}</p>
                    <p class="text-center text-xs leading-tight text-gray-400">{{ $clockedIn ? 'Jam Masuk' : 'Jadwal Masuk' }}</p>
                </div>

                {{-- Check Out --}}
                <div class="flex flex-1 flex-col items-center gap-1.5 px-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-900/20">
                        <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15"/>
                        </svg>
                    </div>
                    <p class="font-mono text-sm font-bold text-gray-900 dark:text-white">{{ $displayOut }}</p>
                    <p class="text-center text-xs leading-tight text-gray-400">{{ $clockedOut ? 'Jam Keluar' : 'Jadwal Keluar' }}</p>
                </div>

                {{-- Working Hours --}}
                <div class="flex flex-1 flex-col items-center gap-1.5 px-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-900/20">
                        <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                        </svg>
                    </div>
                    <p class="font-mono text-sm font-bold text-gray-900 dark:text-white">{{ $workHours }}</p>
                    <p class="text-center text-xs leading-tight text-gray-400">Jam Kerja</p>
                </div>

            </div>
        </div>

        {{-- ── Monthly attendance section ── --}}
        <div class="mx-5 mt-5">
            <div class="flex items-center justify-between">
                <p class="font-bold text-gray-900 dark:text-white">Absensi Bulan Ini</p>
                <div class="flex items-center gap-1.5 rounded-lg bg-gray-200/70 px-3 py-1.5 text-xs font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                    </svg>
                    {{ $monthName }}
                </div>
            </div>

            <div class="mt-3 grid grid-cols-3 gap-3">
                <div class="flex flex-col items-center gap-1.5 rounded-2xl bg-green-50 py-4 dark:bg-green-900/20">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Hadir</p>
                    <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ $stats['present'] }}</p>
                </div>
                <div class="flex flex-col items-center gap-1.5 rounded-2xl bg-red-50 py-4 dark:bg-red-900/20">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Absen</p>
                    <p class="text-3xl font-bold text-red-500 dark:text-red-400">{{ $stats['absent'] }}</p>
                </div>
                <div class="flex flex-col items-center gap-1.5 rounded-2xl bg-amber-50 py-4 dark:bg-amber-900/20">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Terlambat</p>
                    <p class="text-3xl font-bold text-amber-500 dark:text-amber-400">{{ $stats['late'] }}</p>
                </div>
            </div>
        </div>

        {{-- ── Request button ── --}}
        <div class="mx-5 mt-4">
            <button wire:click="requestLeave"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-gray-200 py-4 text-sm font-semibold text-gray-500 transition active:bg-gray-100 dark:border-gray-700 dark:text-gray-400 dark:active:bg-gray-800">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Buat Pengajuan
            </button>
        </div>

    </div>

    {{-- ════════════════════════════════════════════
         FACE RECOGNITION FULL-SCREEN OVERLAY
    ════════════════════════════════════════════ --}}
    <style>
        @keyframes faceScan {
            0%   { top: 2%; opacity: 0; }
            8%   { opacity: 1; }
            92%  { opacity: 1; }
            100% { top: 96%; opacity: 0; }
        }
        @keyframes ovalPulse {
            0%, 100% { opacity: 0.7; }
            50%       { opacity: 1; }
        }
        .face-scan-line  { animation: faceScan 2.4s ease-in-out infinite; }
        .face-oval-ring  { animation: ovalPulse 2.4s ease-in-out infinite; }
    </style>

    <div x-show="action !== null"
         x-transition:enter="transition duration-300 ease-out"
         x-transition:enter-start="opacity-0 scale-[1.02]"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition duration-200 ease-in"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-[1.02]"
         class="fixed inset-0 z-50 overflow-hidden bg-black"
         style="display:none">

        {{-- ── Camera feed ── --}}
        <video x-ref="video"
               x-show="hasStream && !hasPhoto && !mockMode"
               autoplay muted playsinline
               class="absolute inset-0 h-full w-full object-cover"
               style="transform:scaleX(-1)"></video>

        {{-- ── Mock background (no camera) ── --}}
        <div x-show="mockMode && !hasPhoto"
             class="absolute inset-0"
             style="background: radial-gradient(ellipse at 50% 40%, #1e3a5f 0%, #0f172a 70%)">
            <div class="absolute inset-0 flex items-center justify-center opacity-10">
                <svg viewBox="0 0 100 120" class="w-40" fill="white">
                    <ellipse cx="50" cy="38" rx="26" ry="30"/>
                    <path d="M10 120 Q10 80 50 78 Q90 80 90 120Z"/>
                </svg>
            </div>
        </div>

        {{-- ── Mode test badge ── --}}
        <div x-show="mockMode && !hasPhoto"
             class="absolute right-5 top-36 z-20 rounded-full bg-yellow-500/80 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-black backdrop-blur-sm">
            Mode Test
        </div>

        {{-- ── Captured photo ── --}}
        <img x-show="hasPhoto" :src="photo"
             class="absolute inset-0 h-full w-full object-cover" alt="">

        {{-- ── Loading state ── --}}
        <div x-show="!hasStream && !hasPhoto && !camError"
             class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-gray-950">
            <svg class="h-10 w-10 animate-spin text-white/30" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <p class="text-sm text-white/40">Membuka kamera...</p>
        </div>

        {{-- ── Face oval guide (camera active) ── --}}
        <div x-show="hasStream && !hasPhoto"
             class="pointer-events-none absolute inset-0 flex items-center justify-center"
             style="padding-bottom: 8vh">

            {{-- Dark surround with oval cutout --}}
            <div class="relative"
                 style="width: min(68vw, 280px); aspect-ratio: 3/4;">

                {{-- Oval ring --}}
                <div class="face-oval-ring absolute inset-0 rounded-[50%]"
                     style="border: 2.5px solid rgba(255,255,255,0.85); box-shadow: 0 0 0 9999px rgba(0,0,0,0.52);"></div>

                {{-- Scan line (clipped to oval) --}}
                <div class="absolute inset-0 overflow-hidden rounded-[50%]">
                    <div class="face-scan-line absolute inset-x-0 h-px"
                         style="background: linear-gradient(to right, transparent, rgba(59,130,246,0.9), transparent);
                                box-shadow: 0 0 8px 2px rgba(59,130,246,0.6);"></div>
                </div>

                {{-- Corner accents (top-left, top-right, bottom-left, bottom-right) --}}
                <div class="absolute -top-0.5 -left-0.5 h-8 w-8 rounded-tl-[50%]"
                     style="border-top: 3px solid #3b82f6; border-left: 3px solid #3b82f6;"></div>
                <div class="absolute -top-0.5 -right-0.5 h-8 w-8 rounded-tr-[50%]"
                     style="border-top: 3px solid #3b82f6; border-right: 3px solid #3b82f6;"></div>
                <div class="absolute -bottom-0.5 -left-0.5 h-8 w-8 rounded-bl-[50%]"
                     style="border-bottom: 3px solid #3b82f6; border-left: 3px solid #3b82f6;"></div>
                <div class="absolute -bottom-0.5 -right-0.5 h-8 w-8 rounded-br-[50%]"
                     style="border-bottom: 3px solid #3b82f6; border-right: 3px solid #3b82f6;"></div>
            </div>
        </div>

        {{-- ── TOP BAR ── --}}
        <div class="absolute inset-x-0 top-0 z-10 px-5 pb-5 pt-12"
             style="background: linear-gradient(to bottom, rgba(0,0,0,0.72) 0%, transparent 100%)">
            <div class="flex items-center justify-between">

                {{-- Back button --}}
                <button @click="close()"
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white backdrop-blur-sm transition active:bg-white/25">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                    </svg>
                </button>

                {{-- Title --}}
                <div class="text-center">
                    <p class="font-semibold text-white">Deteksi Wajah</p>
                    <p class="text-xs font-medium"
                       :class="action === 'in' ? 'text-blue-300' : 'text-orange-300'"
                       x-text="action === 'in' ? 'Clock In' : 'Clock Out'"></p>
                </div>

                {{-- Location status badge --}}
                <div class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold backdrop-blur-sm transition"
                     :class="{
                         'bg-green-500/80 text-white':  locStatus === \'detected\',
                         'bg-yellow-500/80 text-white': locStatus === \'detecting\',
                         'bg-red-500/80 text-white':    locStatus === \'error\',
                         'bg-white/20 text-white/70':   locStatus === \'idle\',
                     }">
                    <span class="h-1.5 w-1.5 rounded-full bg-current"
                          :class="locStatus === \'detected\' || locStatus === \'detecting\' ? \'animate-pulse\' : \'\'"></span>
                    <span x-text="{idle:\'Lokasi\',detecting:\'Deteksi...\',detected:\'Online\',error:\'Offline\'}[locStatus]"></span>
                </div>
            </div>

            {{-- Zone banner --}}
            <div x-show="locStatus === 'detected' && hasStream && !hasPhoto"
                 x-transition:enter="transition duration-400 ease-out"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="mt-3 flex items-center gap-2 rounded-2xl bg-green-500/85 px-4 py-2.5 backdrop-blur-sm">
                <svg class="h-4 w-4 flex-shrink-0 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
                <span class="text-sm font-semibold text-white">Anda berada di area absensi</span>
            </div>
        </div>

        {{-- ── Camera error (upload fail only) ── --}}
        <div x-show="camError"
             class="absolute bottom-36 inset-x-0 z-20 mx-6 flex items-center gap-3 rounded-2xl bg-red-500/90 px-4 py-3 backdrop-blur-sm">
            <svg class="h-5 w-5 flex-shrink-0 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
            </svg>
            <span class="flex-1 text-sm font-medium text-white" x-text="camError"></span>
            <button @click="camError = null; retake()"
                    class="text-xs font-bold text-white/80 underline">Ulang</button>
        </div>

        {{-- ── Upload overlay ── --}}
        <div x-show="uploading"
             class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-black/70 backdrop-blur-sm">
            <svg class="h-10 w-10 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <p class="text-sm font-medium text-white/80">Menyimpan foto...</p>
        </div>

        {{-- ── Photo ready badge ── --}}
        <div x-show="hasPhoto && !uploading"
             class="absolute left-5 top-16 z-10 mt-4 flex items-center gap-1.5 rounded-full bg-green-500 px-3 py-1 text-xs font-semibold text-white shadow-lg">
            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/>
            </svg>
            Foto siap
        </div>

        <canvas x-ref="canvas" class="hidden"></canvas>

        {{-- ── BOTTOM CONTROLS ── --}}
        <div class="absolute inset-x-0 bottom-0 z-10 px-6 pb-14 pt-8"
             style="background: linear-gradient(to top, rgba(0,0,0,0.75) 0%, transparent 100%)">

            {{-- Instruction text --}}
            <p x-show="hasStream && !hasPhoto"
               class="mb-7 text-center text-sm font-medium text-white/70">
                Posisikan wajah dalam bingkai oval, lalu tekan tombol
            </p>

            {{-- Live time --}}
            <div x-show="hasStream && !hasPhoto"
                 x-data="{ t: '' }"
                 x-init="const p = v => v.toString().padStart(2,'0'); setInterval(() => { const n = new Date(); t = p(n.getHours()) + ':' + p(n.getMinutes()) + ':' + p(n.getSeconds()); }, 1000)"
                 class="mb-5 text-center font-mono text-2xl font-bold text-white/90"
                 x-text="t">
            </div>

            {{-- Shutter button --}}
            <div x-show="hasStream && !hasPhoto" class="flex justify-center">
                <button @click="shoot()"
                        class="relative flex h-20 w-20 items-center justify-center rounded-full ring-4 ring-white/30 transition active:scale-90">
                    <div class="h-[3.75rem] w-[3.75rem] rounded-full bg-white shadow-xl"></div>
                </button>
            </div>

            {{-- Retake / Confirm --}}
            <div x-show="hasPhoto && !uploading" class="flex gap-3">
                <button @click="retake()"
                        class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-white/15 py-4 text-sm font-semibold text-white backdrop-blur-sm transition active:bg-white/25 active:scale-95">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                    Ulang
                </button>
                <button @click="confirm()"
                        :class="action === 'in' ? 'bg-blue-600 shadow-blue-600/40' : 'bg-orange-500 shadow-orange-500/40'"
                        class="flex flex-1 items-center justify-center gap-2 rounded-2xl py-4 text-sm font-bold text-white shadow-lg transition active:scale-95">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                    <span x-text="action === 'in' ? 'Clock In' : 'Clock Out'"></span>
                </button>
            </div>

        </div>
    </div>

    {{-- ════════════════════════════════════════════
         FIXED BOTTOM NAVIGATION
    ════════════════════════════════════════════ --}}
    <div class="fixed inset-x-0 bottom-0 z-30 flex border-t border-gray-100 bg-white/95 backdrop-blur-sm dark:border-gray-800 dark:bg-gray-900/95">

        {{-- Home (active) --}}
        <a href="{{ \App\Filament\Casual\Pages\ClockPage::getUrl() }}"
           class="flex flex-1 flex-col items-center gap-1 py-3">
            <svg class="h-6 w-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                <path d="M11.47 3.841a.75.75 0 0 1 1.06 0l8.69 8.69a.75.75 0 1 0 1.06-1.061l-8.689-8.69a2.25 2.25 0 0 0-3.182 0l-8.69 8.69a.75.75 0 1 0 1.061 1.06l8.69-8.689Z"/>
                <path d="m12 5.432 8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 0 1-.75-.75v-4.5a.75.75 0 0 0-.75-.75h-3a.75.75 0 0 0-.75.75V21a.75.75 0 0 1-.75.75H5.625a1.875 1.875 0 0 1-1.875-1.875v-6.198a2.29 2.29 0 0 0 .091-.086L12 5.432Z"/>
            </svg>
            <span class="text-xs font-semibold text-blue-600">Beranda</span>
        </a>

        {{-- Attendance --}}
        <a href="{{ \App\Filament\Casual\Pages\AttendancePage::getUrl() }}"
           class="flex flex-1 flex-col items-center gap-1 py-3">
            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
            </svg>
            <span class="text-xs text-gray-400">Absensi</span>
        </a>

        {{-- Reports --}}
        <a href="{{ \App\Filament\Casual\Pages\ReportPage::getUrl() }}"
           class="flex flex-1 flex-col items-center gap-1 py-3">
            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
            </svg>
            <span class="text-xs text-gray-400">Laporan</span>
        </a>

        {{-- Profile --}}
        <a href="{{ \App\Filament\Casual\Pages\ProfilePage::getUrl() }}"
           class="flex flex-1 flex-col items-center gap-1 py-3">
            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
            </svg>
            <span class="text-xs text-gray-400">Profil</span>
        </a>

    </div>


</div>
