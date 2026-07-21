<div
     x-data="{
         checkinId: @entangle('activeCheckinId'),
         lat:       @entangle('checkinLat'),
         lng:       @entangle('checkinLng'),
         mode:      'idle',
         source:    null,
         photo:     null,
         stream:    null,
         uploading: false,
         camError:  null,
         locStatus: 'idle',
         facingMode: 'environment',

         toggleCamera() {
             this.facingMode = this.facingMode === 'environment' ? 'user' : 'environment';
             this.stopStream();
             if (this.mode === 'camera') this.startCamera();
             if (this.mode === 'odo-camera') this.startOdoCamera();
         },

         /* ── odo state ── */
         odoCtx:    'start',   /* 'start' | 'end' */
         odoKm:     '',
         odoPhoto:  null,
         odoUploading: false,

         get hasPhoto()  { return !!this.photo; },
         get hasStream() { return !!this.stream; },

         openOdo(ctx) {
             this.odoCtx    = ctx;
             this.odoKm     = '';
             this.odoPhoto  = null;
             this.odoUploading = false;
             this.source    = null;
             this.camError  = null;
             this.mode      = 'odo-picker';
         },

         closeOdo() {
             this.stopStream();
             this.$wire.set(this.odoCtx === 'start' ? 'odoStartPhoto' : 'odoEndPhoto', null);
             this.odoPhoto  = null;
             this.odoKm     = '';
             this.mode      = 'idle';
         },

         odoChooseCamera() {
             this.source   = 'camera';
             this.mode     = 'odo-camera';
             this.odoPhoto = null;
             this.camError = null;
             this.$nextTick(() => this.startOdoCamera());
         },

         odoChooseGallery() {
             this.mode = 'idle';
             this.$nextTick(() => this.$refs.odoFileInput.click());
         },

         async onOdoFilePicked(event) {
             const file = event.target.files[0];
             if (!file) return;
             this.source       = 'gallery';
             this.mode         = 'odo-camera';
             this.odoUploading = true;
             await this.$nextTick();
             const c   = this.$refs.odoCanvas;
             const url = URL.createObjectURL(file);
             const img = new Image();
             img.onload = () => {
                 c.width  = img.naturalWidth;
                 c.height = img.naturalHeight;
                 const ctx2 = c.getContext('2d');
                 ctx2.drawImage(img, 0, 0);
                 URL.revokeObjectURL(url);
                 const now = new Date();
                 const pad = (n) => n.toString().padStart(2,'0');
                 const ts  = pad(now.getDate()) + '/' + pad(now.getMonth()+1) + '/' + now.getFullYear()
                           + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
                 const bh  = 52;
                 ctx2.fillStyle = 'rgba(0,0,0,0.60)';
                 ctx2.fillRect(0, c.height - bh, c.width, bh);
                 ctx2.fillStyle    = '#fff';
                 ctx2.font         = 'bold 22px monospace';
                 ctx2.textAlign    = 'center';
                 ctx2.textBaseline = 'middle';
                 ctx2.fillText(ts, c.width / 2, c.height - bh / 2);
                 this.odoPhoto = c.toDataURL('image/jpeg', 0.85);
                 c.toBlob((blob) => {
                     const stamped = new File([blob], 'odo.jpg', { type: 'image/jpeg' });
                     const prop = this.odoCtx === 'start' ? 'odoStartPhoto' : 'odoEndPhoto';
                     this.$wire.upload(prop, stamped,
                         () => { this.odoUploading = false; },
                         () => { this.odoUploading = false; }
                     );
                 }, 'image/jpeg', 0.85);
             };
             img.src = url;
             event.target.value = '';
         },

         async startOdoCamera() {
             this.camError = null;
             if (!navigator.mediaDevices?.getUserMedia) {
                 this.camError = 'Kamera tidak tersedia. Diperlukan koneksi HTTPS.';
                 return;
             }
             try {
                 this.stream = await navigator.mediaDevices.getUserMedia({
                     video: { facingMode: this.facingMode, width: { ideal: 1280 }, height: { ideal: 960 } },
                     audio: false
                 });
                 await this.$nextTick();
                 const v = this.$refs.odoVideo;
                 if (v) { v.srcObject = this.stream; await v.play(); }
             } catch(e) {
                 this.camError = 'Kamera tidak dapat dibuka: ' + e.message;
             }
         },

         shootOdo() {
             const v = this.$refs.odoVideo, c = this.$refs.odoCanvas;
             if (!v || !c) return;
             c.width  = v.videoWidth  || 1280;
             c.height = v.videoHeight || 960;
             const ctx2 = c.getContext('2d');
             ctx2.drawImage(v, 0, 0, c.width, c.height);
             const now = new Date();
             const pad = (n) => n.toString().padStart(2,'0');
             const ts  = pad(now.getDate()) + '/' + pad(now.getMonth()+1) + '/' + now.getFullYear()
                       + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
             const bh  = 52;
             ctx2.fillStyle = 'rgba(0,0,0,0.60)';
             ctx2.fillRect(0, c.height - bh, c.width, bh);
             ctx2.fillStyle    = '#fff';
             ctx2.font         = 'bold 22px monospace';
             ctx2.textAlign    = 'center';
             ctx2.textBaseline = 'middle';
             ctx2.fillText(ts, c.width / 2, c.height - bh / 2);
             this.odoPhoto = c.toDataURL('image/jpeg', 0.85);
             this.stopStream();
             c.toBlob((blob) => {
                 const file = new File([blob], 'odo.jpg', { type: 'image/jpeg' });
                 this.odoUploading = true;
                 const prop = this.odoCtx === 'start' ? 'odoStartPhoto' : 'odoEndPhoto';
                 this.$wire.upload(prop, file,
                     () => { this.odoUploading = false; },
                     () => { this.odoUploading = false; this.camError = 'Gagal mengunggah foto.'; }
                 );
             }, 'image/jpeg', 0.85);
         },

         odoRetake() {
             const prop = this.odoCtx === 'start' ? 'odoStartPhoto' : 'odoEndPhoto';
             this.$wire.set(prop, null);
             this.odoPhoto = null;
             this.camError = null;
             if (this.source === 'gallery') {
                 this.mode = 'odo-picker';
             } else {
                 this.startOdoCamera();
             }
         },

         odoBackFromCamera() {
             if (this.odoPhoto) {
                 this.odoRetake();
             } else {
                 this.stopStream();
                 this.mode = 'odo-picker';
             }
         },

         confirmOdoPhoto() {
             this.stopStream();
             this.mode = 'odo-picker';
         },

         submitOdo() {
             const km = parseInt(this.odoKm);
             if (!km || km <= 0) return;
             if (this.odoCtx === 'start') {
                 this.$wire.saveOdoStart(km);
             } else {
                 this.$wire.saveOdoEnd(km);
             }
             this.mode     = 'idle';
             this.odoKm    = '';
             this.odoPhoto = null;
         },

         openCheckin(id) {
             this.checkinId = id;
             this.photo     = null;
             this.camError  = null;
             this.uploading = false;
             this.source    = null;
             this.mode      = 'picker';
             this.detectLocation();
         },

         closeCheckin() {
             this.stopStream();
             this.$wire.set('checkinPhoto', null);
             this.photo     = null;
             this.mode      = 'idle';
             this.checkinId = null;
             this.lat       = null;
             this.lng       = null;
             this.locStatus = 'idle';
         },

         chooseCamera() {
             this.source   = 'camera';
             this.mode     = 'camera';
             this.photo    = null;
             this.camError = null;
             this.$nextTick(() => this.startCamera());
         },

         chooseGallery() {
             this.mode = 'idle';
             this.$nextTick(() => this.$refs.fileInput.click());
         },

         async onFilePicked(event) {
             const file = event.target.files[0];
             if (!file) return;
             this.source    = 'gallery';
             this.mode      = 'camera';
             this.uploading = true;
             await this.$nextTick();
             const c   = this.$refs.canvas;
             const url = URL.createObjectURL(file);
             const img = new Image();
             img.onload = () => {
                 c.width  = img.naturalWidth;
                 c.height = img.naturalHeight;
                 const ctx = c.getContext('2d');
                 ctx.drawImage(img, 0, 0);
                 URL.revokeObjectURL(url);
                 const now = new Date();
                 const pad = (n) => n.toString().padStart(2,'0');
                 const ts  = pad(now.getDate()) + '/' + pad(now.getMonth()+1) + '/' + now.getFullYear()
                           + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
                 const bh  = 52;
                 ctx.fillStyle = 'rgba(0,0,0,0.60)';
                 ctx.fillRect(0, c.height - bh, c.width, bh);
                 ctx.fillStyle    = '#fff';
                 ctx.font         = 'bold 22px monospace';
                 ctx.textAlign    = 'center';
                 ctx.textBaseline = 'middle';
                 ctx.fillText(ts, c.width / 2, c.height - bh / 2);
                 this.photo = c.toDataURL('image/jpeg', 0.85);
                 c.toBlob((blob) => {
                     const stamped = new File([blob], 'checkin.jpg', { type: 'image/jpeg' });
                     this.$wire.upload('checkinPhoto', stamped,
                         () => { this.uploading = false; },
                         () => { this.uploading = false; }
                     );
                 }, 'image/jpeg', 0.85);
             };
             img.src = url;
             event.target.value = '';
         },

         async startCamera() {
             this.camError = null;
             if (!navigator.mediaDevices?.getUserMedia) {
                 this.camError = 'Kamera tidak tersedia. Diperlukan koneksi HTTPS.';
                 return;
             }
             try {
                 this.stream = await navigator.mediaDevices.getUserMedia({
                     video: { facingMode: this.facingMode, width: { ideal: 1280 }, height: { ideal: 960 } },
                     audio: false
                 });
                 await this.$nextTick();
                 const v = this.$refs.video;
                 if (v) { v.srcObject = this.stream; await v.play(); }
             } catch(e) {
                 this.camError = 'Kamera tidak dapat dibuka: ' + e.message;
             }
         },

         shoot() {
             const v = this.$refs.video, c = this.$refs.canvas;
             if (!v || !c) return;
             c.width  = v.videoWidth  || 1280;
             c.height = v.videoHeight || 960;
             const ctx = c.getContext('2d');
             ctx.drawImage(v, 0, 0, c.width, c.height);
             const now = new Date();
             const pad = (n) => n.toString().padStart(2,'0');
             const ts  = pad(now.getDate()) + '/' + pad(now.getMonth()+1) + '/' + now.getFullYear()
                       + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
             const bh  = 52;
             ctx.fillStyle = 'rgba(0,0,0,0.60)';
             ctx.fillRect(0, c.height - bh, c.width, bh);
             ctx.fillStyle    = '#fff';
             ctx.font         = 'bold 22px monospace';
             ctx.textAlign    = 'center';
             ctx.textBaseline = 'middle';
             ctx.fillText(ts, c.width / 2, c.height - bh / 2);
             this.photo = c.toDataURL('image/jpeg', 0.85);
             this.stopStream();
             c.toBlob((blob) => {
                 const file = new File([blob], 'checkin.jpg', { type: 'image/jpeg' });
                 this.uploading = true;
                 this.$wire.upload('checkinPhoto', file,
                     () => { this.uploading = false; },
                     () => { this.uploading = false; this.camError = 'Gagal mengunggah foto.'; }
                 );
             }, 'image/jpeg', 0.85);
         },

         retake() {
             this.$wire.set('checkinPhoto', null);
             this.photo    = null;
             this.camError = null;
             if (this.source === 'gallery') {
                 this.mode = 'picker';
             } else {
                 this.startCamera();
             }
         },

         backFromCamera() {
             if (this.hasPhoto) {
                 this.retake();
             } else {
                 this.stopStream();
                 this.mode = 'picker';
             }
         },

         stopStream() {
             if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
         },

         detectLocation() {
             this.locStatus = 'detecting';
             this.lat       = null;
             this.lng       = null;
             if (!navigator.geolocation) { this.locStatus = 'error'; return; }
             navigator.geolocation.getCurrentPosition(
                 (pos) => {
                     this.lat       = pos.coords.latitude;
                     this.lng       = pos.coords.longitude;
                     this.locStatus = 'detected';
                 },
                 () => { this.locStatus = 'error'; },
                 { enableHighAccuracy: true, timeout: 15000 }
             );
         },

         confirm() {
             this.$wire.saveCheckin();
             this.stopStream();
             this.mode      = 'idle';
             this.photo     = null;
             this.locStatus = 'idle';
         }
     }"
     @keydown.escape.window="closeCheckin()">

