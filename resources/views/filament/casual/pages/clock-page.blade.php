@php
    $user      = auth()->user();
    $branch    = $this->effectiveBranch;
    $record    = $this->todayRecord;
    $clockedIn = $record?->clock_in_at  !== null;
    $clockedOut= $record?->clock_out_at !== null;
    $stats     = $this->monthlyStats;
    $workHours = $this->workingHours;

    $registration  = $this->activeRegistration;
    $canCancelReg  = $this->canCancelRegistration;

    $firstName    = explode(' ', $user->name)[0];
    $monthName    = strtoupper(now()->locale('id')->isoFormat('MMM'));
    $unreadCount  = $this->unreadCount;

    $displayIn  = $clockedIn  ? $record->clock_in_at->format('H:i')  : '--:--';
    $displayOut = $clockedOut ? $record->clock_out_at->format('H:i') : '--:--';

    $branchLat    = $branch?->lat;
    $branchLng    = $branch?->lng;
    $branchRadius = $branch?->radius_meters ?? 100;
    $branchLocReq = ($branch?->location_required && $branch?->hasLocation()) ? 'true' : 'false';
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900"
     style="min-height:100dvh"
     x-data="{
         /* ── Modal state ────────────────── */
         action: null,   // 'in' | 'out' | null
         step: null,     // 'location' | 'camera'

         /* ── OT sheet state ─────────────── */
         showOtSheet: false,
         otDate: '{{ today()->format('Y-m-d') }}',
         otStart: '',
         otEnd: '',
         otReason: '',
         get otHoursCalc() {
             if (!this.otStart || !this.otEnd) return null;
             const [sh, sm] = this.otStart.split(':').map(Number);
             const [eh, em] = this.otEnd.split(':').map(Number);
             const totalMins = (eh * 60 + em) - (sh * 60 + sm);
             if (totalMins <= 0) return null;
             return totalMins / 60;
         },
         get otHoursDisplay() {
             const h = this.otHoursCalc;
             if (h === null) return null;
             const hours = Math.floor(h);
             const mins = Math.round((h - hours) * 60);
             return hours > 0 ? hours + 'j ' + mins + 'm' : mins + 'm';
         },

         /* ── Livewire bindings ──────────── */
         lat: @entangle('latitude'),
         lng: @entangle('longitude'),

         /* ── Branch config (from PHP) ─────── */
         shiftLat:     {{ $branchLat ?? 'null' }},
         shiftLng:     {{ $branchLng ?? 'null' }},
         shiftRadius:  {{ $branchRadius }},
         shiftLocReq:  {{ $branchLocReq }},

         /* ── Location state ─────────────── */
         locStatus: 'idle',  // idle | detecting | detected | error
         locError: null,
         locDistance: null,
         locInArea: false,

         /* ── Location map ────────────────── */
         locMap: null,
         locMarkerShift: null,
         locMarkerUser: null,
         locCircle: null,

         /* ── Camera state ────────────────── */
         photo: null,
         stream: null,
         mockMode: false,
         uploading: false,
         camError: null,
         uploadProp: null,
         faceModelReady: false,
         detectingFace: false,
         _faceModelPromise: null,
         faceToast: null,
         _faceToastTimer: null,

         get hasPhoto()  { return !!this.photo; },
         get hasStream() { return !!this.stream || this.mockMode; },

         /* ═══════════════════════════════════
            OPEN / CLOSE
         ═══════════════════════════════════ */
         open(which) {
             this.action     = which;
             this.photo      = null;
             this.camError   = null;
             this.mockMode   = false;
             this.uploading  = false;
             this.uploadProp = which === 'in' ? 'clockInPhoto' : 'clockOutPhoto';
             this.loadFaceModel();

             if (this.shiftLocReq && this.shiftLat && this.shiftLng) {
                 this.step = 'location';
                 this.detectLocation();
                 this.$nextTick(() => this.initLocationMap());
             } else {
                 this.step = 'camera';
                 this.$nextTick(() => this.startCamera());
                 if (this.locStatus === 'idle') this.detectLocation();
             }
         },

         close() {
             this.destroyLocationMap();
             this.stopStream();
             this.mockMode   = false;
             this.faceToast  = null;
             if (this._faceToastTimer) clearTimeout(this._faceToastTimer);
             this.$wire.set('clockInPhoto', null);
             this.$wire.set('clockOutPhoto', null);
             this.photo  = null;
             this.action = null;
             this.step   = null;
         },

         /* ═══════════════════════════════════
            LOCATION STEP
         ═══════════════════════════════════ */
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
                     this.calcDistance();
                     this.updateUserMarker();
                 },
                 (err) => {
                     this.locStatus = 'error';
                     this.locError  = err.message;
                 },
                 { enableHighAccuracy: true, timeout: 15000 }
             );
         },

         calcDistance() {
             if (!this.lat || !this.lng || !this.shiftLat || !this.shiftLng) return;
             this.locDistance = Math.round(this.haversine(this.lat, this.lng, this.shiftLat, this.shiftLng));
             this.locInArea   = this.locDistance <= this.shiftRadius;
         },

         haversine(lat1, lng1, lat2, lng2) {
             const R = 6371000;
             const dLat = (lat2 - lat1) * Math.PI / 180;
             const dLng = (lng2 - lng1) * Math.PI / 180;
             const a = Math.sin(dLat/2)**2
                 + Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLng/2)**2;
             return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
         },

         initLocationMap() {
             if (!window.L || !this.shiftLat || !this.shiftLng) return;
             const el = this.$refs.locationMap;
             if (!el) return;

             if (this.locMap) {
                 this.locMap.invalidateSize();
                 return;
             }

             setTimeout(() => {
                 this.locMap = L.map(el, { zoomControl: false }).setView(
                     [this.shiftLat, this.shiftLng], 16
                 );

                 L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                     attribution: '© OpenStreetMap'
                 }).addTo(this.locMap);

                 L.control.zoom({ position: 'bottomright' }).addTo(this.locMap);

                 // Shift zone circle
                 this.locCircle = L.circle([this.shiftLat, this.shiftLng], {
                     radius: this.shiftRadius,
                     color: '#3b82f6',
                     fillColor: '#3b82f6',
                     fillOpacity: 0.12,
                     weight: 2.5
                 }).addTo(this.locMap);

                 // Shift center pin
                 this.locMarkerShift = L.circleMarker([this.shiftLat, this.shiftLng], {
                     radius: 8, color: 'white', weight: 3,
                     fillColor: '#3b82f6', fillOpacity: 1
                 }).addTo(this.locMap).bindTooltip('Lokasi Kerja', {direction:'top'});

                 // If location already detected when map opens
                 if (this.lat && this.lng) this.updateUserMarker();

                 // Ensure tiles render after CSS transition completes
                 setTimeout(() => this.locMap && this.locMap.invalidateSize(), 320);
             }, 350);
         },

         updateUserMarker() {
             if (!this.locMap || !this.lat || !this.lng) return;
             this.calcDistance();
             const fillColor = this.locInArea ? '#10b981' : '#ef4444';

             if (this.locMarkerUser) {
                 this.locMarkerUser.setLatLng([this.lat, this.lng]);
                 this.locMarkerUser.setStyle({ fillColor });
             } else {
                 this.locMarkerUser = L.circleMarker([this.lat, this.lng], {
                     radius: 10, color: 'white', weight: 3,
                     fillColor, fillOpacity: 1
                 }).addTo(this.locMap).bindTooltip('Posisi Anda', {direction:'top'});

                 // Fit map to show both markers
                 try {
                     this.locMap.fitBounds([
                         [this.shiftLat, this.shiftLng],
                         [this.lat, this.lng]
                     ], { padding:[60,60], maxZoom:17 });
                 } catch(_){}
             }
         },

         destroyLocationMap() {
             if (this.locMap) {
                 this.locMap.remove();
                 this.locMap         = null;
                 this.locMarkerShift = null;
                 this.locMarkerUser  = null;
                 this.locCircle      = null;
             }
         },

         proceedToCamera() {
             this.destroyLocationMap();
             this.step = 'camera';
             this.$nextTick(() => this.startCamera());
         },

         /* ═══════════════════════════════════
            CAMERA STEP
         ═══════════════════════════════════ */
         loadFaceModel() {
             if (this.faceModelReady) return Promise.resolve();
             if (this._faceModelPromise) return this._faceModelPromise;
             if (!window.faceapi) return Promise.resolve();
             this._faceModelPromise = faceapi.nets.tinyFaceDetector
                 .loadFromUri('/vendor/face-api')
                 .then(() => { this.faceModelReady = true; })
                 .catch(e => { console.warn('Face model load failed:', e); });
             return this._faceModelPromise;
         },

         showFaceToast(type, message) {
             if (this._faceToastTimer) clearTimeout(this._faceToastTimer);
             this.faceToast = { type, message };
             const duration = type === 'success' ? 2000 : 4000;
             this._faceToastTimer = setTimeout(() => { this.faceToast = null; }, duration);
         },

         async startCamera() {
             this.camError = null;
             this.mockMode = false;
             if (!window.isSecureContext) {
                 this.camError = 'Kamera memerlukan koneksi HTTPS. Silakan hubungi admin untuk mengaktifkan HTTPS.';
                 return;
             }
             if (!navigator.mediaDevices?.getUserMedia) {
                 this.camError = 'Browser tidak mendukung akses kamera.';
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
                 if (e.name === 'NotAllowedError' || e.name === 'PermissionDeniedError') {
                     this.camError = 'Akses kamera ditolak. Silakan izinkan akses kamera melalui pengaturan browser dan coba kembali.';
                 } else if (e.name === 'NotFoundError' || e.name === 'DevicesNotFoundError') {
                     this.camError = 'Kamera tidak ditemukan di perangkat ini.';
                 } else {
                     this.camError = 'Kamera tidak dapat dibuka: ' + e.message;
                 }
             }
         },

         async shoot() {
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

             if (!this.mockMode && this.faceModelReady) {
                 this.detectingFace = true;
                 try {
                     const detection = await faceapi.detectSingleFace(
                         c, new faceapi.TinyFaceDetectorOptions({ scoreThreshold: 0.4 })
                     );
                     this.detectingFace = false;
                     if (!detection) {
                         this.photo = null;
                         this.showFaceToast('error', 'Wajah tidak terdeteksi. Pastikan wajah Anda berada di dalam bingkai oval dengan pencahayaan yang memadai.');
                         this.startCamera();
                         return;
                     }
                     this.showFaceToast('success', 'Wajah berhasil dideteksi.');
                 } catch(e) {
                     this.detectingFace = false;
                 }
             }

             const blob = await new Promise(resolve => c.toBlob(resolve, 'image/jpeg', 0.85));
             const file = new File([blob], 'selfie.jpg', { type: 'image/jpeg' });
             this.uploading = true;
             this.$wire.upload(this.uploadProp, file,
                 ()  => { this.uploading = false; },
                 ()  => { this.uploading = false; this.camError = 'Gagal mengunggah foto. Silakan coba kembali.'; }
             );
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

         confirm() {
             if (this.action === 'in') { this.$wire.clockIn(); } else { this.$wire.clockOut(); }
             this.stopStream();
             this.action = null;
             this.step   = null;
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
                    {{ $branch?->name ?? 'Lokasi Kerja' }}
                </span>
            </div>
            <div class="flex items-center gap-2">
            {{-- Dark mode toggle --}}
            <button
                type="button"
                class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 text-white transition active:bg-white/25"
                @click="
                    const isDark = document.documentElement.classList.contains('dark');
                    window.dispatchEvent(new CustomEvent('theme-changed', { detail: isDark ? 'light' : 'dark' }));
                "
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/>
                </svg>
            </button>

            <a href="{{ \App\Filament\Casual\Pages\NotificationPage::getUrl() }}"
               class="relative flex h-9 w-9 items-center justify-center rounded-full bg-white/15 text-white transition active:bg-white/25">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                </svg>
                @if($unreadCount > 0)
                    <span class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-semibold text-white">
                        {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                    </span>
                @endif
            </a>
            </div>
        </div>

        {{-- Greeting --}}
        <div class="mt-5">
            <p class="text-sm font-medium text-blue-200">{{ now()->locale('id')->isoFormat('dddd, D MMMM YYYY') }}</p>
            <h1 class="mt-0.5 text-xl font-semibold text-white">Selamat Datang, {{ $user->name }}</h1>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        {{-- ══════════════════════════
             STATE: NO REGISTRATION
        ══════════════════════════ --}}
        @if(! $registration)
            <div class="mx-5 mt-4 overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-col items-center gap-4 px-5 py-10 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-900/20">
                        <svg class="h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-white">Belum Terdaftar Lowongan</p>
                        <p class="mt-1 text-sm text-gray-400">Silakan pilih lowongan yang tersedia untuk mendaftar.</p>
                    </div>
                    <a href="{{ \App\Filament\Casual\Pages\PositionsPage::getUrl() }}"
                       class="flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition active:scale-95 active:bg-blue-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                        </svg>
                        Lihat Lowongan
                    </a>
                </div>
            </div>

        @else

        {{-- ══════════════════════════
             STATE: CLOCK IN / OUT
        ══════════════════════════ --}}
        <div class="mx-5 mt-4 overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">

            {{-- Registration info + status --}}
            <div class="flex items-center justify-between px-5 pt-5">
                <span class="rounded-full bg-teal-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-teal-700 dark:bg-teal-900/30 dark:text-teal-400">
                    {{ strtoupper($registration->opening->casualPosition->name) }}
                </span>

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
                     class="font-mono text-[1.9rem] font-semibold tracking-tight text-gray-900 dark:text-white"
                     x-text="t">
                </div>

                @if(! $clockedIn)
                    <button @click="open('in')"
                            class="flex items-center gap-1.5 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition active:scale-95">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
                        </svg>
                        Clock In
                    </button>
                @elseif($clockedIn && ! $clockedOut)
                    <button @click="open('out')"
                            class="flex items-center gap-1.5 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white transition active:scale-95">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15"/>
                        </svg>
                        Clock Out
                    </button>
                @elseif($clockedOut)
                    <div class="flex items-center gap-1.5 rounded-xl bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-600 dark:bg-green-900/20 dark:text-green-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                        Selesai
                    </div>
                @else
                    <div class="flex items-center gap-1.5 rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-400 dark:bg-gray-800 dark:text-gray-600">
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
                    <p class="font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ $displayIn }}</p>
                    <p class="text-center text-xs leading-tight text-gray-400">Jam Masuk</p>
                </div>

                {{-- Check Out --}}
                <div class="flex flex-1 flex-col items-center gap-1.5 px-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-900/20">
                        <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15"/>
                        </svg>
                    </div>
                    <p class="font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ $displayOut }}</p>
                    <p class="text-center text-xs leading-tight text-gray-400">Jam Keluar</p>
                </div>

                {{-- Working Hours --}}
                <div class="flex flex-1 flex-col items-center gap-1.5 px-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-900/20">
                        <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                        </svg>
                    </div>
                    <p class="font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ $workHours }}</p>
                    <p class="text-center text-xs leading-tight text-gray-400">Jam Kerja</p>
                </div>

            </div>
        </div>

        @endif {{-- end @else (has registration) --}}

        {{-- ── WhatsApp contact (shown if leader has phone) ── --}}
        @if($registration?->opening?->postedBy?->phone)
            @php $leader = $registration->opening->postedBy; @endphp
            <div class="mx-5 mt-4">
                <a href="{{ $this->getWhatsappUrl() }}"
                   target="_blank"
                   class="flex w-full items-center justify-center gap-2 rounded-2xl bg-green-500 py-4 text-sm font-bold text-white shadow-sm transition active:scale-95 active:bg-green-600">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/>
                    </svg>
                    Hubungi via WhatsApp
                </a>
            </div>
        @endif

        {{-- ── Monthly attendance section ── --}}
        <div class="mx-5 mt-5">
            <div class="flex items-center justify-between">
                <p class="font-semibold text-gray-900 dark:text-white">Absensi Bulan Ini</p>
                <div class="flex items-center gap-1.5 rounded-lg bg-gray-200/70 px-3 py-1.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                    </svg>
                    {{ $monthName }}
                </div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-3">
                <div class="flex flex-col items-center gap-1.5 rounded-2xl bg-green-50 py-4 dark:bg-green-900/20">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Hadir</p>
                    <p class="text-3xl font-semibold text-green-600 dark:text-green-400">{{ $stats['present'] }}</p>
                </div>
                <div class="flex flex-col items-center gap-1.5 rounded-2xl bg-red-50 py-4 dark:bg-red-900/20">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Absen</p>
                    <p class="text-3xl font-semibold text-red-500 dark:text-red-400">{{ $stats['absent'] }}</p>
                </div>
            </div>
        </div>

        {{-- ── Request button ── --}}
        <div class="mx-5 mt-4">
            <button @click="showOtSheet = true; otDate = '{{ today()->format('Y-m-d') }}'"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-gray-200 py-4 text-sm font-semibold text-gray-500 transition active:bg-gray-100 dark:border-gray-700 dark:text-gray-400 dark:active:bg-gray-800">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Ajukan Lembur
            </button>
        </div>

    </div>

    {{-- ════════════════════════════════════════════
         FULL-SCREEN OVERLAY (location + camera)
    ════════════════════════════════════════════ --}}
    <div x-show="action !== null"
         x-transition:enter="transition duration-300 ease-out"
         x-transition:enter-start="opacity-0 scale-[1.02]"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition duration-200 ease-in"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-[1.02]"
         class="fixed inset-0 z-50 overflow-hidden"
         style="display:none">

        {{-- ══════════════════════════════════════
             STEP 1: LOCATION VERIFICATION
        ══════════════════════════════════════ --}}
        <div x-show="step === 'location'"
             class="absolute inset-0"
             style="display:none">

            {{-- Map fills entire screen --}}
            <div x-ref="locationMap" wire:ignore
                 class="absolute inset-0 z-0 bg-gray-950"></div>

            {{-- TOP BAR — solid blue --}}
            <div class="absolute inset-x-0 top-0 z-20 rounded-b-3xl bg-blue-600 px-5 pb-5 pt-12 dark:bg-blue-900">
                <div class="flex items-center justify-between">

                    <button @click="close()"
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white transition active:bg-white/25">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                        </svg>
                    </button>

                    <div class="text-center">
                        <p class="font-semibold text-white">Verifikasi Lokasi</p>
                        <p class="text-xs font-medium"
                           :class="action === 'in' ? 'text-blue-200' : 'text-orange-300'"
                           x-text="action === 'in' ? 'Clock In' : 'Clock Out'"></p>
                    </div>

                    {{-- Refresh location button --}}
                    <button @click="locStatus = 'idle'; detectLocation(); updateUserMarker()"
                            :disabled="locStatus === 'detecting'"
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white transition active:bg-white/25 disabled:opacity-40">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                             :class="locStatus === 'detecting' ? 'animate-spin' : ''">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                        </svg>
                    </button>

                </div>
            </div>

            {{-- BOTTOM CONTROLS --}}
            <div class="absolute inset-x-0 bottom-0 z-20 space-y-3 px-5 pb-12 pt-6">

                {{-- Status card (glass style over dark gradient) --}}
                <div x-show="locStatus !== 'idle'"
                     class="flex items-center gap-3.5 rounded-2xl px-4 py-3.5 ring-1 transition-colors"
                     :class="locStatus === 'detecting'
                         ? 'bg-gray-800 ring-gray-700'
                         : locInArea
                             ? 'bg-emerald-900 ring-emerald-700'
                             : 'bg-red-900 ring-red-700'">

                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                         :class="locStatus === 'detecting' ? 'bg-gray-700' :
                                 locInArea ? 'bg-emerald-800' : 'bg-red-800'">
                        <template x-if="locStatus === 'detecting'">
                            <svg class="h-5 w-5 animate-spin text-white/60" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </template>
                        <template x-if="locStatus === 'detected' && locInArea">
                            <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                        </template>
                        <template x-if="locStatus === 'detected' && !locInArea">
                            <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                            </svg>
                        </template>
                        <template x-if="locStatus === 'error'">
                            <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                            </svg>
                        </template>
                    </div>

                    <div class="min-w-0 flex-1">
                        <template x-if="locStatus === 'detecting'">
                            <p class="text-sm font-medium text-white/70">Mendeteksi posisi GPS...</p>
                        </template>
                        <template x-if="locStatus === 'detected' && locInArea">
                            <div>
                                <p class="text-sm font-semibold text-emerald-400">Dalam area kerja</p>
                                <p class="mt-0.5 text-xs text-emerald-400/60"
                                   x-text="'Jarak: ' + locDistance + 'm · Radius: {{ $branchRadius }}m'"></p>
                            </div>
                        </template>
                        <template x-if="locStatus === 'detected' && !locInArea">
                            <div>
                                <p class="text-sm font-semibold text-red-400">Di luar area kerja</p>
                                <p class="mt-0.5 text-xs text-red-400/60"
                                   x-text="'Jarak: ' + locDistance + 'm · Radius: {{ $branchRadius }}m'"></p>
                            </div>
                        </template>
                        <template x-if="locStatus === 'error'">
                            <div>
                                <p class="text-sm font-semibold text-red-400">GPS tidak terdeteksi</p>
                                <p class="mt-0.5 text-xs text-red-400/60" x-text="locError"></p>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Proceed button --}}
                <button
                    @click="proceedToCamera()"
                    :disabled="locStatus !== 'detected' || !locInArea"
                    class="relative w-full rounded-2xl py-4 text-sm font-semibold text-white transition active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
                    :class="(locStatus === 'detected' && locInArea)
                        ? (action === 'in' ? 'bg-blue-600 active:bg-blue-700' : 'bg-orange-500 active:bg-orange-600')
                        : 'bg-gray-700'"
                    style="box-shadow: none"
                >
                    <span x-show="locStatus === 'idle' || locStatus === 'detecting'">Mendeteksi lokasi...</span>
                    <span x-show="locStatus === 'detected' && locInArea" class="flex items-center justify-center gap-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z"/>
                        </svg>
                        Lanjutkan
                    </span>
                    <span x-show="locStatus === 'detected' && !locInArea">Di Luar Area Kerja</span>
                    <span x-show="locStatus === 'error'">Lokasi Tidak Tersedia</span>
                </button>

            </div>
        </div>

        {{-- ══════════════════════════════════════
             STEP 2: FACE DETECTION CAMERA
        ══════════════════════════════════════ --}}
        <div x-show="step === 'camera'"
             class="absolute inset-0 bg-black"
             style="display:none">

            {{-- Camera feed --}}
            <video x-ref="video"
                   x-show="hasStream && !hasPhoto && !mockMode"
                   autoplay muted playsinline
                   class="absolute inset-0 h-full w-full object-cover"
                   style="transform:scaleX(-1)"></video>

            {{-- Mock background --}}
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

            <div x-show="mockMode && !hasPhoto"
                 class="absolute right-5 top-36 z-20 rounded-full bg-yellow-500/80 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-black backdrop-blur-sm">
                Mode Test
            </div>

            {{-- Captured photo --}}
            <img x-show="hasPhoto" :src="photo"
                 class="absolute inset-0 h-full w-full object-cover" alt="">

            {{-- Loading state --}}
            <div x-show="!hasStream && !hasPhoto && !camError"
                 class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-gray-950">
                <svg class="h-10 w-10 animate-spin text-white/30" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <p class="text-sm text-white/40">Membuka kamera...</p>
            </div>

            {{-- Camera init error --}}
            <div x-show="camError && !hasPhoto"
                 class="absolute inset-0 flex flex-col items-center justify-center gap-5 bg-gray-950 px-8 text-center">
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-red-500/20">
                    <svg class="h-10 w-10 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M12 18.75H4.5a2.25 2.25 0 0 1-2.25-2.25V9m12.841 9.091L16.5 19.5m-1.409-1.409c.407-.407.659-.97.659-1.591v-9a2.25 2.25 0 0 0-2.25-2.25h-9c-.621 0-1.184.252-1.591.659m12.182 12.182L2.909 5.909M1.5 4.5l1.409 1.409"/>
                    </svg>
                </div>
                <p class="text-sm leading-relaxed text-white/80" x-text="camError"></p>
                <button @click="camError = null; startCamera()"
                        class="rounded-full bg-white/20 px-6 py-2.5 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/30 active:scale-95">
                    Coba Lagi
                </button>
            </div>

            {{-- Face oval guide --}}
            <div x-show="hasStream && !hasPhoto"
                 class="pointer-events-none absolute inset-0 flex items-center justify-center"
                 style="padding-bottom: 8vh">
                <div class="relative" style="width: min(68vw, 280px); aspect-ratio: 3/4;">
                    <div class="face-oval-ring absolute inset-0 rounded-[50%]"
                         style="border: 2.5px solid rgba(255,255,255,0.85); box-shadow: 0 0 0 9999px rgba(0,0,0,0.52);"></div>
                    <div class="absolute inset-0 overflow-hidden rounded-[50%]">
                        <div class="face-scan-line absolute inset-x-0 h-px"
                             style="background: linear-gradient(to right, transparent, rgba(59,130,246,0.9), transparent);
                                    box-shadow: 0 0 8px 2px rgba(59,130,246,0.6);"></div>
                    </div>
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

            {{-- TOP BAR --}}
            <div class="absolute inset-x-0 top-0 z-10 rounded-b-3xl bg-blue-600 px-5 pb-5 pt-12 dark:bg-blue-900">
                <div class="flex items-center justify-between">

                    {{-- Back button (goes back to location step if required, else closes) --}}
                    <button @click="shiftLocReq && shiftLat ? (destroyLocationMap(), step = 'location', $nextTick(() => initLocationMap())) : close()"
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white backdrop-blur-sm transition active:bg-white/25">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                        </svg>
                    </button>

                    <div class="text-center">
                        <p class="font-semibold text-white">Deteksi Wajah</p>
                        <p class="text-xs font-medium"
                           :class="action === 'in' ? 'text-blue-200' : 'text-orange-300'"
                           x-text="action === 'in' ? 'Clock In' : 'Clock Out'"></p>
                    </div>

                    <div class="h-10 w-10"></div>
                </div>
            </div>

            {{-- Camera error (upload fail) --}}
            <div x-show="camError"
                 class="absolute bottom-36 inset-x-0 z-20 mx-6 flex items-center gap-3 rounded-2xl bg-red-500/90 px-4 py-3 backdrop-blur-sm">
                <svg class="h-5 w-5 flex-shrink-0 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                </svg>
                <span class="flex-1 text-sm font-medium text-white" x-text="camError"></span>
                <button @click="camError = null; retake()"
                        class="text-xs font-semibold text-white/80 underline">Ulang</button>
            </div>

            {{-- Face detection toast --}}
            <div x-show="faceToast"
                 x-transition:enter="transition duration-300 ease-out"
                 x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition duration-200 ease-in"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
                 class="absolute inset-x-4 top-28 z-50 flex items-start gap-3 rounded-2xl px-4 py-3.5 backdrop-blur-md"
                 :class="faceToast?.type === 'success' ? 'bg-green-500/95' : 'bg-red-500/95'"
                 style="display:none">
                <div class="mt-0.5 flex-shrink-0">
                    <template x-if="faceToast?.type === 'success'">
                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-white/25">
                            <svg class="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </template>
                    <template x-if="faceToast?.type === 'error'">
                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-white/25">
                            <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                            </svg>
                        </div>
                    </template>
                </div>
                <p class="flex-1 text-sm font-medium leading-snug text-white" x-text="faceToast?.message"></p>
            </div>

            {{-- Detecting face overlay --}}
            <div x-show="detectingFace"
                 class="absolute inset-0 z-30 flex flex-col items-center justify-center gap-4 bg-black/60 backdrop-blur-sm">
                <div class="relative flex h-20 w-20 items-center justify-center">
                    <svg class="absolute inset-0 h-20 w-20 animate-spin text-blue-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-white">Mendeteksi wajah...</p>
            </div>

            {{-- Upload overlay --}}
            <div x-show="uploading"
                 class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-black/70 backdrop-blur-sm">
                <svg class="h-10 w-10 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <p class="text-sm font-medium text-white/80">Menyimpan foto...</p>
            </div>

            <canvas x-ref="canvas" class="hidden"></canvas>

            {{-- BOTTOM CONTROLS --}}
            <div class="absolute inset-x-0 bottom-0 z-10 px-6 pb-14 pt-8"
                 style="background: linear-gradient(to top, rgba(0,0,0,0.75) 0%, transparent 100%)">

                <p x-show="hasStream && !hasPhoto"
                   class="mb-7 text-center text-sm font-medium text-white/70">
                    Posisikan wajah dalam bingkai oval, lalu tekan tombol
                </p>

                <div x-show="hasStream && !hasPhoto"
                     x-data="{ t: '' }"
                     x-init="const p = v => v.toString().padStart(2,'0'); setInterval(() => { const n = new Date(); t = p(n.getHours()) + ':' + p(n.getMinutes()) + ':' + p(n.getSeconds()); }, 1000)"
                     class="mb-5 text-center font-mono text-2xl font-semibold text-white/90"
                     x-text="t">
                </div>

                <div x-show="hasStream && !hasPhoto" class="flex justify-center">
                    <button @click="shoot()"
                            class="relative flex h-20 w-20 items-center justify-center rounded-full ring-4 ring-white/30 transition active:scale-90">
                        <div class="h-[3.75rem] w-[3.75rem] rounded-full bg-white"></div>
                    </button>
                </div>

                <div x-show="hasPhoto && !uploading" class="flex gap-3">
                    <button @click="retake()"
                            class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-white/15 py-4 text-sm font-semibold text-white backdrop-blur-sm transition active:bg-white/25 active:scale-95">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                        </svg>
                        Ulang
                    </button>
                    <button @click="confirm()"
                            :class="action === 'in' ? 'bg-blue-600' : 'bg-orange-500'"
                            class="flex flex-1 items-center justify-center gap-2 rounded-2xl py-4 text-sm font-semibold text-white transition active:scale-95">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                        <span x-text="action === 'in' ? 'Clock In' : 'Clock Out'"></span>
                    </button>
                </div>

            </div>
        </div>{{-- end step camera --}}

    </div>{{-- end overlay --}}

    <x-casual.bottom-nav active="clock" />

    {{-- ════════════════════════════════════════════
         OT REQUEST BOTTOM SHEET
    ════════════════════════════════════════════ --}}

    {{-- Backdrop --}}
    <div x-show="showOtSheet"
         x-transition:enter="transition duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40 bg-black/60"
         style="display:none"
         @click="showOtSheet = false">
    </div>

    {{-- Sheet --}}
    <div x-show="showOtSheet"
         x-transition:enter="transition duration-300 ease-out"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition duration-200 ease-in"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full"
         class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] z-50 overflow-hidden rounded-t-3xl bg-white dark:bg-gray-900"
         style="max-height:88vh; display:none"
         @click.stop>

        {{-- Drag handle --}}
        <div class="flex justify-center pb-2 pt-3">
            <div class="h-1 w-10 rounded-full bg-gray-200 dark:bg-gray-700"></div>
        </div>

        <div class="overflow-y-auto pb-10 px-5" style="max-height:calc(88vh - 28px)">

            {{-- Header --}}
            <div class="flex items-center justify-between pb-4 pt-2">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white">Request Lembur</p>
                    <p class="mt-0.5 text-xs text-gray-400">Ajukan permohonan lembur</p>
                </div>
                <button @click="showOtSheet = false"
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition active:bg-gray-200 dark:bg-gray-800 dark:text-gray-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Form --}}
            <div class="space-y-4">

                {{-- Tanggal --}}
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal Lembur</label>
                    <input type="date"
                           x-model="otDate"
                           :max="'{{ today()->format('Y-m-d') }}'"
                           class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                </div>

                {{-- Jam Mulai & Selesai --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Jam Mulai</label>
                        <input type="time"
                               x-model="otStart"
                               class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Jam Selesai</label>
                        <input type="time"
                               x-model="otEnd"
                               class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>
                </div>

                {{-- Akumulasi jam --}}
                <template x-if="otHoursDisplay !== null">
                    <div class="flex items-center gap-2.5 rounded-xl bg-indigo-50 px-4 py-3 dark:bg-indigo-900/20">
                        <svg class="h-4 w-4 text-indigo-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <div>
                            <p class="text-[11px] text-indigo-400">Akumulasi Lembur</p>
                            <p class="text-sm font-semibold text-indigo-700 dark:text-indigo-300" x-text="otHoursDisplay"></p>
                        </div>
                    </div>
                </template>
                <template x-if="otStart && otEnd && otHoursDisplay === null">
                    <div class="flex items-center gap-2 rounded-xl bg-red-50 px-4 py-3 dark:bg-red-900/20">
                        <svg class="h-4 w-4 text-red-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                        </svg>
                        <p class="text-xs font-medium text-red-500">Jam selesai harus setelah jam mulai</p>
                    </div>
                </template>

                {{-- Alasan --}}
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alasan Lembur</label>
                    <textarea x-model="otReason"
                              rows="3"
                              placeholder="Jelaskan alasan pengajuan lembur..."
                              class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"></textarea>
                </div>

                {{-- Submit --}}
                <button type="button"
                        :disabled="!otDate || !otStart || !otEnd || !otReason.trim() || otHoursDisplay === null"
                        @click="$wire.submitOvertimeRequest(otDate, otStart, otEnd, otReason).then(() => { showOtSheet = false; otStart = ''; otEnd = ''; otReason = '' })"
                        class="w-full rounded-xl bg-blue-600 py-4 text-sm font-semibold text-white transition active:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                    Kirim Request Lembur
                </button>

            </div>

        </div>
    </div>

</div>

