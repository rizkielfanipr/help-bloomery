@php
        $user      = auth()->user();
        $shift     = $user->casualShift;
        $record    = $this->todayRecord;
        $clockedIn = $record?->clock_in_at  !== null;
        $clockedOut= $record?->clock_out_at !== null;
    @endphp

    {{-- ════════════════════════════════════════════════════════
         ROOT — full-bleed mobile canvas
    ════════════════════════════════════════════════════════ --}}
    <div class="relative flex min-h-screen flex-col bg-gray-50 dark:bg-gray-950"
         x-data="{
             action: null,
             lat: @entangle('latitude'),
             lng: @entangle('longitude'),
             locStatus: 'idle',
             locError: null,
             photo: null,
             stream: null,
             uploading: false,
             camError: null,
             uploadProp: null,

             get hasPhoto()  { return !!this.photo; },
             get hasStream() { return !!this.stream; },

             open(which) {
                 this.action    = which;
                 this.photo     = null;
                 this.camError  = null;
                 this.uploading = false;
                 this.uploadProp = which === 'in' ? 'clockInPhoto' : 'clockOutPhoto';
                 this.$nextTick(() => this.startCamera());
                 if (this.locStatus === 'idle') this.detectLocation();
             },

             close() {
                 this.stopStream();
                 this.$wire.set('clockInPhoto', null);
                 this.$wire.set('clockOutPhoto', null);
                 this.photo  = null;
                 this.action = null;
             },

             async startCamera() {
                 this.camError = null;
                 if (!navigator.mediaDevices?.getUserMedia) {
                     this.camError = 'Kamera tidak tersedia (butuh HTTPS atau localhost).';
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
                     this.camError = 'Tidak dapat membuka kamera: ' + e.message;
                 }
             },

             shoot() {
                 const v = this.$refs.video, c = this.$refs.canvas;
                 if (!v || !c) return;
                 const s = Math.min(v.videoWidth, v.videoHeight) || 480;
                 c.width = c.height = s;
                 const ctx = c.getContext('2d');
                 ctx.save();
                 ctx.scale(-1, 1);
                 ctx.drawImage(v, -(v.videoWidth-s)/2-s, -(v.videoHeight-s)/2, v.videoWidth, v.videoHeight);
                 ctx.restore();
                 this.photo = c.toDataURL('image/jpeg', 0.85);
                 this.stopStream();
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
                 this.photo   = null;
                 this.camError= null;
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
                 if (this.action === 'in') {
                     this.$wire.clockIn();
                 } else {
                     this.$wire.clockOut();
                 }
                 this.stopStream();
                 this.action = null;
                 this.photo  = null;
             }
         }"
         @keydown.escape.window="close()">

        {{-- ── TODAY STATUS CARD ── --}}
        <div class="px-4 pt-4">
            <div class="overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                @if($clockedIn)
                    <div class="flex divide-x divide-gray-100 dark:divide-gray-800">
                        <div class="flex flex-1 items-center gap-3 px-4 py-4">
                            <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-green-100 dark:bg-green-900/30">
                                <svg class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
                                </svg>
                            </div>
                            <div>
                                <div class="text-xs font-medium text-green-600 dark:text-green-400">Clock In</div>
                                <div class="text-xl font-bold leading-tight text-gray-900 dark:text-white">{{ $record->clock_in_at->format('H:i') }}</div>
                                @if($record->is_late)
                                    <span class="mt-0.5 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">+{{ $record->late_minutes }}m</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-1 items-center gap-3 px-4 py-4">
                            @if($clockedOut)
                                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30">
                                    <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-blue-600 dark:text-blue-400">Clock Out</div>
                                    <div class="text-xl font-bold leading-tight text-gray-900 dark:text-white">{{ $record->clock_out_at->format('H:i') }}</div>
                                    @if($record->is_early_out)
                                        <span class="mt-0.5 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">-{{ $record->early_out_minutes }}m</span>
                                    @endif
                                </div>
                            @else
                                <div class="flex items-center gap-2 text-gray-400">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                                    </svg>
                                    <span class="text-sm">Belum Clock Out</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="flex items-center gap-3 px-4 py-4 text-gray-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                        </svg>
                        <span class="text-sm">Belum absensi hari ini</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── ACTION BUTTONS ── --}}
        <div class="flex flex-1 flex-col items-center justify-center gap-4 px-6 py-10">
            @if($clockedOut)
                <div class="flex flex-col items-center gap-3 text-center">
                    <div class="flex h-24 w-24 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                        <svg class="h-14 w-14 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800 dark:text-white">Absensi Selesai</h2>
                    <p class="text-sm text-gray-400">Kerja keras hari ini sudah tercatat. Sampai jumpa besok!</p>
                </div>

            @elseif(! $shift)
                <div class="flex flex-col items-center gap-3 text-center">
                    <div class="flex h-20 w-20 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
                        <svg class="h-10 w-10 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                        </svg>
                    </div>
                    <p class="font-semibold text-gray-700 dark:text-gray-300">Shift Belum Diatur</p>
                    <p class="text-sm text-gray-400">Hubungi HR Admin untuk mengatur shift Anda.</p>
                </div>

            @else
                @if(! $clockedIn)
                    <button @click="open('in')"
                            class="flex w-full items-center justify-center gap-3 rounded-2xl bg-green-500 py-5 text-lg font-bold text-white shadow-lg shadow-green-500/30 transition active:scale-95">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
                        </svg>
                        Clock In
                    </button>
                @endif

                @if($clockedIn && ! $clockedOut)
                    <button @click="open('out')"
                            class="flex w-full items-center justify-center gap-3 rounded-2xl bg-red-500 py-5 text-lg font-bold text-white shadow-lg shadow-red-500/30 transition active:scale-95">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15"/>
                        </svg>
                        Clock Out
                    </button>
                @endif
            @endif
        </div>

        {{-- ════════════════════════════════════════════════════════
             CAMERA OVERLAY
        ════════════════════════════════════════════════════════ --}}
        <div x-show="action !== null"
             x-transition:enter="transition duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex flex-col bg-black"
             style="display:none;">

            {{-- Top bar --}}
            <div class="flex items-center justify-between px-5 pb-4 pt-10">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-widest"
                         :class="action==='in' ? 'text-green-400' : 'text-red-400'"
                         x-text="action==='in' ? 'Clock In' : 'Clock Out'"></div>
                    <div x-data="{t:''}"
                         x-init="const p=v=>v.toString().padStart(2,'0'); setInterval(()=>{const n=new Date(); t=p(n.getHours())+':'+p(n.getMinutes())+':'+p(n.getSeconds());},1000)"
                         class="font-mono text-3xl font-bold text-white" x-text="t"></div>
                </div>
                <button @click="close()"
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Viewfinder --}}
            <div class="relative flex-1 overflow-hidden">

                <video x-ref="video" x-show="hasStream && !hasPhoto"
                       autoplay muted playsinline
                       class="absolute inset-0 h-full w-full object-cover"
                       style="transform:scaleX(-1)"></video>

                <div x-show="hasStream && !hasPhoto"
                     class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div class="h-64 w-64 rounded-full border-2 border-dashed border-white/50"
                         style="box-shadow:0 0 0 9999px rgba(0,0,0,.4)"></div>
                </div>

                <img x-show="hasPhoto" :src="photo"
                     class="absolute inset-0 h-full w-full object-cover" alt="">

                <div x-show="!hasStream && !hasPhoto && !camError"
                     class="absolute inset-0 flex flex-col items-center justify-center gap-3 text-white">
                    <svg class="h-10 w-10 animate-spin text-white/50" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span class="text-sm text-white/60">Membuka kamera...</span>
                </div>

                <div x-show="camError"
                     class="absolute inset-0 flex flex-col items-center justify-center gap-4 px-8 text-center text-white">
                    <svg class="h-12 w-12 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M12 18.75H4.5a2.25 2.25 0 0 1-2.25-2.25V9m12.841 9.091L16.5 19.5m-1.409-1.409c.407-.407.659-.97.659-1.591v-9a2.25 2.25 0 0 0-2.25-2.25h-9c-.621 0-1.184.252-1.591.659m12.182 12.182L2.909 5.909M1.5 4.5l1.409 1.409"/>
                    </svg>
                    <p class="text-sm text-white/70" x-text="camError"></p>
                    <button @click="startCamera()"
                            class="rounded-full bg-white/20 px-6 py-2 text-sm font-semibold transition hover:bg-white/30">
                        Coba Lagi
                    </button>
                </div>

                <div x-show="uploading"
                     class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-black/60 text-white">
                    <svg class="h-8 w-8 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span class="text-sm">Menyimpan foto...</span>
                </div>

                <div x-show="hasPhoto && !uploading"
                     class="absolute left-4 top-4 flex items-center gap-1.5 rounded-full bg-green-500 px-3 py-1 text-xs font-semibold text-white">
                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/>
                    </svg>
                    Foto siap
                </div>
            </div>

            <canvas x-ref="canvas" class="hidden"></canvas>

            {{-- Bottom controls --}}
            <div class="px-6 pb-12 pt-5">
                <div x-show="hasStream && !hasPhoto" class="flex justify-center">
                    <button @click="shoot()"
                            class="flex h-20 w-20 items-center justify-center rounded-full ring-4 ring-white/30 transition active:scale-90">
                        <div class="h-14 w-14 rounded-full bg-white"></div>
                    </button>
                </div>

                <div x-show="hasPhoto && !uploading" class="flex gap-3">
                    <button @click="retake()"
                            class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-white/10 py-4 text-sm font-semibold text-white transition hover:bg-white/20 active:scale-95">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                        </svg>
                        Ulang
                    </button>
                    <button @click="confirm()"
                            class="flex flex-1 items-center justify-center gap-2 rounded-2xl py-4 text-sm font-bold text-white transition active:scale-95"
                            :class="action==='in' ? 'bg-green-500 shadow-lg shadow-green-500/30' : 'bg-red-500 shadow-lg shadow-red-500/30'">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                        <span x-text="action==='in' ? 'Clock In' : 'Clock Out'"></span>
                    </button>
                </div>

                <p x-show="hasStream && !hasPhoto"
                   class="mt-4 text-center text-xs text-white/40">
                    Posisikan wajah dalam lingkaran, lalu tekan tombol
                </p>
            </div>
        </div>

    </div>
