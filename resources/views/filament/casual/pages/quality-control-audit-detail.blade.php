<div x-data="{
        showItemModal: false,
        showSummaryModal: false,

        cameraOpen: false,
        stream: null,
        stampTime: '',
        stampLocation: 'Mendapatkan lokasi...',
        locationCoords: null,
        previewSrc: null,
        timeInterval: null,
        currentFacing: 'environment',
        selectedTimer: 0,
        countdown: null,
        countdownInterval: null,
        uploading: false,

        async openCamera() {
            this.currentFacing = 'environment';
            this.previewSrc = null;
            this.uploading = false;
            this.cameraOpen = true;

            this.updateStampTime();
            this.timeInterval = setInterval(() => this.updateStampTime(), 1000);

            this.initLocation();

            await this.startStream();
        },

        async startStream() {
            if (this.stream) {
                this.stream.getTracks().forEach(t => t.stop());
                this.stream = null;
            }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: this.currentFacing,
                        width: { ideal: 1280 },
                        height: { ideal: 960 },
                    },
                    audio: false,
                });
                await this.$nextTick();
                const video = document.getElementById('qc-cam-video');
                if (video) {
                    video.srcObject = this.stream;
                }
            } catch (err) {
                clearInterval(this.timeInterval);
                this.cameraOpen = false;
                alert('Tidak dapat mengakses kamera. Pastikan izin kamera sudah diberikan.');
            }
        },

        async flipCamera() {
            this.currentFacing = this.currentFacing === 'user' ? 'environment' : 'user';
            await this.startStream();
        },

        closeCamera() {
            this.cancelCountdown();
            if (this.stream) {
                this.stream.getTracks().forEach(t => t.stop());
                this.stream = null;
            }
            clearInterval(this.timeInterval);
            this.cameraOpen = false;
        },

        cancelCountdown() {
            if (this.countdownInterval) {
                clearInterval(this.countdownInterval);
                this.countdownInterval = null;
            }
            this.countdown = null;
        },

        startCapture() {
            if (this.countdown !== null || this.uploading) return;

            if (this.selectedTimer === 0) {
                this.capturePhoto();
                return;
            }

            this.countdown = this.selectedTimer;
            this.countdownInterval = setInterval(() => {
                this.countdown--;
                if (this.countdown <= 0) {
                    clearInterval(this.countdownInterval);
                    this.countdownInterval = null;
                    this.capturePhoto();
                }
            }, 1000);
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
            const video = document.getElementById('qc-cam-video');
            if (!video || !video.videoWidth) return;

            const W = video.videoWidth;
            const H = video.videoHeight;

            const tmp = document.createElement('canvas');
            tmp.width = W;
            tmp.height = H;
            const tmpCtx = tmp.getContext('2d');
            if (this.currentFacing === 'user') {
                tmpCtx.translate(W, 0);
                tmpCtx.scale(-1, 1);
            }
            tmpCtx.drawImage(video, 0, 0);
            tmpCtx.setTransform(1, 0, 0, 1, 0, 0);

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
    }"
     @open-item-modal.window="showItemModal = true"
     @close-item-modal.window="showItemModal = false"
     @open-summary-modal.window="showSummaryModal = true"
     @close-summary-modal.window="showSummaryModal = false">
