@php
    $user     = auth()->user();
    $initials = collect(explode(' ', $user->name))
        ->take(2)->map(fn ($w) => strtoupper($w[0]))->join('');
    $monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
    $joinedDate = $monthNames[$user->created_at->month - 1] . ' ' . $user->created_at->year;
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900"
     style="min-height:100dvh"
     x-data="{
         mode: 'idle',
         photo: null,
         stream: null,
         uploading: false,
         camError: null,
         currentFacing: 'user',

         get hasPhoto()  { return !!this.photo; },
         get hasStream() { return !!this.stream; },

         openPicker() { this.mode = 'picker'; },

         chooseCamera() {
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
             const reader = new FileReader();
             reader.onload = (e) => { this.photo = e.target.result; };
             reader.readAsDataURL(file);
             this.uploading = true;
             this.$wire.upload('photo', file,
                 () => { this.uploading = false; },
                 () => { this.uploading = false; }
             );
             event.target.value = '';
         },

         close() {
             this.stopStream();
             this.$wire.set('photo', null);
             this.photo = null;
             this.mode  = 'idle';
         },

         clearPhoto() {
             this.$wire.set('photo', null);
             this.photo = null;
         },

         async startCamera() {
             this.camError = null;
             if (!navigator.mediaDevices?.getUserMedia) {
                 this.camError = 'Kamera tidak tersedia. Akses kamera memerlukan koneksi HTTPS.';
                 return;
             }
             try {
                 this.stream = await navigator.mediaDevices.getUserMedia({
                     video: { facingMode: this.currentFacing, width: { ideal: 720 }, height: { ideal: 720 } },
                     audio: false
                 });
                 await this.$nextTick();
                 const v = this.$refs.video;
                 if (v) { v.srcObject = this.stream; await v.play(); }
             } catch (e) {
                 this.camError = 'Kamera tidak dapat diakses: ' + e.message;
             }
         },

         async flipCamera() {
             this.stopStream();
             this.currentFacing = this.currentFacing === 'user' ? 'environment' : 'user';
             await this.startCamera();
         },

         shoot() {
             const v = this.$refs.video, c = this.$refs.canvas;
             if (!v || !c) return;
             const s = Math.min(v.videoWidth, v.videoHeight) || 480;
             c.width = c.height = s;
             const ctx = c.getContext('2d');
             if (this.currentFacing === 'user') {
                 ctx.save(); ctx.scale(-1, 1);
                 ctx.drawImage(v, -(v.videoWidth - s) / 2 - s, -(v.videoHeight - s) / 2, v.videoWidth, v.videoHeight);
                 ctx.restore();
             } else {
                 ctx.drawImage(v, -(v.videoWidth - s) / 2, -(v.videoHeight - s) / 2, v.videoWidth, v.videoHeight);
             }
             this.photo = c.toDataURL('image/jpeg', 0.9);
             this.stopStream();
             c.toBlob((blob) => {
                 const file = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
                 this.uploading = true;
                 this.$wire.upload('photo', file,
                     () => { this.uploading = false; },
                     () => { this.uploading = false; this.camError = 'Gagal mengunggah foto. Silakan coba kembali.'; }
                 );
             }, 'image/jpeg', 0.9);
         },

         retake() {
             this.photo    = null;
             this.camError = null;
             this.$wire.set('photo', null);
             this.startCamera();
         },

         stopStream() {
             if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
         },

         usePhoto() { this.stopStream(); this.mode = 'idle'; }
     }"
     @keydown.escape.window="close()">

    {{-- Hidden file input --}}
    <input x-ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFilePicked($event)">

    {{-- ════════════════════════════════════════════
         ORANGE HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-12 pt-14 text-center">

        {{-- Back arrow --}}
        <div class="mb-6 flex items-center">
            <a href="{{ \App\Filament\Casual\Resources\ServiceRequests\ServiceRequestResource::getUrl('index') }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
        </div>

        {{-- Avatar --}}
        <div class="relative mx-auto w-fit">
            <div class="h-24 w-24 overflow-hidden rounded-full ring-4 ring-orange-300/60">
                <template x-if="hasPhoto">
                    <img :src="photo" class="h-full w-full object-cover" alt="">
                </template>
                <template x-if="!hasPhoto">
                    <div class="h-full w-full">
                        @if($user->avatar)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($user->avatar, now()->addHour()) }}"
                                 class="h-full w-full object-cover"
                                 alt="{{ $user->name }}">
                        @else
                            <div class="flex h-full w-full items-center justify-center bg-blue-500 text-2xl font-semibold text-white">
                                {{ $initials }}
                            </div>
                        @endif
                    </div>
                </template>
            </div>

            <button @click="openPicker()"
                    x-show="!hasPhoto && !uploading"
                    class="absolute -bottom-1 -right-1 flex h-9 w-9 items-center justify-center rounded-full bg-white text-blue-600 ring-2 ring-blue-100 transition active:scale-90">
                <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                </svg>
            </button>

            <div x-show="uploading"
                 class="absolute inset-0 flex items-center justify-center rounded-full bg-black/50">
                <svg class="h-6 w-6 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
            </div>
        </div>

        <h1 class="mt-4 text-2xl font-semibold text-white">{{ $user->name }}</h1>

        <div class="mt-2 flex items-center justify-center gap-2">
            <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-semibold text-white">Teknisi</span>
        </div>

        <div class="mt-3 flex items-center justify-center gap-1.5 text-blue-200">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
            </svg>
            <span class="text-xs font-medium">Bergabung {{ $joinedDate }}</span>
        </div>

        <div x-show="hasPhoto && !uploading" x-transition class="mt-5 flex gap-3">
            <button @click="clearPhoto()"
                    class="flex flex-1 items-center justify-center rounded-2xl bg-white/20 py-3 text-sm font-semibold text-white transition active:bg-white/30">
                Batalkan
            </button>
            <button wire:click="savePhoto"
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-2xl bg-white py-3 text-sm font-semibold text-blue-600 transition active:scale-95">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
                Simpan Foto
            </button>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 rounded-t-3xl bg-gray-50 pb-28 dark:bg-gray-950">

        {{-- Info section --}}
        <div class="mx-5 mt-5 overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">

            <div class="flex items-center justify-between px-5 py-4">
                <span class="text-sm text-gray-400">Mobile No.</span>
                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $user->phone ?? '—' }}</span>
            </div>
            <div class="mx-5 h-px bg-gray-100 dark:bg-gray-800"></div>

            <div class="flex items-center justify-between px-5 py-4">
                <span class="text-sm text-gray-400">Email ID</span>
                <span class="max-w-[60%] truncate text-right text-sm font-semibold text-gray-900 dark:text-white">{{ $user->email }}</span>
            </div>
            <div class="mx-5 h-px bg-gray-100 dark:bg-gray-800"></div>

        </div>

        {{-- Actions section --}}
        <div class="mx-5 mt-4 overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">

            <a href="{{ \App\Filament\Casual\Resources\ServiceRequests\ServiceRequestResource::getUrl('index') }}"
               class="flex w-full items-center gap-3 px-5 py-4 transition active:bg-gray-50 dark:active:bg-gray-800">
                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30">
                    <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z"/>
                    </svg>
                </div>
                <span class="flex-1 font-semibold text-gray-900 dark:text-white">Pekerjaan Saya</span>
                <svg class="h-4 w-4 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                </svg>
            </a>

            <div class="mx-5 h-px bg-gray-100 dark:bg-gray-800"></div>

            <a href="{{ \App\Filament\Casual\Pages\TechnicianHistoryPage::getUrl() }}"
               class="flex w-full items-center gap-3 px-5 py-4 transition active:bg-gray-50 dark:active:bg-gray-800">
                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30">
                    <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                    </svg>
                </div>
                <span class="flex-1 font-semibold text-gray-900 dark:text-white">Riwayat Pekerjaan</span>
                <svg class="h-4 w-4 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                </svg>
            </a>

            <div class="mx-5 h-px bg-gray-100 dark:bg-gray-800"></div>

            <button wire:click="logout"
                    class="flex w-full items-center gap-3 px-5 py-4 transition active:bg-red-50 dark:active:bg-red-900/10">
                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-red-100 dark:bg-red-900/30">
                    <svg class="h-5 w-5 text-red-500 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
                    </svg>
                </div>
                <span class="flex-1 text-left font-semibold text-red-500 dark:text-red-400">Keluar</span>
                <svg class="h-4 w-4 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                </svg>
            </button>
        </div>

    </div>

    {{-- ════════════════════════════════════════════
         PICKER SHEET
    ════════════════════════════════════════════ --}}

    <div x-show="mode === 'picker' || mode === 'camera'"
         x-transition:enter="transition duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40 bg-black/60"
         style="display:none"
         @click="close()">
    </div>

    <div x-show="mode === 'picker'"
         x-transition:enter="transition duration-300 ease-out"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition duration-200 ease-in"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full"
         class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] z-50 overflow-hidden rounded-t-3xl bg-white dark:bg-gray-900"
         style="display:none">

        <div class="flex justify-center pb-2 pt-3">
            <div class="h-1 w-10 rounded-full bg-gray-200 dark:bg-gray-700"></div>
        </div>

        <div class="flex flex-col items-center gap-3 px-5 pb-5 pt-3">
            <div class="relative">
                <div class="h-16 w-16 overflow-hidden rounded-full ring-4 ring-blue-100 dark:ring-orange-900/40">
                    @if($user->avatar)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($user->avatar, now()->addHour()) }}"
                             class="h-full w-full object-cover" alt="{{ $user->name }}">
                    @else
                        <div class="flex h-full w-full items-center justify-center bg-blue-500 text-xl font-semibold text-white">
                            {{ $initials }}
                        </div>
                    @endif
                </div>
                <div class="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 ring-2 ring-white dark:ring-gray-900">
                    <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                </div>
            </div>
            <div class="text-center">
                <p class="font-semibold text-gray-900 dark:text-white">Ganti Foto Profil</p>
                <p class="mt-0.5 text-xs text-gray-400">Pilih sumber foto</p>
            </div>
        </div>

        <div class="flex flex-col gap-3 px-5 pb-4">
            <button @click="chooseCamera()"
                    class="flex w-full items-center gap-4 rounded-2xl bg-orange-50 px-4 py-3.5 text-left transition active:bg-blue-100 dark:bg-blue-900/20">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-blue-600">
                    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-gray-900 dark:text-white">Kamera</p>
                    <p class="text-xs text-gray-400">Ambil foto melalui kamera</p>
                </div>
                <svg class="h-4 w-4 flex-shrink-0 text-orange-300 dark:text-orange-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                </svg>
            </button>

            <button @click="chooseGallery()"
                    class="flex w-full items-center gap-4 rounded-2xl bg-gray-50 px-4 py-3.5 text-left transition active:bg-gray-100 dark:bg-gray-800">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-gray-800 dark:bg-gray-700">
                    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-gray-900 dark:text-white">Galeri / File</p>
                    <p class="text-xs text-gray-400">Pilih dari foto tersimpan</p>
                </div>
                <svg class="h-4 w-4 flex-shrink-0 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                </svg>
            </button>
        </div>

        <div class="px-5 pb-10">
            <button @click="close()"
                    class="flex w-full items-center justify-center rounded-2xl bg-gray-100 py-3.5 text-sm font-semibold text-gray-500 transition active:bg-gray-200 dark:bg-gray-800 dark:text-gray-400">
                Batal
            </button>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         CAMERA SHEET
    ════════════════════════════════════════════ --}}

    <div x-show="mode === 'camera'"
         x-transition:enter="transition duration-300 ease-out"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition duration-200 ease-in"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full"
         class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] z-50 flex flex-col overflow-hidden rounded-t-3xl bg-gray-950"
         style="height:88vh; display:none">

        <div class="flex justify-center pb-1 pt-3">
            <div class="h-1 w-10 rounded-full bg-white/20"></div>
        </div>

        <div class="flex items-center justify-between px-5 pb-4 pt-3">
            <div>
                <div class="text-xs font-semibold uppercase tracking-widest text-blue-400">Foto Profil</div>
                <div class="mt-0.5 text-sm text-white/60">Posisikan wajah dalam lingkaran</div>
            </div>
            <button @click="close()"
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="relative flex-1 overflow-hidden">
            <video x-ref="video" x-show="hasStream && !hasPhoto"
                   autoplay muted playsinline
                   class="absolute inset-0 h-full w-full object-cover"
                   :style="currentFacing === 'user' ? 'transform:scaleX(-1)' : ''"></video>

            <div x-show="hasStream && !hasPhoto"
                 class="pointer-events-none absolute inset-0 flex items-center justify-center">
                <div class="h-64 w-64 rounded-full border-2 border-dashed border-white/50"
                     style="box-shadow:0 0 0 9999px rgba(0,0,0,.4)"></div>
            </div>

            <img x-show="hasPhoto" :src="photo" class="absolute inset-0 h-full w-full object-cover" alt="">

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
                <span class="text-sm">Mengunggah foto...</span>
            </div>
        </div>

        <canvas x-ref="canvas" class="hidden"></canvas>

        <div class="px-6 pb-12 pt-5">
            <div x-show="hasStream && !hasPhoto" class="flex items-center justify-between">
                <div class="h-12 w-12"></div>
                <button @click="shoot()"
                        class="flex h-20 w-20 items-center justify-center rounded-full ring-4 ring-white/30 transition active:scale-90">
                    <div class="h-14 w-14 rounded-full bg-white"></div>
                </button>
                <button @click="flipCamera()"
                        class="flex h-12 w-12 items-center justify-center rounded-full bg-white/15 text-white transition active:bg-white/25">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
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
                <button @click="usePhoto()"
                        class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-blue-600 py-4 text-sm font-semibold text-white transition active:scale-95">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                    Gunakan Foto
                </button>
            </div>

            <p x-show="hasStream && !hasPhoto" class="mt-4 text-center text-xs text-white/40">
                Posisikan wajah dalam lingkaran, lalu tekan tombol
            </p>
        </div>
    </div>

    <x-technician.bottom-nav active="profile" />

</div>
