{{--
    Selfie camera partial.
    Parent must have: x-data="selfieCam('propertyName')"
--}}
<div class="px-4 pb-3">
    <div class="overflow-hidden rounded-3xl bg-black shadow-xl">

        {{-- Live preview (square) --}}
        <div class="relative w-full" style="aspect-ratio:1/1;">

            {{-- Video stream --}}
            <video x-ref="video" x-show="hasStream && !hasPhoto"
                   autoplay muted playsinline
                   class="absolute inset-0 h-full w-full object-cover"
                   style="transform:scaleX(-1)"></video>

            {{-- Captured photo --}}
            <img x-show="hasPhoto" :src="photo"
                 class="absolute inset-0 h-full w-full object-cover" alt="">

            {{-- Face guide overlay (live preview only) --}}
            <div x-show="hasStream && !hasPhoto"
                 class="pointer-events-none absolute inset-0 flex items-center justify-center">
                <div class="h-52 w-52 rounded-full border-2 border-dashed border-white/60"
                     style="box-shadow:0 0 0 9999px rgba(0,0,0,.35)"></div>
            </div>

            {{-- Loading spinner --}}
            <div x-show="!hasStream && !hasPhoto && !camError"
                 class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-gray-900 text-white">
                <svg class="h-8 w-8 animate-spin text-white/60" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span class="text-sm text-white/60">Membuka kamera...</span>
            </div>

            {{-- Error state --}}
            <div x-show="camError"
                 class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-gray-900 px-6 text-center text-white">
                <svg class="h-10 w-10 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M12 18.75H4.5a2.25 2.25 0 0 1-2.25-2.25V9m12.841 9.091L16.5 19.5m-1.409-1.409c.407-.407.659-.97.659-1.591v-9a2.25 2.25 0 0 0-2.25-2.25h-9c-.621 0-1.184.252-1.591.659m12.182 12.182L2.909 5.909M1.5 4.5l1.409 1.409"/>
                </svg>
                <p class="text-sm text-white/80" x-text="camError"></p>
                <button @click="startCamera()"
                        class="mt-1 rounded-full bg-white/20 px-5 py-2 text-sm font-semibold backdrop-blur-sm transition hover:bg-white/30">
                    Coba Lagi
                </button>
            </div>

            {{-- Photo ready badge --}}
            <div x-show="hasPhoto && !uploading"
                 class="absolute left-3 top-3 flex items-center gap-1.5 rounded-full bg-green-500 px-3 py-1 text-xs font-semibold text-white shadow">
                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/>
                </svg>
                Foto siap
            </div>

            {{-- Uploading badge --}}
            <div x-show="uploading"
                 class="absolute left-3 top-3 flex items-center gap-1.5 rounded-full bg-amber-500 px-3 py-1 text-xs font-semibold text-white shadow">
                <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Mengunggah...
            </div>

            {{-- Shutter button (live only) --}}
            <div x-show="hasStream && !hasPhoto"
                 class="absolute bottom-5 left-0 right-0 flex justify-center">
                <button @click="shoot()"
                        class="flex h-16 w-16 items-center justify-center rounded-full bg-white shadow-xl transition active:scale-90">
                    <div class="h-11 w-11 rounded-full bg-white ring-4 ring-gray-300"></div>
                </button>
            </div>
        </div>

        {{-- Canvas (hidden, used for capture) --}}
        <canvas x-ref="canvas" class="hidden"></canvas>

        {{-- Retake button (after capture) --}}
        <div x-show="hasPhoto" class="flex items-center justify-center bg-black py-3">
            <button @click="retake()"
                    class="flex items-center gap-1.5 rounded-full bg-white/10 px-5 py-2 text-sm font-semibold text-white transition hover:bg-white/20 active:scale-95">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                </svg>
                Ambil Ulang
            </button>
        </div>

    </div>

    {{-- Hint below camera --}}
    <p x-show="hasStream && !hasPhoto" class="mt-2 text-center text-xs text-gray-400">
        Posisikan wajah dalam lingkaran, lalu tekan tombol putih.
    </p>
</div>