@php
    $audit = $this->auditModel;
    $items = $audit->items;
    $sections = $items->groupBy('section_code');
    $answeredCount = $items->whereNotNull('result')->count();
    $totalCount = $items->count();
    $progress = $totalCount > 0 ? round($answeredCount / $totalCount * 100) : 0;
    $isDraft = $audit->status === 'draft';

    $ratingConfig = match($audit->rating) {
        'green'  => ['label' => 'Green',  'bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dark' => 'dark:bg-emerald-900/30 dark:text-emerald-400'],
        'yellow' => ['label' => 'Yellow', 'bg' => 'bg-amber-100',   'text' => 'text-amber-700',   'dark' => 'dark:bg-amber-900/30 dark:text-amber-400'],
        'red'    => ['label' => 'Red',     'bg' => 'bg-red-100',    'text' => 'text-red-700',     'dark' => 'dark:bg-red-900/30 dark:text-red-400'],
        default  => ['label' => 'Belum Dinilai', 'bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'dark' => 'dark:bg-gray-800 dark:text-gray-400'],
    };
@endphp

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
        <video id="qc-cam-video"
               autoplay
               playsinline
               muted
               :class="currentFacing === 'user' ? '-scale-x-100' : ''"
               class="h-full w-full object-cover"></video>

        {{-- Timer countdown --}}
        <div x-show="countdown !== null"
             x-cloak
             class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/20">
            <span x-text="countdown"
                  class="flex h-28 w-28 items-center justify-center rounded-full bg-black/60 text-6xl font-bold text-white shadow-2xl ring-4 ring-white/80"></span>
        </div>

        {{-- Live timestamp overlay --}}
        <div class="absolute bottom-0 left-0 right-0 bg-black/60 px-3 py-2.5">
            <p x-text="stampTime"
               class="font-mono text-sm font-bold leading-tight text-white"></p>
            <p x-text="stampLocation"
               class="mt-0.5 font-mono text-xs leading-tight text-green-300"></p>
        </div>
    </div>

    {{-- Timer and capture controls --}}
    <div class="flex flex-shrink-0 flex-col gap-5 bg-black px-6 pb-8 pt-4">
        <div class="flex items-center justify-center gap-2" aria-label="Timer kamera">
            <template x-for="seconds in [0, 5, 10, 20]" :key="seconds">
                <button type="button"
                        @click="selectedTimer = seconds"
                        :disabled="countdown !== null"
                        :class="selectedTimer === seconds ? 'bg-white text-black' : 'bg-white/15 text-white'"
                        class="min-w-14 rounded-full px-3 py-1.5 text-xs font-semibold transition disabled:opacity-50"
                        x-text="seconds === 0 ? 'Mati' : seconds + ' dtk'"></button>
            </template>
        </div>
        <div class="flex items-center justify-between px-2">
            <div style="width:48px"></div>
            <button @click="startCapture()"
                    :disabled="countdown !== null"
                    aria-label="Ambil foto"
                    class="flex items-center justify-center rounded-full border-4 border-white/70 bg-white shadow-xl transition active:scale-90 disabled:opacity-60"
                    style="height:72px;width:72px">
                <div class="rounded-full bg-white ring-2 ring-gray-300" style="height:56px;width:56px"></div>
            </button>
            <button @click="flipCamera()"
                    :disabled="countdown !== null"
                    aria-label="Balik kamera"
                    class="flex items-center justify-center rounded-full bg-white/15 text-white transition active:bg-white/25 disabled:opacity-40"
                    style="height:48px;width:48px">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                </svg>
            </button>
        </div>
    </div>
</div>

<div class="flex flex-col bg-blue-600" style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-6 pt-14">
        <div class="mb-3 flex items-center gap-3">
            <a href="{{ \App\Filament\Casual\Pages\QualityControlAudits::getUrl(panel: 'casual') }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-blue-200">Detail Audit</p>
                <p class="truncate text-base font-semibold text-white">{{ $audit->branch?->name ?? 'Tanpa Cabang' }}</p>
            </div>
        </div>

        <p class="mb-2 text-xs text-blue-200">{{ $audit->audit_number }} · {{ $audit->audit_date?->format('d M Y') }}</p>

        {{-- Progress bar --}}
        <div class="h-1.5 overflow-hidden rounded-full bg-white/20">
            <div class="h-1.5 rounded-full bg-white transition-all duration-500" style="width:{{ $progress }}%"></div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-32 pt-5 dark:bg-gray-950">

        {{-- ── Info Audit ── --}}
        <div class="mx-5 mb-5 overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">

            {{-- Nomor Audit --}}
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3.5 dark:border-gray-800">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800">
                        <svg class="h-4.5 w-4.5 text-slate-600 dark:text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Nomor Audit</p>
                        <p class="truncate font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ $audit->audit_number }}</p>
                    </div>
                </div>
                <span class="flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $isDraft ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $isDraft ? 'animate-pulse bg-amber-500' : 'bg-emerald-500' }}"></span>
                    {{ $isDraft ? 'Sedang Berjalan' : 'Submitted' }}
                </span>
            </div>

            {{-- Auditor --}}
            <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3.5 dark:border-gray-800">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-900/20">
                    <svg class="h-4.5 w-4.5 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Auditor</p>
                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $audit->auditor?->name ?? '—' }}</p>
                    <p class="text-[11px] text-gray-400">{{ $audit->store_leader_present ? 'Store Leader: '.($audit->store_leader_name ?? '—') : 'Tanpa Store Leader' }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2">
                {{-- Skor --}}
                <div class="flex items-center gap-3 border-r border-gray-100 px-4 py-3.5 dark:border-gray-800">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $ratingConfig['bg'] }} {{ $ratingConfig['dark'] }}">
                        <svg class="h-4.5 w-4.5 {{ $ratingConfig['text'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Skor</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format((float) $audit->score, 1) }}%</p>
                        <p class="text-[11px] text-gray-400">{{ $ratingConfig['label'] }}</p>
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
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $answeredCount }}/{{ $totalCount }} poin</p>
                        <p class="text-[11px] text-gray-400">{{ $progress }}% selesai</p>
                    </div>
                </div>
            </div>
        </div>

        @foreach($sections as $sectionCode => $sectionItems)
            @php
                $sectionAnswered = $sectionItems->filter(fn ($i) => $i->result !== null)->count();
                $sectionTotal = $sectionItems->count();
                $sectionPct = $sectionTotal > 0 ? round($sectionAnswered / $sectionTotal * 100) : 0;
            @endphp

            {{-- Section divider --}}
            <div class="mx-5 mb-3 mt-5 flex items-center gap-2">
                <div class="h-px flex-1 bg-blue-100 dark:bg-blue-900/30"></div>
                <div class="flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-blue-600 ring-1 ring-blue-100 dark:bg-blue-900/20 dark:text-blue-400 dark:ring-blue-800">
                    <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-blue-600 text-[9px] font-bold text-white">{{ $sectionCode }}</span>
                    <span class="text-xs font-semibold">Section {{ $sectionCode }}</span>
                </div>
                <div class="h-px flex-1 bg-blue-100 dark:bg-blue-900/30"></div>
            </div>

            <div class="mx-5 overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">

                {{-- Section header --}}
                <div class="flex items-center gap-2.5 px-4 pb-3 pt-4">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20">
                        <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $sectionItems->first()->section_name }}</p>
                        <p class="text-xs text-gray-400">{{ $sectionAnswered }}/{{ $sectionTotal }} poin terisi</p>
                    </div>
                </div>

                {{-- Progress bar --}}
                <div class="mx-4 mb-3 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                    <div class="h-full rounded-full bg-blue-500 transition-all duration-500" style="width: {{ $sectionPct }}%"></div>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($sectionItems as $item)
                    @php
                        $isAnswered = $item->result !== null;
                        $resultConfig = match(true) {
                            ! $isAnswered => ['icon' => 'dot', 'bg' => 'bg-gray-100 dark:bg-gray-800', 'text' => 'text-gray-300 dark:text-gray-600'],
                            $item->earned_points >= $item->maximum_points && $item->maximum_points > 0 => ['icon' => 'check', 'bg' => 'bg-emerald-100 dark:bg-emerald-900/30', 'text' => 'text-emerald-600 dark:text-emerald-400'],
                            $item->earned_points <= 0 => ['icon' => 'x', 'bg' => 'bg-red-100 dark:bg-red-900/30', 'text' => 'text-red-600 dark:text-red-400'],
                            default => ['icon' => 'dash', 'bg' => 'bg-amber-100 dark:bg-amber-900/30', 'text' => 'text-amber-600 dark:text-amber-400'],
                        };
                    @endphp
                    <button type="button" wire:click="openItemModal({{ $item->id }})"
                            class="flex w-full items-start gap-3 px-4 py-3.5 text-left active:bg-gray-50 dark:active:bg-gray-800/50">
                        <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full {{ $resultConfig['bg'] }} {{ $resultConfig['text'] }}">
                            @if($resultConfig['icon'] === 'check')
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            @elseif($resultConfig['icon'] === 'x')
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            @elseif($resultConfig['icon'] === 'dash')
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                            @else
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium capitalize text-gray-900 dark:text-white">{{ $item->question }}</p>
                            <div class="mt-1 flex items-center gap-2">
                                @if($isAnswered)
                                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $item->earned_points }}/{{ $item->maximum_points }} poin</span>
                                @else
                                    <span class="text-xs text-gray-400">Maks {{ $item->maximum_points }} poin</span>
                                @endif
                                @if($item->is_critical)
                                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Critical</span>
                                @endif
                            </div>
                        </div>
                        <svg class="mt-1 h-4 w-4 shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    </button>
                @endforeach
                </div>
            </div>
        @endforeach

        {{-- ── Ringkasan ── --}}
        <div class="mx-5 mb-3 mt-4">
            <button wire:click="openSummaryModal"
                    class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-white px-3 py-2.5 text-xs font-semibold text-gray-600 ring-1 ring-black/5 dark:bg-gray-900 dark:text-gray-300 dark:ring-white/10">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487 18.549 2.8a2.121 2.121 0 0 1 3 3l-1.687 1.688m-3-3L6.75 12.606a4.5 4.5 0 0 0-1.13 1.897l-.815 2.828a.5.5 0 0 0 .62.62l2.828-.815a4.5 4.5 0 0 0 1.897-1.13L19.862 7.5m-3-3 3 3"/>
                </svg>
                Ringkasan
            </button>
        </div>

        {{-- ── Submit ── --}}
        <div class="mx-5 mb-4">
            @if($isDraft)
                <button wire:click="submitAudit" wire:confirm="Submit audit ini? Pastikan semua poin sudah terisi dengan benar."
                        class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-3 py-3 text-sm font-semibold text-white transition active:scale-95">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                    Submit Audit
                </button>
            @else
                <div class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-gray-100 px-3 py-3 text-sm font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    Sudah Disubmit
                </div>
            @endif
        </div>

    </div>