@php
    $trip      = $this->tripModel;
    $route     = $trip->tripRoute;
    $waypoints = $route->waypoints;
    $checkins  = $trip->waypointCheckins->keyBy('trip_route_waypoint_id');
    $requiresAttachment = $route->requires_waypoint_attachment;
    $completedCount = $checkins->filter(fn($c) => $c->checked_in_at)->count();
    $totalCount     = $waypoints->count();
    $progress       = $totalCount > 0 ? round($completedCount / $totalCount * 100) : 0;
@endphp

{{-- Hidden file inputs --}}
<input x-ref="fileInput"    type="file" accept="image/*" class="hidden" @change="onFilePicked($event)">
<input x-ref="odoFileInput" type="file" accept="image/*" class="hidden" @change="onOdoFilePicked($event)">

<div class="flex flex-col bg-blue-600" style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-6 pt-14">
        <div class="mb-3 flex items-center gap-3">
            <a href="{{ \App\Filament\Casual\Pages\TripDashboard::getUrl() }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-blue-200">Perjalanan Aktif</p>
                <p class="truncate text-base font-semibold text-white">{{ $route->name }}</p>
            </div>
            <div class="shrink-0 text-right">
                <span class="text-2xl font-bold text-white">{{ $completedCount }}/{{ $totalCount }}</span>
                <p class="text-[10px] text-blue-200">titik</p>
            </div>
        </div>

        <p class="mb-2 text-xs text-blue-200">{{ $trip->vehicle->license_plate }} · Mulai {{ $trip->started_at->format('H:i') }}</p>

        {{-- Progress bar --}}
        <div class="h-1.5 overflow-hidden rounded-full bg-white/20">
            <div class="h-1.5 rounded-full bg-white transition-all duration-500" style="width:{{ $progress }}%"></div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-10 pt-6 dark:bg-gray-950">

        {{-- Trip info card --}}
        <div class="mx-5 mb-5 overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">

            {{-- Status header --}}
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <div>
                    <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Kode Perjalanan</p>
                    <p class="font-mono text-sm font-bold tracking-wider text-gray-900 dark:text-white">{{ $trip->code ?? '—' }}</p>
                </div>
                @php $status = $trip->status; @endphp
                <span class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold
                    {{ $status === \App\Enums\TripStatus::InProgress ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : '' }}
                    {{ $status === \App\Enums\TripStatus::Pending    ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : '' }}
                    {{ $status === \App\Enums\TripStatus::Completed  ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : '' }}">
                    @if($status === \App\Enums\TripStatus::InProgress)
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-blue-500"></span>
                    @elseif($status === \App\Enums\TripStatus::Pending)
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                    @elseif($status === \App\Enums\TripStatus::Completed)
                        <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                    @endif
                    {{ $status->getLabel() }}
                </span>
            </div>


            <div class="grid grid-cols-2">
                {{-- Kendaraan --}}
                <div class="flex items-center gap-3 border-b border-r border-gray-100 px-4 py-3.5 dark:border-gray-800">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20">
                        <svg class="h-4.5 w-4.5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Kendaraan</p>
                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $trip->vehicle->license_plate }}</p>
                        <p class="text-[11px] text-gray-400">{{ $trip->vehicle->model }}</p>
                    </div>
                </div>

                {{-- Tanggal --}}
                <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3.5 dark:border-gray-800">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-900/20">
                        <svg class="h-4.5 w-4.5 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Tanggal</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $trip->trip_date->isoFormat('D MMM Y') }}</p>
                        <p class="text-[11px] text-gray-400">{{ $trip->trip_date->locale('id')->isoFormat('dddd') }}</p>
                    </div>
                </div>

                {{-- Jam mulai --}}
                <div class="flex items-center gap-3 border-r border-gray-100 px-4 py-3.5 dark:border-gray-800">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-green-50 dark:bg-green-900/20">
                        <svg class="h-4.5 w-4.5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Jam Mulai</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $trip->started_at->format('H:i') }}</p>
                        <p class="text-[11px] text-gray-400">{{ $trip->started_at->locale('id')->diffForHumans() }}</p>
                    </div>
                </div>

                {{-- Progress --}}
                <div class="flex items-center gap-3 px-4 py-3.5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-900/20">
                        <svg class="h-4.5 w-4.5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Progress</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $completedCount }}/{{ $totalCount }} titik</p>
                        <p class="text-[11px] text-gray-400">{{ $progress }}% selesai</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Odometer start card --}}
        @if(!$trip->odo_start)
            <div class="mx-5 mb-5 overflow-hidden rounded-2xl bg-amber-50 ring-1 ring-amber-200 dark:bg-amber-900/20 dark:ring-amber-700/40">
                <div class="flex items-center gap-3 px-4 py-3.5">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500">
                        <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Odometer Awal Belum Diisi</p>
                        <p class="text-xs text-amber-600 dark:text-amber-400">Catat angka odometer kendaraan sekarang</p>
                    </div>
                    <button @click="openOdo('start')"
                            class="shrink-0 flex items-center gap-1.5 rounded-xl bg-amber-500 px-3 py-2 text-xs font-semibold text-white transition active:scale-95 active:bg-amber-600">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        Isi Sekarang
                    </button>
                </div>
            </div>
        @else
            <div class="mx-5 mb-5 overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-3 px-4 py-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-green-50 dark:bg-green-900/20">
                        <svg class="h-4.5 w-4.5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Odometer Awal</p>
                        <p class="text-sm font-bold font-mono text-gray-900 dark:text-white">{{ number_format($trip->odo_start) }} km</p>
                    </div>
                    @if($trip->odo_start_photo)
                        <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl ring-2 ring-green-100 dark:ring-green-900/40">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($trip->odo_start_photo, now()->addHour()) }}"
                                 class="h-full w-full object-cover" alt="Odo awal">
                        </div>
                    @endif
                    <button @click="openOdo('start')"
                            class="shrink-0 flex items-center gap-1 rounded-lg bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-500 transition active:bg-gray-200 dark:bg-gray-800 dark:text-gray-400">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
                        </svg>
                        Edit
                    </button>
                </div>
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
                            $checkin   = $checkins[$waypoint->id] ?? null;
                            $isDone    = $checkin && $checkin->checked_in_at;
                            $hasAttach = $checkin && $checkin->attachment_path;
                            $isReady   = $isDone && (!$requiresAttachment || $hasAttach);
                        @endphp

                        <div class="flex gap-4">
                            {{-- Dot --}}
                            <div class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 ring-gray-50 dark:ring-gray-950
                                {{ $isReady ? 'bg-blue-500' : ($isDone ? 'bg-amber-400' : 'bg-gray-200 dark:bg-gray-700') }}">
                                @if($isReady)
                                    <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                @elseif($isDone)
                                    <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/></svg>
                                @else
                                    <span class="h-2 w-2 rounded-full bg-gray-400 dark:bg-gray-500"></span>
                                @endif
                            </div>

                            {{-- Content --}}
                            <div class="min-w-0 flex-1 overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">

                                {{-- Name + button row --}}
                                <div class="flex min-h-[4rem] items-center gap-3 px-4 py-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ $waypoint->name }}
                                        </p>
                                        @if($waypoint->description)
                                            <p class="mt-0.5 text-xs text-gray-400">{{ $waypoint->description }}</p>
                                        @endif
                                        @if(!$isDone)
                                            <p class="mt-1 text-xs text-gray-400">Belum check-in</p>
                                        @endif
                                    </div>

                                    @if($checkin)
                                        <button @click="openCheckin({{ $checkin->id }})"
                                                class="shrink-0 flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold transition
                                                    {{ $isReady ? 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' : 'bg-blue-600 text-white active:scale-95' }}">
                                            @if($isReady)
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
                                                </svg>
                                                Edit
                                            @else
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                                                </svg>
                                                Check-in
                                            @endif
                                        </button>
                                    @endif
                                </div>

                                {{-- Check-in footer --}}
                                @if($isDone)
                                    <div class="flex items-center gap-3 border-t border-gray-100 px-4 py-2.5 dark:border-gray-800">
                                        <div class="flex flex-1 items-center gap-1.5">
                                            <div class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                                                <svg class="h-3 w-3 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                                </svg>
                                            </div>
                                            <span class="text-xs font-semibold text-blue-600 dark:text-blue-400">Check-in {{ $checkin->checked_in_at->format('H:i') }}</span>
                                        </div>

                                        @if($hasAttach)
                                            <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl ring-2 ring-blue-100 dark:ring-blue-900/40">
                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($checkin->attachment_path, now()->addHour()) }}"
                                                     class="h-full w-full object-cover"
                                                     alt="Bukti">
                                            </div>
                                        @elseif($requiresAttachment)
                                            <div class="flex items-center gap-1 rounded-lg bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-600 dark:bg-amber-900/20 dark:text-amber-400">
                                                <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                                                </svg>
                                                Belum ada bukti
                                            </div>
                                        @endif
                                    </div>
                                @endif

                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Odometer end card --}}
        <div class="mx-5 mt-5">
            @if(!$trip->odo_end)
                <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-center gap-3 px-4 py-3.5">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800">
                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Odometer Akhir</p>
                            <p class="text-xs text-gray-400">Belum diisi</p>
                        </div>
                        <button @click="openOdo('end')"
                                class="shrink-0 flex items-center gap-1.5 rounded-xl bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition active:scale-95 active:bg-blue-700">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                            </svg>
                            Isi Sekarang
                        </button>
                    </div>
                </div>
            @else
                <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-center gap-3 px-4 py-3">
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
                        <button @click="openOdo('end')"
                                class="shrink-0 flex items-center gap-1 rounded-lg bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-500 transition active:bg-gray-200 dark:bg-gray-800 dark:text-gray-400">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
                            </svg>
                            Edit
                        </button>
                    </div>
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
                        {{ $trip->allWaypointsCompleted() ? 'bg-blue-600 active:scale-95 active:bg-blue-700' : 'cursor-not-allowed bg-gray-200 text-gray-400 dark:bg-gray-800 dark:text-gray-600' }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5"/>
                </svg>
                Selesaikan Perjalanan
            </button>
        </div>

    </div>

</div>

{{-- ════════════════════════════════════════════
     CHECKIN BACKDROP
════════════════════════════════════════════ --}}
<div x-show="mode === 'picker' || mode === 'camera' || mode === 'odo-picker' || mode === 'odo-sheet' || mode === 'odo-camera'"
     x-transition:enter="transition duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-40 bg-black/60"
     style="display:none"
     @click="mode === 'odo-picker' || mode === 'odo-sheet' ? closeOdo() : (mode === 'odo-camera' ? null : closeCheckin())">
</div>

{{-- ════════════════════════════════════════════
     PICKER SHEET
════════════════════════════════════════════ --}}
<div x-show="mode === 'picker'"
     x-transition:enter="transition duration-300 ease-out"
     x-transition:enter-start="translate-y-full"
     x-transition:enter-end="translate-y-0"
     x-transition:leave="transition duration-200 ease-in"
     x-transition:leave-start="translate-y-0"
     x-transition:leave-end="translate-y-full"
     class="fixed bottom-0 left-1/2 z-50 w-full max-w-[430px] -translate-x-1/2 overflow-hidden rounded-t-3xl bg-white dark:bg-gray-900"
     style="display:none"
     @click.stop>

    {{-- Drag handle --}}
    <div class="flex justify-center pb-2 pt-3">
        <div class="h-1 w-10 rounded-full bg-gray-200 dark:bg-gray-700"></div>
    </div>

    {{-- Header row --}}
    <div class="flex items-center justify-between px-5 pb-4 pt-2">
        <div>
            <p class="font-semibold text-gray-900 dark:text-white">Check-in Titik</p>
            <p class="mt-0.5 text-xs text-gray-400">Pilih cara upload bukti</p>
        </div>
        {{-- Location status badge --}}
        <div class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
             :class="{
                 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500': locStatus === 'idle' || locStatus === 'detecting',
                 'bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400': locStatus === 'detected',
                 'bg-red-50 text-red-500 dark:bg-red-900/20 dark:text-red-400': locStatus === 'error'
             }">
            <span x-show="locStatus === 'detecting'" class="h-1.5 w-1.5 animate-pulse rounded-full bg-current"></span>
            <svg x-show="locStatus === 'detected'" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="display:none">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
            </svg>
            <svg x-show="locStatus === 'error'" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
            <span x-text="locStatus === 'detected' ? 'Lokasi OK' : locStatus === 'error' ? 'Tanpa Lokasi' : 'Deteksi...'"></span>
        </div>
    </div>

    {{-- Options --}}
    <div class="flex flex-col gap-3 px-5 pb-4">

        {{-- Camera --}}
        <button @click="chooseCamera()"
                class="flex w-full items-center gap-4 rounded-2xl bg-blue-50 px-4 py-3.5 text-left transition active:bg-blue-100 dark:bg-blue-900/20 dark:active:bg-blue-900/40">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-600">
                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                </svg>
            </div>
            <div class="flex-1">
                <p class="font-semibold text-gray-900 dark:text-white">Kamera</p>
                <p class="text-xs text-gray-400">Ambil foto langsung dengan kamera</p>
            </div>
            <svg class="h-4 w-4 shrink-0 text-blue-300 dark:text-blue-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
            </svg>
        </button>

        {{-- Gallery --}}
        <button @click="chooseGallery()"
                class="flex w-full items-center gap-4 rounded-2xl bg-gray-50 px-4 py-3.5 text-left transition active:bg-gray-100 dark:bg-gray-800 dark:active:bg-gray-700">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-800 dark:bg-gray-700">
                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                </svg>
            </div>
            <div class="flex-1">
                <p class="font-semibold text-gray-900 dark:text-white">Galeri / File</p>
                <p class="text-xs text-gray-400">Pilih dari foto tersimpan</p>
            </div>
            <svg class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
            </svg>
        </button>

    </div>

    {{-- Cancel --}}
    <div class="px-5 pb-10">
        <button @click="closeCheckin()"
                class="flex w-full items-center justify-center rounded-2xl bg-gray-100 py-3.5 text-sm font-semibold text-gray-500 transition active:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:active:bg-gray-700">
            Batal
        </button>
    </div>

