<div
     x-data="{
         fuelPrices: @js($this->fuelTypes->pluck('price_per_liter', 'name')->toArray()),

         /* ── photo state ── */
         photoCtx:         null,
         mode:             'idle',
         photo:            null,
         stream:           null,
         uploading:        false,
         camError:         null,
         source:           null,
         notaPreview:      null,
         indicatorPreview: null,

         get hasPhoto()  { return !!this.photo; },
         get hasStream() { return !!this.stream; },
         get wireProp()  { return this.photoCtx === 'nota' ? 'notaPhoto' : 'indicatorPhoto'; },

         /* ── fuel calc ── */
         onFuelTypeChange(name) {
             const price = this.fuelPrices[name] || 0;
             this.$wire.set('pricePerLiter', price);
             this.recalcTotal();
         },
         recalcTotal() {
             const liters = parseFloat(this.$wire.liters) || 0;
             const price  = parseInt(this.$wire.pricePerLiter) || 0;
             if (liters > 0 && price > 0) {
                 this.$wire.set('totalPrice', Math.round(liters * price).toString());
             }
         },

         /* ── photo flow ── */
         openPhoto(ctx) {
             this.photoCtx = ctx;
             this.photo    = null;
             this.camError = null;
             this.source   = null;
             this.mode     = 'picker';
         },

         closePhoto() {
             this.stopStream();
             this.photo    = null;
             this.mode     = 'idle';
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
                 if (this.photoCtx === 'nota') { this.notaPreview = this.photo; }
                 else { this.indicatorPreview = this.photo; }
                 c.toBlob((blob) => {
                     const stamped = new File([blob], 'foto.jpg', { type: 'image/jpeg' });
                     this.$wire.upload(this.wireProp, stamped,
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
                     video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 960 } },
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
             if (this.photoCtx === 'nota') { this.notaPreview = this.photo; }
             else { this.indicatorPreview = this.photo; }
             this.stopStream();
             c.toBlob((blob) => {
                 const file = new File([blob], 'foto.jpg', { type: 'image/jpeg' });
                 this.uploading = true;
                 this.$wire.upload(this.wireProp, file,
                     () => { this.uploading = false; },
                     () => { this.uploading = false; this.camError = 'Gagal mengunggah foto.'; }
                 );
             }, 'image/jpeg', 0.85);
         },

         retake() {
             this.$wire.set(this.wireProp, null);
             this.photo    = null;
             this.camError = null;
             if (this.photoCtx === 'nota') { this.notaPreview = null; }
             else { this.indicatorPreview = null; }
             if (this.source === 'gallery') { this.mode = 'picker'; }
             else { this.startCamera(); }
         },

         backFromCamera() {
             if (this.hasPhoto) { this.retake(); }
             else { this.stopStream(); this.mode = 'picker'; }
         },

         confirm() {
             this.stopStream();
             this.mode = 'idle';
         },

         removePhoto(ctx) {
             const prop = ctx === 'nota' ? 'notaPhoto' : 'indicatorPhoto';
             this.$wire.set(prop, null);
             if (ctx === 'nota') { this.notaPreview = null; }
             else { this.indicatorPreview = null; }
         },

         stopStream() {
             if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
         }
     }"
     @keydown.escape.window="if (mode === 'picker') closePhoto()">

<input x-ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFilePicked($event)">