</div>

{{-- ════════════════════════════════════════════
     ITEM FILL MODAL (bottom sheet)
════════════════════════════════════════════ --}}
<div x-show="showItemModal"
     x-cloak
     class="fixed inset-0 z-50 flex items-end"
     style="display:none">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"
         @click="showItemModal = false" wire:click="cancelItemModal"></div>

    {{-- Sheet --}}
    <div class="relative max-h-[85vh] w-full overflow-y-auto rounded-t-3xl bg-white dark:bg-gray-900"
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

        {{-- Title (kontekstual, mengikuti poin yang sedang dinilai) --}}
        @if($activeItemInfo)
            <div class="px-5 pb-3 pt-2">
                <div class="mb-1.5 flex items-center gap-2">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md bg-blue-600 text-[10px] font-bold text-white">{{ $activeItemInfo['section_code'] }}</span>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $activeItemInfo['section_name'] }}</p>
                </div>
                <p class="text-base font-semibold capitalize text-gray-900 dark:text-white">{{ $activeItemInfo['question'] }}</p>
                @if($activeItemInfo['check_procedure'])
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $activeItemInfo['check_procedure'] }}</p>
                @endif
                @if($activeItemInfo['is_critical'] || $activeItemInfo['requires_photo'])
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        @if($activeItemInfo['is_critical'])
                            <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Poin Kritis</span>
                        @endif
                        @if($activeItemInfo['requires_photo'])
                            <span class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Disarankan Melampirkan Foto</span>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{-- Form body --}}
        <div class="px-5 pb-4 pt-1">
            {{ $this->itemForm }}

            {{-- Foto Bukti (camera-only) --}}
            <div class="mt-4">
                <div class="mb-1.5 flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Foto Bukti
                        <span class="ml-1 text-xs font-normal text-gray-400">(opsional)</span>
                    </p>
                    @if(count($photoPaths) > 0)
                        <span class="text-xs text-gray-400">{{ count($photoPaths) }}/5 foto</span>
                    @endif
                </div>

                <div class="grid grid-cols-3 gap-2">
                    @foreach($photoPaths as $index => $path)
                        <div class="relative aspect-square overflow-hidden rounded-xl bg-black">
                            <img src="{{ Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}"
                                 class="h-full w-full object-contain" alt="Foto {{ $index + 1 }}">
                            <button wire:click="removeCameraPhoto({{ $index }})"
                                    wire:loading.attr="disabled"
                                    class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-white shadow transition active:bg-black/80">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @endforeach

                    <div x-show="previewSrc" x-cloak class="relative aspect-square overflow-hidden rounded-xl bg-black">
                        <img :src="previewSrc" class="h-full w-full object-contain">
                        <div class="absolute inset-0 flex items-center justify-center bg-black/40">
                            <svg class="h-5 w-5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </div>
                    </div>

                    @if(count($photoPaths) < 5)
                        <button type="button" @click="openCamera()"
                                x-show="!uploading"
                                class="flex aspect-square flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-blue-300 bg-blue-50 transition active:bg-blue-100 dark:border-blue-700 dark:bg-blue-950/30">
                            <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                            </svg>
                            <span class="text-xs font-medium text-blue-600 dark:text-blue-400">Kamera</span>
                        </button>
                    @endif
                </div>
                @if(count($photoPaths) === 0)
                    <p x-show="!uploading" class="mt-1.5 text-center text-xs text-gray-400">
                        Waktu dan lokasi tersemat otomatis pada setiap foto
                    </p>
                @endif
            </div>
        </div>

        {{-- Action buttons --}}
        <div class="flex gap-3 px-5 pb-10">
            <button type="button"
                    @click="showItemModal = false" wire:click="cancelItemModal"
                    class="flex-1 rounded-2xl border border-gray-200 py-3.5 text-sm font-semibold text-gray-600 transition active:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                Batal
            </button>
            <button type="button"
                    wire:click="saveItem"
                    wire:loading.attr="disabled"
                    class="flex-1 rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white shadow-sm transition active:bg-blue-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="saveItem">Simpan</span>
                <span wire:loading wire:target="saveItem">Menyimpan...</span>
            </button>
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════
     SUMMARY MODAL (bottom sheet)
════════════════════════════════════════════ --}}
<div x-show="showSummaryModal"
     x-cloak
     class="fixed inset-0 z-50 flex items-end"
     style="display:none">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"
         @click="showSummaryModal = false"></div>

    {{-- Sheet --}}
    <div class="relative max-h-[85vh] w-full overflow-y-auto rounded-t-3xl bg-white dark:bg-gray-900"
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
            <p class="text-base font-semibold text-gray-900 dark:text-white">Ringkasan Audit</p>
        </div>

        {{-- Form body --}}
        <div class="px-5 pb-4 pt-2">
            {{ $this->summaryForm }}
        </div>

        {{-- Action buttons --}}
        <div class="flex gap-3 px-5 pb-10">
            <button type="button"
                    @click="showSummaryModal = false"
                    class="flex-1 rounded-2xl border border-gray-200 py-3.5 text-sm font-semibold text-gray-600 transition active:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                Batal
            </button>
            <button type="button"
                    wire:click="saveSummary"
                    wire:loading.attr="disabled"
                    class="flex-1 rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white shadow-sm transition active:bg-blue-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="saveSummary">Simpan</span>
                <span wire:loading wire:target="saveSummary">Menyimpan...</span>
            </button>
        </div>
    </div>
</div>

</div>