</div>

{{-- ════════════════════════════════════════════
     CAMERA / PREVIEW OVERLAY
════════════════════════════════════════════ --}}
<div x-show="mode === 'camera'"
     x-transition:enter="transition duration-300 ease-out"
     x-transition:enter-start="opacity-0 scale-[1.02]"
     x-transition:enter-end="opacity-100 scale-100"
     x-transition:leave="transition duration-200 ease-in"
     x-transition:leave-start="opacity-100 scale-100"
     x-transition:leave-end="opacity-0 scale-[1.02]"
     class="fixed inset-0 z-50 overflow-hidden bg-black"
     style="display:none">

    {{-- Live camera feed --}}
    <video x-ref="video"
           x-show="hasStream && !hasPhoto"
           autoplay muted playsinline
           class="absolute inset-0 h-full w-full object-cover"
           style="display:none"></video>

    {{-- Captured / gallery photo preview --}}
    <img x-show="hasPhoto" :src="photo"
         class="absolute inset-0 h-full w-full object-contain" alt=""
         style="display:none">

    {{-- Camera loading --}}
    <div x-show="!hasStream && !hasPhoto && !camError"
         class="absolute inset-0 flex flex-col items-center justify-center gap-4"
         style="display:none">
        <svg class="h-10 w-10 animate-spin text-white/30" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        <p class="text-sm text-white/40">Membuka kamera...</p>
    </div>

    {{-- Camera error --}}
    <div x-show="camError && !hasPhoto"
         class="absolute inset-0 flex flex-col items-center justify-center gap-5 px-8 text-center"
         style="display:none">
        <div class="flex h-20 w-20 items-center justify-center rounded-full bg-red-500/20">
            <svg class="h-10 w-10 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M12 18.75H4.5a2.25 2.25 0 0 1-2.25-2.25V9m12.841 9.091L16.5 19.5m-1.409-1.409c.407-.407.659-.97.659-1.591v-9a2.25 2.25 0 0 0-2.25-2.25h-9c-.621 0-1.184.252-1.591.659m12.182 12.182L2.909 5.909M1.5 4.5l1.409 1.409"/>
            </svg>
        </div>
        <p class="text-sm leading-relaxed text-white/80" x-text="camError"></p>
        <button @click="camError = null; source === 'gallery' ? (mode = 'picker') : startCamera()"
                class="rounded-full bg-white/20 px-6 py-2.5 text-sm font-semibold text-white transition active:scale-95">
            Coba Lagi
        </button>
    </div>

    {{-- Upload overlay --}}
    <div x-show="uploading"
         class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-black/60"
         style="display:none">
        <svg class="h-10 w-10 animate-spin text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        <p class="text-sm text-white/80">Mengunggah foto...</p>
    </div>

    <canvas x-ref="canvas" class="hidden"></canvas>

    {{-- TOP BAR --}}
    <div class="absolute inset-x-0 top-0 z-10 rounded-b-3xl bg-blue-600 px-5 pb-5 pt-12">
        <div class="flex items-center justify-between">
            <button @click="backFromCamera()"
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white transition active:bg-white/25">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </button>
            <div class="text-center">
                <p class="font-semibold text-white">Check-in Titik</p>
                <p class="text-xs text-blue-200" x-text="hasPhoto ? 'Konfirmasi foto' : 'Ambil foto'"></p>
            </div>
            <button @click="toggleCamera()" x-show="hasStream && !hasPhoto" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/15 text-white transition active:bg-white/25">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                </svg>
            </button>
            <div x-show="!(hasStream && !hasPhoto)" class="h-10 w-10 shrink-0"></div>
        </div>
    </div>

    {{-- BOTTOM CONTROLS --}}
    <div class="absolute inset-x-0 bottom-0 z-10 px-6 pb-14 pt-8"
         style="background: linear-gradient(to top, rgba(0,0,0,0.80) 0%, transparent 100%)">

        {{-- Shoot button (live feed) --}}
        <div x-show="hasStream && !hasPhoto" class="space-y-5" style="display:none">
            <div x-data="{ t: '' }"
                 x-init="const p = v => v.toString().padStart(2,'0');
                          setInterval(() => {
                              const n = new Date();
                              t = p(n.getDate()) + '/' + p(n.getMonth()+1) + '/' + n.getFullYear()
                                + ' ' + p(n.getHours()) + ':' + p(n.getMinutes()) + ':' + p(n.getSeconds());
                          }, 1000);"
                 class="text-center font-mono text-base font-medium text-white/80"
                 x-text="t">
            </div>
            <div class="flex justify-center">
                <button @click="shoot()"
                        class="relative flex h-20 w-20 items-center justify-center rounded-full ring-4 ring-white/30 transition active:scale-90">
                    <div class="h-[3.75rem] w-[3.75rem] rounded-full bg-white"></div>
                </button>
            </div>
            <p class="text-center text-xs text-white/40">Tekan untuk mengambil foto</p>
        </div>

        {{-- Confirm / retake (preview) --}}
        <div x-show="hasPhoto && !uploading" class="space-y-3" style="display:none">
            {{-- Location status --}}
            <div class="flex items-center justify-center gap-1.5 text-xs"
                 :class="locStatus === 'detected' ? 'text-green-400' : 'text-white/40'">
                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                </svg>
                <span x-text="locStatus === 'detected' ? 'Lokasi terdeteksi' : locStatus === 'detecting' ? 'Mendeteksi lokasi...' : 'Lokasi tidak tersedia'"></span>
            </div>
            <div class="flex gap-3">
                <button @click="retake()"
                        class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-white/15 py-4 text-sm font-semibold text-white transition active:scale-95 active:bg-white/25">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                    Ulang
                </button>
                <button @click="confirm()"
                        class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-blue-600 py-4 text-sm font-semibold text-white transition active:scale-95 active:bg-blue-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                    Simpan
                </button>
            </div>
        </div>

    </div>