<div class="flex flex-col bg-emerald-600 dark:bg-emerald-900" style="min-height:100dvh">

    {{-- HEADER --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ \App\Filament\Driver\Pages\ActiveTrip::getUrl(['trip' => $this->trip]) }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Data Pengisian BBM</span>
        </div>
        <p class="text-emerald-200">{{ $this->tripModel->tripRoute?->name ?? '' }}</p>
        <p class="text-xl font-semibold text-white">Isi detail pengisian bahan bakar</p>
    </div>

    {{-- CONTENT --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        @php
            $fieldClass = 'w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-slate-700 placeholder-slate-300 focus:border-emerald-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-900 dark:text-slate-200';
            $labelClass = 'mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-400';
        @endphp

        <div class="flex flex-col gap-4 px-5">
            <div class="flex flex-col gap-5 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">

                {{-- Jenis BBM --}}
                <div>
                    <label class="{{ $labelClass }}">Jenis BBM</label>
                    <div class="relative">
                        <select wire:model="fuelType"
                                @change="onFuelTypeChange($event.target.value)"
                                class="{{ $fieldClass }} appearance-none pr-10">
                            <option value="">-- Pilih jenis BBM --</option>
                            @foreach($this->fuelTypes as $type)
                                <option value="{{ $type->name }}">{{ $type->name }} (Rp {{ number_format($type->price_per_liter) }}/L)</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                    @error('fuelType') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Jumlah Liter --}}
                <div>
                    <label class="{{ $labelClass }}">Jumlah Liter</label>
                    <input wire:model="liters"
                           @input="recalcTotal()"
                           type="number" step="0.01" min="0.1"
                           placeholder="Contoh: 20.5"
                           class="{{ $fieldClass }}">
                    @error('liters') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Total Biaya (read-only) --}}
                <div>
                    <label class="{{ $labelClass }}">Total Biaya</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-sm text-slate-400">Rp</span>
                        <input wire:model="totalPrice" type="text" readonly
                               placeholder="Otomatis dihitung"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 pl-9 text-sm text-slate-600 placeholder-slate-300 focus:outline-none cursor-default dark:border-gray-700 dark:bg-gray-800 dark:text-slate-400">
                    </div>
                </div>

                {{-- Alamat SPBU --}}
                <div>
                    <label class="{{ $labelClass }}">Alamat SPBU</label>
                    <input wire:model="spbuAddress" type="text"
                           placeholder="CONTOH: SPBU 34.401.12 JL. MAGELANG KM.5"
                           maxlength="255"
                           autocapitalize="characters"
                           @input="$event.target.value = $event.target.value.toUpperCase()"
                           class="{{ $fieldClass }} uppercase">
                    @error('spbuAddress') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Foto Nota --}}
                <div>
                    <label class="{{ $labelClass }}">Foto Nota / Struk</label>
                    <template x-if="notaPreview">
                        <div class="mb-2 relative aspect-video overflow-hidden rounded-xl border border-gray-200">
                            <img :src="notaPreview" class="h-full w-full object-cover">
                            <button type="button" @click="removePhoto('nota')"
                                    class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-white">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                    <button type="button" @click="openPhoto('nota')"
                            class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 py-4 transition hover:border-emerald-300 hover:bg-emerald-50 dark:border-gray-700 dark:hover:border-emerald-600 dark:hover:bg-emerald-900/20">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                        </svg>
                        <span class="text-sm text-gray-400" x-text="notaPreview ? 'Ganti foto nota' : 'Tambah foto nota'"></span>
                    </button>
                    @error('notaPhoto') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Foto Indikator BBM --}}
                <div>
                    <label class="{{ $labelClass }}">Foto Indikator BBM Kendaraan</label>
                    <template x-if="indicatorPreview">
                        <div class="mb-2 relative aspect-video overflow-hidden rounded-xl border border-gray-200">
                            <img :src="indicatorPreview" class="h-full w-full object-cover">
                            <button type="button" @click="removePhoto('indicator')"
                                    class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-white">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                    <button type="button" @click="openPhoto('indicator')"
                            class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 py-4 transition hover:border-emerald-300 hover:bg-emerald-50 dark:border-gray-700 dark:hover:border-emerald-600 dark:hover:bg-emerald-900/20">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/>
                        </svg>
                        <span class="text-sm text-gray-400" x-text="indicatorPreview ? 'Ganti foto indikator' : 'Tambah foto indikator'"></span>
                    </button>
                    @error('indicatorPhoto') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

            </div>{{-- /white card --}}

            {{-- Submit --}}
            <button type="button"
                    wire:click="submit"
                    wire:loading.attr="disabled"
                    :disabled="uploading"
                    class="w-full rounded-2xl bg-emerald-600 py-3.5 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">Selesaikan Perjalanan</span>
                <span wire:loading wire:target="submit">Menyimpan...</span>
            </button>

        </div>
    </div>

</div>

{{-- ════════════════ BACKDROP ════════════════ --}}
<div x-show="mode === 'picker' || mode === 'camera'"
     x-transition:enter="transition duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-40 bg-black/60" style="display:none"
     @click="mode === 'picker' ? closePhoto() : null">
</div>

{{-- ════════════════ PICKER SHEET ════════════════ --}}
<div x-show="mode === 'picker'"
     x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
     x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
     class="fixed bottom-0 left-1/2 z-50 w-full max-w-[430px] -translate-x-1/2 overflow-hidden rounded-t-3xl bg-white dark:bg-gray-900"
     style="display:none" @click.stop>

    <div class="flex justify-center pb-2 pt-3">
        <div class="h-1 w-10 rounded-full bg-gray-200 dark:bg-gray-700"></div>
    </div>

    <div class="px-5 pb-4 pt-2">
        <p class="font-semibold text-gray-900 dark:text-white"
           x-text="photoCtx === 'nota' ? 'Foto Nota / Struk' : 'Foto Indikator BBM'"></p>
        <p class="mt-0.5 text-xs text-gray-400">Pilih cara mengambil foto</p>
    </div>

    <div class="flex flex-col gap-3 px-5 pb-4">
        <button @click="chooseCamera()"
                class="flex w-full items-center gap-4 rounded-2xl bg-emerald-50 px-4 py-3.5 text-left transition active:bg-emerald-100 dark:bg-emerald-900/20">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-600">
                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                </svg>
            </div>
            <div class="flex-1">
                <p class="font-semibold text-gray-900 dark:text-white">Kamera</p>
                <p class="text-xs text-gray-400">Ambil foto langsung dengan kamera</p>
            </div>
            <svg class="h-4 w-4 shrink-0 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
            </svg>
        </button>

        <button @click="chooseGallery()"
                class="flex w-full items-center gap-4 rounded-2xl bg-gray-50 px-4 py-3.5 text-left transition active:bg-gray-100 dark:bg-gray-800">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-800 dark:bg-gray-700">
                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                </svg>
            </div>
            <div class="flex-1">
                <p class="font-semibold text-gray-900 dark:text-white">Galeri / File</p>
                <p class="text-xs text-gray-400">Pilih dari foto tersimpan</p>
            </div>
            <svg class="h-4 w-4 shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
            </svg>
        </button>
    </div>

    <div class="px-5 pb-10">
        <button @click="closePhoto()"
                class="flex w-full items-center justify-center rounded-2xl bg-gray-100 py-3.5 text-sm font-semibold text-gray-500 transition active:bg-gray-200 dark:bg-gray-800 dark:text-gray-400">
            Batal
        </button>
    </div>
