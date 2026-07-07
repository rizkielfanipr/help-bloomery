@php
    $user = auth()->user();
    $firstName = explode(' ', $user->name)[0];
    $briefingData = $this->briefingData;

    $periodColors = [
        'daily'   => ['bg' => 'bg-blue-600', 'light' => 'bg-blue-50', 'icon' => 'text-blue-600', 'pill' => 'bg-blue-100 text-blue-700', 'bar' => 'bg-blue-500'],
        'weekly'  => ['bg' => 'bg-blue-600',   'light' => 'bg-blue-50',   'icon' => 'text-blue-600',   'pill' => 'bg-blue-100 text-blue-700',   'bar' => 'bg-blue-500'],
        'monthly' => ['bg' => 'bg-amber-500',  'light' => 'bg-amber-50',  'icon' => 'text-amber-500',  'pill' => 'bg-amber-100 text-amber-700', 'bar' => 'bg-amber-400'],
    ];
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900"
     style="min-height:100dvh"
     x-data="{
         showModal: false,
         openModal() { this.showModal = true; this.updateStampTime(); this.initLocation(); },
         closeModal() { this.showModal = false; },

         cameraOpen: false,
         stream: null,
         stampTime: '',
         stampLocation: 'Mendapatkan lokasi...',
         locationCoords: null,
         previewSrc: null,
         timeInterval: null,
         isSelfie: false,
         uploading: false,

         async openCamera() {
             this.isSelfie = $wire.activeTaskIsSelfie;
             this.previewSrc = null;
             this.uploading = false;
             this.cameraOpen = true;

             this.updateStampTime();
             this.timeInterval = setInterval(() => this.updateStampTime(), 1000);

             this.initLocation();

             try {
                 this.stream = await navigator.mediaDevices.getUserMedia({
                     video: {
                         facingMode: this.isSelfie ? 'user' : 'environment',
                         width: { ideal: 1280 },
                         height: { ideal: 960 },
                     },
                     audio: false,
                 });
                 await this.$nextTick();
                 const video = document.getElementById('cam-video');
                 if (video) {
                     video.srcObject = this.stream;
                 }
             } catch (err) {
                 clearInterval(this.timeInterval);
                 this.cameraOpen = false;
                 alert('Tidak dapat mengakses kamera. Pastikan izin kamera sudah diberikan.');
             }
         },

         closeCamera() {
             if (this.stream) {
                 this.stream.getTracks().forEach(t => t.stop());
                 this.stream = null;
             }
             clearInterval(this.timeInterval);
             this.cameraOpen = false;
         },

         initLocation() {
             if ('geolocation' in navigator) {
                 navigator.geolocation.getCurrentPosition(
                     async (pos) => {
                         const lat = pos.coords.latitude.toFixed(6);
                         const lng = pos.coords.longitude.toFixed(6);
                         this.locationCoords = { lat, lng };
                         this.stampLocation = lat + ', ' + lng;
                         try {
                             const res = await fetch(
                                 `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`,
                                 { headers: { 'Accept-Language': 'id' } }
                             );
                             if (res.ok) {
                                 const data = await res.json();
                                 const addr = data.address || {};
                                 const road = addr.road || addr.pedestrian || addr.footway || addr.path || '';
                                 const suburb = addr.suburb || addr.neighbourhood || addr.village || '';
                                 const city = addr.city || addr.town || addr.county || '';
                                 let formatted = [road, suburb, city].filter(Boolean).join(', ');
                                 if (! formatted) {
                                     formatted = (data.display_name || '').split(',').slice(0, 2).join(',').trim();
                                 }
                                 if (formatted) {
                                     this.stampLocation = formatted.length > 55 ? formatted.substring(0, 52) + '...' : formatted;
                                 }
                             }
                         } catch (_) {}
                     },
                     () => { this.stampLocation = 'Lokasi tidak tersedia'; },
                     { timeout: 10000, enableHighAccuracy: true }
                 );
             } else {
                 this.stampLocation = 'Lokasi tidak didukung';
             }
         },

         burnTimestamp(canvas) {
             const ctx = canvas.getContext('2d');
             const fW = canvas.width;
             const fH = canvas.height;
             const bandH = Math.max(68, Math.round(fH * 0.13));
             const grad = ctx.createLinearGradient(0, fH - bandH, 0, fH);
             grad.addColorStop(0, 'rgba(0,0,0,0)');
             grad.addColorStop(1, 'rgba(0,0,0,0.80)');
             ctx.fillStyle = grad;
             ctx.fillRect(0, fH - bandH, fW, bandH);
             const fs = Math.max(14, Math.round(fW * 0.028));
             const font = 'system-ui, -apple-system, sans-serif';
             const base = fH - Math.round(bandH * 0.38);
             ctx.font = 'bold ' + fs + 'px ' + font;
             ctx.fillStyle = '#FFFFFF';
             ctx.fillText(this.stampTime, 14, base);
             ctx.font = Math.round(fs * 0.80) + 'px ' + font;
             ctx.fillStyle = 'rgba(255,255,255,0.72)';
             ctx.fillText(this.stampLocation, 14, base + fs + 4);
         },

         updateStampTime() {
             const now = new Date();
             const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
             const d = String(now.getDate()).padStart(2, '0');
             const m = months[now.getMonth()];
             const y = now.getFullYear();
             const h = String(now.getHours()).padStart(2, '0');
             const min = String(now.getMinutes()).padStart(2, '0');
             const s = String(now.getSeconds()).padStart(2, '0');
             this.stampTime = d + ' ' + m + ' ' + y + ' - ' + h + ':' + min + ':' + s;
         },

         capturePhoto() {
             const video = document.getElementById('cam-video');
             if (!video || !video.videoWidth) return;

             const W = video.videoWidth;
             const H = video.videoHeight;

             // Draw video frame
             const tmp = document.createElement('canvas');
             tmp.width = W;
             tmp.height = H;
             const tmpCtx = tmp.getContext('2d');
             if (this.isSelfie) {
                 tmpCtx.translate(W, 0);
                 tmpCtx.scale(-1, 1);
             }
             tmpCtx.drawImage(video, 0, 0);
             tmpCtx.setTransform(1, 0, 0, 1, 0, 0);

             // Resize to max 1024px wide
             const maxW = 1024;
             const scale = W > maxW ? maxW / W : 1;
             const fW = Math.round(W * scale);
             const fH = Math.round(H * scale);

             const canvas = document.createElement('canvas');
             canvas.width = fW;
             canvas.height = fH;
             const ctx = canvas.getContext('2d');
             ctx.drawImage(tmp, 0, 0, fW, fH);

             this.burnTimestamp(canvas);

             this.previewSrc = canvas.toDataURL('image/jpeg', 0.85);
             this.closeCamera();
             this.uploading = true;

             $wire.storeCameraPhoto(this.previewSrc)
                 .then(() => { this.uploading = false; this.previewSrc = null; })
                 .catch(() => {
                     this.uploading = false;
                     this.previewSrc = null;
                     alert('Gagal menyimpan foto. Silakan coba lagi.');
                 });
         },

         handleFileUpload(event) {
             const file = event.target.files[0];
             if (!file) return;
             event.target.value = '';

             this.updateStampTime();
             this.uploading = true;

             const reader = new FileReader();
             reader.onload = (e) => {
                 const img = new Image();
                 img.onload = () => {
                     const maxW = 1024;
                     const scale = img.width > maxW ? maxW / img.width : 1;
                     const fW = Math.round(img.width * scale);
                     const fH = Math.round(img.height * scale);

                     const canvas = document.createElement('canvas');
                     canvas.width = fW;
                     canvas.height = fH;
                     const ctx = canvas.getContext('2d');
                     ctx.drawImage(img, 0, 0, fW, fH);

                     this.burnTimestamp(canvas);

                     const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
                     this.previewSrc = dataUrl;

                     $wire.storeCameraPhoto(dataUrl)
                         .then(() => { this.uploading = false; this.previewSrc = null; })
                         .catch(() => {
                             this.uploading = false;
                             this.previewSrc = null;
                             alert('Gagal menyimpan foto. Silakan coba lagi.');
                         });
                 };
                 img.src = e.target.result;
             };
             reader.readAsDataURL(file);
         },
     }"
     @open-task-modal.window="openModal()"
     @close-task-modal.window="closeModal()">

    {{-- ════════════════════════════════════════════
         CAMERA OVERLAY (full-screen, z-[100])
    ════════════════════════════════════════════ --}}
    <div x-show="cameraOpen"
         x-cloak
         class="fixed inset-0 z-[100] flex flex-col bg-black"
         style="display:none">

        {{-- Close button --}}
        <button @click="closeCamera()"
                class="absolute left-4 top-safe-top z-10 mt-4 flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </button>

        {{-- Video preview --}}
        <div class="relative flex-1 overflow-hidden">
            <video id="cam-video"
                   autoplay
                   playsinline
                   muted
                   :class="isSelfie ? '-scale-x-100' : ''"
                   class="h-full w-full object-cover"></video>

            {{-- Live timestamp overlay (HTML — mirrors the burned-in version) --}}
            <div class="absolute bottom-0 left-0 right-0 bg-black/60 px-3 py-2.5">
                <p x-text="stampTime"
                   class="font-mono text-sm font-bold leading-tight text-white"></p>
                <p x-text="stampLocation"
                   class="mt-0.5 font-mono text-xs leading-tight text-green-300"></p>
            </div>
        </div>

        {{-- Capture button --}}
        <div class="flex flex-shrink-0 items-center justify-center bg-black py-8">
            <button @click="capturePhoto()"
                    class="flex h-18 w-18 items-center justify-center rounded-full border-4 border-white/70 bg-white shadow-xl transition active:scale-90"
                    style="height:72px;width:72px">
                <div class="h-14 w-14 rounded-full bg-white ring-2 ring-gray-300" style="height:56px;width:56px"></div>
            </button>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         VIOLET HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('filament.casual.pages.launcher-page') }}"
                   class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                    </svg>
                </a>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/>
                    </svg>
                </div>
                <span class="text-base font-semibold text-white">Daily Briefing</span>
            </div>
        </div>

        <p class="text-blue-200">
            {{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}
        </p>
        <p class="text-xl font-semibold text-white">Halo, {{ $firstName }}!</p>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        <div class="flex flex-col gap-5 px-5">

            @foreach($briefingData as $periodKey => $data)
                @php $colors = $periodColors[$periodKey]; @endphp

                <div class="rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900">

                    {{-- Period header --}}
                    <div class="flex items-center justify-between px-4 pb-3 pt-4">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl {{ $colors['light'] }}">
                                @if($periodKey === 'daily')
                                    <svg class="h-5 w-5 {{ $colors['icon'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                    </svg>
                                @elseif($periodKey === 'weekly')
                                    <svg class="h-5 w-5 {{ $colors['icon'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                                    </svg>
                                @else
                                    <svg class="h-5 w-5 {{ $colors['icon'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5"/>
                                    </svg>
                                @endif
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $data['label'] }}</p>
                                <p class="text-xs text-gray-400">{{ $data['completed'] }}/{{ $data['total'] }} tugas selesai</p>
                            </div>
                        </div>
                    </div>

                    {{-- Progress bar --}}
                    <div class="mx-4 mb-3 h-1.5 overflow-hidden rounded-full bg-gray-100">
                        @php $pct = $data['total'] > 0 ? round($data['completed'] / $data['total'] * 100) : 0; @endphp
                        <div class="h-full rounded-full transition-all duration-500 {{ $colors['bar'] }}"
                             style="width: {{ $pct }}%"></div>
                    </div>

                    {{-- Task list —— grouped dynamically --}}
                    @php
                        $allTasks = collect($data['tasks']);
                        $ungroupedTasks = $allTasks->whereNull('group')->values();
                        $groups = $allTasks->whereNotNull('group')->groupBy('group');
                    @endphp

                    <div class="divide-y divide-gray-100 dark:divide-gray-800">

                        @foreach($ungroupedTasks as $task)
                            @php
                                $reviewStatus = $task['reviewStatus']?->value ?? null;
                                $isApproved   = $reviewStatus === 'approved';
                                $isRejected   = $reviewStatus === 'rejected';
                                $isPending    = $reviewStatus === 'pending';
                                $isPastDL     = $task['isPastDeadline'];
                                $isClickable  = ! $isPastDL && ($isRejected || (! $task['reviewStatus'] && ! $task['isCompleted']));
                            @endphp

                            @if($isClickable)
                                <button type="button"
                                        wire:click="openTaskModal('{{ $task['key'] }}')"
                                        class="flex w-full items-start gap-3 px-4 py-3 text-left transition active:bg-gray-50 dark:active:bg-gray-800">
                            @else
                                <div class="flex w-full items-start gap-3 px-4 py-3 text-left">
                            @endif

                                {{-- Status icon --}}
                                <div class="mt-0.5 flex-shrink-0">
                                    @if($isApproved)
                                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-green-500">
                                            <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                        </div>
                                    @elseif($isRejected)
                                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-red-500">
                                            <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                        </div>
                                    @elseif($isPastDL)
                                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-300 dark:bg-gray-600">
                                            <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                        </div>
                                    @elseif($isPending)
                                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-400">
                                            <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                        </div>
                                    @elseif($task['isCompleted'])
                                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-green-500">
                                            <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                        </div>
                                    @else
                                        <div class="h-6 w-6 rounded-full border-2 border-gray-200 dark:border-gray-600"></div>
                                    @endif
                                </div>

                                {{-- Task info --}}
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium {{ ($isApproved || $task['isCompleted']) && ! $isRejected ? 'text-gray-400 line-through dark:text-gray-500' : ($isPastDL ? 'text-gray-400 dark:text-gray-500' : 'text-gray-800 dark:text-gray-200') }}">
                                        {{ $task['label'] }}
                                    </p>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                        @if($isApproved)
                                            <span class="rounded bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700">Disetujui</span>
                                        @elseif($isRejected)
                                            <span class="rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700">Ditolak</span>
                                        @elseif($isPastDL)
                                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400">Deadline Terlewat</span>
                                        @elseif($isPending)
                                            <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700">Menunggu Review</span>
                                        @elseif($task['requiresPhoto'])
                                            <span class="text-xs text-gray-400">
                                                <svg class="inline h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                                                </svg>
                                                {{ $task['noteType'] }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">{{ $task['noteType'] }}</span>
                                        @endif
                                        @if($task['completedAt'] && ! $isRejected && ! $isPending)
                                            <span class="text-xs text-gray-400">{{ $task['completedAt']->format('H.i') }}</span>
                                        @elseif(! $task['isCompleted'] && ! $isPastDL && $task['deadlineLabel'])
                                            <span class="text-xs text-gray-400">{{ $task['deadlineLabel'] }}</span>
                                        @endif
                                    </div>
                                    @if($isRejected && $task['rejectionReason'])
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $task['rejectionReason'] }}</p>
                                        <p class="mt-0.5 text-xs font-medium text-red-500">Tap untuk submit ulang</p>
                                    @endif
                                </div>

                                @if($isClickable)
                                    <svg class="mt-1 h-4 w-4 flex-shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                                    </svg>
                                @endif

                            @if($isClickable)
                                </button>
                            @else
                                </div>
                            @endif
                        @endforeach

                        {{-- Dynamic collapsible groups --}}
                        @foreach($groups as $groupKey => $groupTasks)
                            @php
                                $groupLabel  = $groupTasks->first()['groupLabel'] ?? $groupKey;
                                $groupDone   = $groupTasks->where('isCompleted', true)->count();
                                $groupTotal  = $groupTasks->count();
                                $groupAllDL  = $groupTasks->every(fn ($t) => $t['isPastDeadline']);
                            @endphp

                            <div x-data="{ openGroup: false }">
                                <button type="button"
                                        @click="openGroup = !openGroup"
                                        class="flex w-full items-center gap-3 px-4 py-3 text-left transition active:bg-gray-50 dark:active:bg-gray-800">
                                    <div class="flex-shrink-0">
                                        @if($groupDone === $groupTotal)
                                            <div class="flex h-6 w-6 items-center justify-center rounded-full bg-green-500">
                                                <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                            </div>
                                        @elseif($groupDone > 0)
                                            <div class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-400">
                                                <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                            </div>
                                        @else
                                            <div class="h-6 w-6 rounded-full border-2 border-gray-200 dark:border-gray-600"></div>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $groupLabel }}</p>
                                        <div class="mt-0.5 flex items-center gap-1.5">
                                            @if($groupAllDL && $groupDone < $groupTotal)
                                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400">Deadline Terlewat</span>
                                            @else
                                                <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700">{{ $groupDone }}/{{ $groupTotal }} poin selesai</span>
                                            @endif
                                        </div>
                                    </div>
                                    <svg class="h-4 w-4 flex-shrink-0 text-gray-300 transition-transform duration-200"
                                         :class="openGroup ? 'rotate-90' : ''"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                                    </svg>
                                </button>

                                <div x-show="openGroup"
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100"
                                     x-transition:leave="transition ease-in duration-150"
                                     x-transition:leave-start="opacity-100"
                                     x-transition:leave-end="opacity-0"
                                     style="display:none"
                                     class="divide-y divide-gray-50 border-t border-gray-100 bg-gray-50/50 dark:divide-gray-800/50 dark:border-gray-800 dark:bg-gray-900/50">

                                    @foreach($groupTasks as $cleanTask)
                                        @php
                                            $cReview    = $cleanTask['reviewStatus']?->value ?? null;
                                            $cApproved  = $cReview === 'approved';
                                            $cRejected  = $cReview === 'rejected';
                                            $cPending   = $cReview === 'pending';
                                            $cPastDL    = $cleanTask['isPastDeadline'];
                                            $cClickable = ! $cPastDL && ($cRejected || (! $cleanTask['reviewStatus'] && ! $cleanTask['isCompleted']));
                                        @endphp

                                        @if($cClickable)
                                            <button type="button"
                                                    wire:click="openTaskModal('{{ $cleanTask['key'] }}')"
                                                    class="flex w-full items-start gap-3 py-3 pl-10 pr-4 text-left transition active:bg-gray-100 dark:active:bg-gray-800">
                                        @else
                                            <div class="flex w-full items-start gap-3 py-3 pl-10 pr-4">
                                        @endif
                                            <div class="mt-0.5 flex-shrink-0">
                                                @if($cApproved)
                                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-green-500">
                                                        <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                                    </div>
                                                @elseif($cRejected)
                                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-red-500">
                                                        <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                                    </div>
                                                @elseif($cPastDL)
                                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-gray-300 dark:bg-gray-600">
                                                        <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                                    </div>
                                                @elseif($cPending)
                                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-amber-400">
                                                        <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                                    </div>
                                                @elseif($cleanTask['isCompleted'])
                                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-green-500">
                                                        <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                                    </div>
                                                @else
                                                    <div class="h-5 w-5 rounded-full border-2 border-gray-200 dark:border-gray-600"></div>
                                                @endif
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-xs font-medium {{ ($cApproved || $cleanTask['isCompleted']) && ! $cRejected ? 'text-gray-400 line-through dark:text-gray-500' : ($cPastDL ? 'text-gray-400 dark:text-gray-500' : 'text-gray-700 dark:text-gray-300') }}">
                                                    {{ $cleanTask['label'] }}
                                                </p>
                                                <div class="mt-0.5 flex flex-wrap items-center gap-1">
                                                    @if($cApproved)
                                                        <span class="rounded bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700">Disetujui</span>
                                                    @elseif($cRejected)
                                                        <span class="rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700">Ditolak</span>
                                                    @elseif($cPastDL)
                                                        <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400">Deadline Terlewat</span>
                                                    @elseif($cPending)
                                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700">Menunggu Review</span>
                                                    @elseif($cleanTask['completedAt'])
                                                        <span class="text-xs text-gray-400">{{ $cleanTask['completedAt']->format('H.i') }}</span>
                                                    @else
                                                        <span class="text-xs text-gray-400">{{ $cleanTask['noteType'] }}</span>
                                                    @endif
                                                </div>
                                                @if($cRejected && $cleanTask['rejectionReason'])
                                                    <p class="mt-0.5 text-xs text-red-600 dark:text-red-400">{{ $cleanTask['rejectionReason'] }}</p>
                                                    <p class="mt-0.5 text-xs font-medium text-red-500">Tap untuk submit ulang</p>
                                                @endif
                                            </div>
                                            @if($cClickable)
                                                <svg class="mt-0.5 h-3.5 w-3.5 flex-shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                                                </svg>
                                            @endif
                                        @if($cClickable)
                                            </button>
                                        @else
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                    </div>

                </div>
            @endforeach

        </div>
    </div>

    {{-- ════════════════════════════════════════════
         TASK MODAL (bottom sheet)
    ════════════════════════════════════════════ --}}
    <div x-show="showModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-end"
         style="display:none">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"
             @click="closeModal(); $wire.set('activeTaskKey', null)"></div>

        {{-- Sheet --}}
        <div class="relative w-full rounded-t-3xl bg-white dark:bg-gray-900"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full">

            {{-- Handle --}}
            <div class="flex justify-center pb-1 pt-3">
                <div class="h-1 w-10 rounded-full bg-gray-200 dark:bg-gray-700"></div>
            </div>

            {{-- Title --}}
            <div class="px-5 pb-2 pt-2">
                @if($this->activeTaskKey)
                    <p class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ $this->activeTaskLabel }}
                    </p>
                    <p class="mt-0.5 text-sm text-gray-500">
                        {{ $this->activeTaskNoteType }}
                    </p>
                @endif
            </div>

            {{-- Form body --}}
            @php $submissionType = $this->activeSubmissionType; @endphp
            <div class="px-5 pb-4 pt-2" wire:key="task-form-{{ $taskModalKey }}">

                @if($this->activeTaskKey)

                    @if(in_array($submissionType, ['camera_only', 'photo', 'photo_and_text']))
                        {{-- ── Photo section ── --}}
                        <div class="mb-4">
                            <div class="mb-1.5 flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Foto Bukti
                                    <span class="ml-1 text-xs font-normal text-red-500">* Wajib</span>
                                </p>
                                @if(count($this->cameraPhotoPaths) > 0)
                                    <span class="text-xs text-gray-400">{{ count($this->cameraPhotoPaths) }}/5 foto</span>
                                @endif
                            </div>

                            <input type="file"
                                   accept="image/*"
                                   x-ref="photoInput"
                                   @change="handleFileUpload($event)"
                                   class="hidden">
                            <div class="grid grid-cols-3 gap-2">

                                @foreach($this->cameraPhotoPaths as $index => $path)
                                    <div class="relative aspect-square overflow-hidden rounded-xl">
                                        <img src="{{ asset('storage/' . $path) }}"
                                             class="h-full w-full object-cover"
                                             alt="Foto {{ $index + 1 }}">
                                        <button wire:click="removePhoto({{ $index }})"
                                                wire:loading.attr="disabled"
                                                class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-white shadow transition active:bg-black/80">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                @endforeach

                                <div x-show="previewSrc" x-cloak
                                     class="relative aspect-square overflow-hidden rounded-xl bg-gray-100">
                                    <img :src="previewSrc" class="h-full w-full object-cover">
                                    <div class="absolute inset-0 flex items-center justify-center bg-black/40">
                                        <svg class="h-5 w-5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                    </div>
                                </div>

                                @if(count($this->cameraPhotoPaths) < 5)
                                    <button @click="openCamera()"
                                            x-show="!uploading"
                                            class="flex aspect-square flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-blue-300 bg-blue-50 transition active:bg-blue-100 dark:border-blue-700 dark:bg-blue-950/30">
                                        <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                                        </svg>
                                        <span class="text-xs font-medium text-blue-600 dark:text-blue-400">Kamera</span>
                                    </button>
                                    @if($submissionType !== 'camera_only')
                                        <button @click="$refs.photoInput.click()"
                                                x-show="!uploading"
                                                class="flex aspect-square flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-blue-300 bg-blue-50 transition active:bg-blue-100 dark:border-blue-700 dark:bg-blue-950/30">
                                            <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                                            </svg>
                                            <span class="text-xs font-medium text-blue-600 dark:text-blue-400">Galeri</span>
                                        </button>
                                    @endif
                                @endif
                            </div>
                            @if(count($this->cameraPhotoPaths) === 0)
                                <p x-show="!uploading" class="mt-1.5 text-center text-xs text-gray-400">
                                    Timestamp &amp; lokasi otomatis di semua foto
                                </p>
                            @endif
                        </div>
                    @endif

                    {{-- ── Notes / Text section ── --}}
                    @if(in_array($submissionType, ['text_only', 'photo_and_text']))
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $submissionType === 'text_only' ? $this->activeTaskLabel : 'Catatan' }}
                                <span class="ml-1 text-xs font-normal text-red-500">* Wajib</span>
                            </label>
                            <textarea wire:model="taskData.notes"
                                      rows="{{ $submissionType === 'text_only' ? 6 : 3 }}"
                                      placeholder="{{ $submissionType === 'text_only' ? 'Paste atau ketik isi di sini...' : 'Tambahkan catatan...' }}"
                                      class="mt-1.5 w-full resize-none rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500"></textarea>
                        </div>
                    @elseif($submissionType === 'photo')
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Catatan
                                <span class="ml-1 text-xs font-normal text-gray-400">(opsional)</span>
                            </label>
                            <textarea wire:model="taskData.notes"
                                      rows="2"
                                      placeholder="Tambahkan catatan..."
                                      class="mt-1.5 w-full resize-none rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500"></textarea>
                        </div>
                    @endif

                @endif
            </div>

            {{-- Action buttons --}}
            <div class="flex gap-3 px-5 pb-10">
                <button type="button"
                        @click="closeModal(); $wire.set('activeTaskKey', null)"
                        class="flex-1 rounded-2xl border border-gray-200 py-3.5 text-sm font-semibold text-gray-600 transition active:bg-gray-50">
                    Batal
                </button>
                <button type="button"
                        wire:click="saveTask"
                        wire:loading.attr="disabled"
                        x-bind:disabled="uploading"
                        class="flex-1 rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white shadow-sm transition active:bg-blue-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="saveTask" x-show="!uploading">Simpan</span>
                    <span x-show="uploading">Memproses foto...</span>
                    <span wire:loading wire:target="saveTask">Menyimpan...</span>
                </button>
            </div>
        </div>
    </div>

    <x-filament-actions::modals />

    <x-briefing.bottom-nav active="briefing" />
</div>