</div>

{{-- ════════════════════════════════════════════
     ODO PICKER SHEET
════════════════════════════════════════════ --}}
<div x-show="mode === 'odo-picker'"
     x-transition:enter="transition duration-300 ease-out"
     x-transition:enter-start="translate-y-full"
     x-transition:enter-end="translate-y-0"
     x-transition:leave="transition duration-200 ease-in"
     x-transition:leave-start="translate-y-0"
     x-transition:leave-end="translate-y-full"
     class="fixed bottom-0 left-1/2 z-50 w-full max-w-[430px] -translate-x-1/2 overflow-hidden rounded-t-3xl bg-white dark:bg-gray-900"
     style="display:none"
     @click.stop>

    <div class="flex justify-center pb-2 pt-3">
        <div class="h-1 w-10 rounded-full bg-gray-200 dark:bg-gray-700"></div>
    </div>

    <div class="px-5 pb-4 pt-2">
        <p class="font-semibold text-gray-900 dark:text-white" x-text="odoCtx === 'start' ? 'Odometer Awal' : 'Odometer Akhir'"></p>
        <p class="mt-0.5 text-xs text-gray-400">Masukkan angka km dan ambil foto odometer</p>
    </div>

    <div class="px-5 pb-4">
        {{-- KM input --}}
        <div class="mb-4">
            <label class="mb-1.5 block text-xs font-medium text-gray-500">Angka Kilometer (km)</label>
            <div class="flex items-center gap-2 overflow-hidden rounded-xl border-2 bg-white px-3 dark:bg-gray-800"
                 :class="odoCtx === 'end' && odoKm && parseInt(odoKm) <= {{ (int) ($this->tripModel->odo_start ?? 0) }} && {{ (int) ($this->tripModel->odo_start ?? 0) }} > 0
                     ? 'border-red-400 focus-within:border-red-500'
                     : 'border-gray-200 focus-within:border-blue-500 dark:border-gray-700'">
                <input x-model="odoKm"
                       type="number"
                       min="0"
                       placeholder="Contoh: 12500"
                       class="flex-1 bg-transparent py-3 text-sm font-semibold text-gray-900 outline-none placeholder:font-normal placeholder:text-gray-400 dark:text-white">
                <span class="shrink-0 text-xs font-medium text-gray-400">km</span>
            </div>
            @if($this->tripModel->odo_start)
                <p x-show="odoCtx === 'end' && odoKm && parseInt(odoKm) <= {{ (int) $this->tripModel->odo_start }}"
                   class="mt-1.5 text-xs font-medium text-red-500"
                   style="display:none">
                    Harus lebih dari odometer awal ({{ number_format($this->tripModel->odo_start) }} km)
                </p>
            @endif
        </div>

        {{-- Photo --}}
        <label class="mb-1.5 block text-xs font-medium text-gray-500">Foto Odometer <span class="text-gray-400">(opsional)</span></label>
        <template x-if="!odoPhoto">
            <div class="flex gap-2">
                <button @click="odoChooseCamera()"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-blue-50 py-3 text-sm font-medium text-blue-700 transition active:bg-blue-100 dark:bg-blue-900/20 dark:text-blue-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                    </svg>
                    Kamera
                </button>
                <button @click="odoChooseGallery()"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-gray-100 py-3 text-sm font-medium text-gray-700 transition active:bg-gray-200 dark:bg-gray-800 dark:text-gray-300">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                    </svg>
                    Galeri
                </button>
            </div>
        </template>
        <template x-if="odoPhoto">
            <div class="relative overflow-hidden rounded-xl">
                <img :src="odoPhoto" class="h-32 w-full object-cover">
                <button @click="odoRetake()"
                        class="absolute right-2 top-2 rounded-lg bg-black/50 px-2.5 py-1 text-xs font-semibold text-white transition active:bg-black/70">
                    Ulangi
                </button>
                <div x-show="odoUploading" class="absolute inset-0 flex items-center justify-center bg-black/40">
                    <svg class="h-6 w-6 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </div>
            </div>
        </template>
    </div>

    <div class="flex gap-3 px-5 pb-10">
        <button @click="closeOdo()"
                class="flex flex-1 items-center justify-center rounded-2xl bg-gray-100 py-3.5 text-sm font-semibold text-gray-500 transition active:bg-gray-200 dark:bg-gray-800 dark:text-gray-400">
            Batal
        </button>
        <button @click="submitOdo()"
                :disabled="!odoKm || parseInt(odoKm) <= 0 || odoUploading ||
                    (odoCtx === 'end' && {{ (int) ($this->tripModel->odo_start ?? 0) }} > 0 && parseInt(odoKm) <= {{ (int) ($this->tripModel->odo_start ?? 0) }})"
                class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white transition active:scale-95 active:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
            </svg>
            Simpan
        </button>
    </div>