</div>

{{-- ════════════════ CAMERA OVERLAY ════════════════ --}}
<div x-show="mode === 'camera'"
     x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="opacity-0 scale-[1.02]" x-transition:enter-end="opacity-100 scale-100"
     x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-[1.02]"
     class="fixed inset-0 z-50 overflow-hidden bg-black" style="display:none">

    <video x-ref="video" x-show="hasStream && !hasPhoto" autoplay muted playsinline
           class="absolute inset-0 h-full w-full object-cover" style="display:none"></video>

    <img x-show="hasPhoto" :src="photo" class="absolute inset-0 h-full w-full object-contain" alt="" style="display:none">

    <div x-show="!hasStream && !hasPhoto && !camError"
         class="absolute inset-0 flex flex-col items-center justify-center gap-4" style="display:none">
        <svg class="h-10 w-10 animate-spin text-white/30" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        <p class="text-sm text-white/40">Membuka kamera...</p>
    </div>

    <div x-show="camError && !hasPhoto"
         class="absolute inset-0 flex flex-col items-center justify-center gap-5 px-8 text-center" style="display:none">
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

    <div x-show="uploading" class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-black/60" style="display:none">
        <svg class="h-10 w-10 animate-spin text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        <p class="text-sm text-white/80">Mengunggah foto...</p>
    </div>

    <canvas x-ref="canvas" class="hidden"></canvas>

    {{-- TOP BAR --}}
    <div class="absolute inset-x-0 top-0 z-10 rounded-b-3xl bg-emerald-600 px-5 pb-5 pt-12">
        <div class="flex items-center justify-between">
            <button @click="backFromCamera()"
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white transition active:bg-white/25">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </button>
            <div class="text-center">
                <p class="font-semibold text-white"
                   x-text="photoCtx === 'nota' ? 'Foto Nota / Struk' : 'Foto Indikator BBM'"></p>
                <p class="text-xs text-emerald-200" x-text="hasPhoto ? 'Konfirmasi foto' : 'Ambil foto'"></p>
            </div>
            <div class="h-10 w-10"></div>
        </div>
    </div>

    {{-- BOTTOM CONTROLS --}}
    <div class="absolute inset-x-0 bottom-0 z-10 px-6 pb-14 pt-8"
         style="background: linear-gradient(to top, rgba(0,0,0,0.80) 0%, transparent 100%)">

        <div x-show="hasStream && !hasPhoto" class="space-y-5" style="display:none">
            <div x-data="{ t: '' }"
                 x-init="const p = v => v.toString().padStart(2,'0');
                          setInterval(() => { const n = new Date();
                              t = p(n.getDate()) + '/' + p(n.getMonth()+1) + '/' + n.getFullYear()
                                + ' ' + p(n.getHours()) + ':' + p(n.getMinutes()) + ':' + p(n.getSeconds());
                          }, 1000);"
                 class="text-center font-mono text-base font-medium text-white/80" x-text="t">
            </div>
            <div class="flex justify-center">
                <button @click="shoot()"
                        class="relative flex h-20 w-20 items-center justify-center rounded-full ring-4 ring-white/30 transition active:scale-90">
                    <div class="h-[3.75rem] w-[3.75rem] rounded-full bg-white"></div>
                </button>
            </div>
            <p class="text-center text-xs text-white/40">Tekan untuk mengambil foto</p>
        </div>

        <div x-show="hasPhoto && !uploading" class="flex gap-3" style="display:none">
            <button @click="retake()"
                    class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-white/15 py-4 text-sm font-semibold text-white transition active:scale-95 active:bg-white/25">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                </svg>
                Ulang
            </button>
            <button @click="confirm()"
                    class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-emerald-600 py-4 text-sm font-semibold text-white transition active:scale-95 active:bg-emerald-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
                Pakai Foto Ini
            </button>
        </div>

    </div>
</div>

</div>