</div>

{{-- ════════════════════════════════════════════
     ODO CAMERA OVERLAY
════════════════════════════════════════════ --}}
<div x-show="mode === 'odo-camera'"
     x-transition:enter="transition duration-300 ease-out"
     x-transition:enter-start="opacity-0 scale-[1.02]"
     x-transition:enter-end="opacity-100 scale-100"
     x-transition:leave="transition duration-200 ease-in"
     x-transition:leave-start="opacity-100 scale-100"
     x-transition:leave-end="opacity-0 scale-[1.02]"
     class="fixed inset-0 z-50 overflow-hidden bg-black"
     style="display:none">

    <video x-ref="odoVideo"
           x-show="hasStream && !odoPhoto"
           autoplay muted playsinline
           class="absolute inset-0 h-full w-full object-cover"
           style="display:none"></video>

    <img x-show="odoPhoto" :src="odoPhoto"
         class="absolute inset-0 h-full w-full object-contain" alt=""
         style="display:none">

    <div x-show="!hasStream && !odoPhoto && !camError"
         class="absolute inset-0 flex flex-col items-center justify-center gap-4"
         style="display:none">
        <svg class="h-10 w-10 animate-spin text-white/30" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        <p class="text-sm text-white/40">Membuka kamera...</p>
    </div>

    <div x-show="camError && !odoPhoto"
         class="absolute inset-0 flex flex-col items-center justify-center gap-5 px-8 text-center"
         style="display:none">
        <div class="flex h-20 w-20 items-center justify-center rounded-full bg-red-500/20">
            <svg class="h-10 w-10 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M12 18.75H4.5a2.25 2.25 0 0 1-2.25-2.25V9m12.841 9.091L16.5 19.5m-1.409-1.409c.407-.407.659-.97.659-1.591v-9a2.25 2.25 0 0 0-2.25-2.25h-9c-.621 0-1.184.252-1.591.659m12.182 12.182L2.909 5.909M1.5 4.5l1.409 1.409"/>
            </svg>
        </div>
        <p class="text-sm leading-relaxed text-white/80" x-text="camError"></p>
        <button @click="camError = null; source === 'gallery' ? (mode = 'odo-picker') : startOdoCamera()"
                class="rounded-full bg-white/20 px-6 py-2.5 text-sm font-semibold text-white transition active:scale-95">
            Coba Lagi
        </button>
    </div>

    <div x-show="odoUploading"
         class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-black/60"
         style="display:none">
        <svg class="h-10 w-10 animate-spin text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        <p class="text-sm text-white/80">Mengunggah foto...</p>
    </div>

    <canvas x-ref="odoCanvas" class="hidden"></canvas>

    {{-- TOP BAR --}}
    <div class="absolute inset-x-0 top-0 z-10 rounded-b-3xl bg-blue-600 px-5 pb-5 pt-12">
        <div class="flex items-center justify-between">
            <button @click="odoBackFromCamera()"
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white transition active:bg-white/25">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </button>
            <div class="text-center">
                <p class="font-semibold text-white" x-text="odoCtx === 'start' ? 'Odometer Awal' : 'Odometer Akhir'"></p>
                <p class="text-xs text-blue-200" x-text="odoPhoto ? 'Konfirmasi foto' : 'Ambil foto odometer'"></p>
            </div>
            <div class="h-10 w-10"></div>
        </div>
    </div>

    {{-- BOTTOM CONTROLS --}}
    <div class="absolute inset-x-0 bottom-0 z-10 px-6 pb-14 pt-8"
         style="background: linear-gradient(to top, rgba(0,0,0,0.80) 0%, transparent 100%)">

        <div x-show="hasStream && !odoPhoto" class="space-y-5" style="display:none">
            <div x-data="{ t: '' }"
                 x-init="const p = v => v.toString().padStart(2,'0');
                          setInterval(() => {
                              const n = new Date();
                              t = p(n.getDate()) + '/' + p(n.getMonth()+1) + '/' + n.getFullYear()
                                + ' ' + p(n.getHours()) + ':' + p(n.getMinutes()) + ':' + p(n.getSeconds());
                          }, 1000);"
                 class="text-center font-mono text-base font-medium text-white/80"
                 x-text="t">
            </div>
            <div class="flex justify-center">
                <button @click="shootOdo()"
                        class="relative flex h-20 w-20 items-center justify-center rounded-full ring-4 ring-white/30 transition active:scale-90">
                    <div class="h-[3.75rem] w-[3.75rem] rounded-full bg-white"></div>
                </button>
            </div>
            <p class="text-center text-xs text-white/40">Tekan untuk mengambil foto</p>
        </div>

        <div x-show="odoPhoto && !odoUploading" class="space-y-3" style="display:none">
            <div class="flex gap-3">
                <button @click="odoRetake()"
                        class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-white/15 py-4 text-sm font-semibold text-white transition active:scale-95 active:bg-white/25">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                    Ulang
                </button>
                <button @click="confirmOdoPhoto()"
                        class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-blue-600 py-4 text-sm font-semibold text-white transition active:scale-95 active:bg-blue-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                    Pakai Foto Ini
                </button>
            </div>
        </div>
    </div>
</div>

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
