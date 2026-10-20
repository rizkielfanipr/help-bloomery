<x-filament-panels::page>
    @php
        $transaction = $this->transaction();
        $attempts = $this->attempts;
        $localRecord = $this->localRecord;
        $canViewPayload = $this->canViewPayload();
        $sectionClass = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6';
        $labelClass = 'text-xs font-semibold text-gray-500 dark:text-gray-400';
        $valueClass = 'mt-0.5 break-words text-sm font-medium text-gray-900 dark:text-white';
        $jakarta = fn ($date) => $date?->copy()->timezone('Asia/Jakarta')->format('d M Y H:i:s').' WIB';
    @endphp

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-4 p-5 sm:p-6 md:flex-row md:items-start md:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                        <x-heroicon-o-arrows-right-left class="h-6 w-6" aria-hidden="true" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">System Monitoring · ESB Transaction</p>
                        <h2 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $transaction->moduleLabel() }} — {{ str($transaction->operation_type)->headline() }}</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ str($transaction->source_action)->headline() }} · {{ $jakarta($transaction->started_at) }} · {{ $transaction->environment }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-col items-start gap-2 md:items-end">
                    <x-helpdesk.monitoring.status-badge :label="$transaction->status->getLabel()" :color="$transaction->status->getColor()" class="text-sm" />
                    <a href="{{ \App\Filament\Helpdesk\Resources\MonitoringTransactions\MonitoringTransactionResource::getUrl('index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"><x-heroicon-o-arrow-left class="h-4 w-4" aria-hidden="true" />Kembali ke Daftar</a>
                </div>
            </div>
            <div @class([
                'flex items-start gap-2 border-t px-5 py-3 text-sm leading-6 sm:px-6',
                'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200' => in_array($transaction->status->value, ['unknown', 'partial', 'failed', 'processing'], true),
                'border-gray-200 bg-gray-50/60 text-gray-600 dark:border-gray-700 dark:bg-gray-800/30 dark:text-gray-300' => ! in_array($transaction->status->value, ['unknown', 'partial', 'failed', 'processing'], true),
            ])>
                <x-heroicon-o-information-circle class="mt-1 h-4 w-4 shrink-0" aria-hidden="true" />
                <span><strong>Keputusan Coba Ulang / Rekonsiliasi:</strong> {{ $this->retryGuidance() }}</span>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
            <div class="space-y-6">
                <section class="{{ $sectionClass }}">
                    <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Identitas</h3>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                        <div><dt class="{{ $labelClass }}">Actor</dt><dd class="{{ $valueClass }}">{{ $transaction->actor?->name ?? 'System' }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Role saat transaksi</dt><dd class="{{ $valueClass }}">{{ $transaction->actor_role_snapshot ?: '-' }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Company Code</dt><dd class="{{ $valueClass }}">{{ $transaction->company_code ?: '-' }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Branch</dt><dd class="{{ $valueClass }}">{{ $transaction->branch?->name ?? '-' }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Referensi Eksternal</dt><dd class="{{ $valueClass }} font-mono">{{ $transaction->external_reference ?: '-' }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Jumlah Attempt</dt><dd class="{{ $valueClass }}">{{ $transaction->attempt_count }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Durasi</dt><dd class="{{ $valueClass }}">{{ $transaction->duration_ms !== null ? number_format($transaction->duration_ms / 1000, 2).' detik' : '-' }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Deploy Commit</dt><dd class="{{ $valueClass }} font-mono">{{ $transaction->deploy_commit ? substr($transaction->deploy_commit, 0, 7) : 'Tidak diketahui' }}</dd></div>
                        <div class="sm:col-span-2 xl:col-span-1 2xl:col-span-2" x-data="{ copied: false }">
                            <dt class="{{ $labelClass }}">Correlation ID</dt>
                            <dd class="mt-0.5 flex items-center gap-2">
                                <code class="min-w-0 break-all text-xs text-gray-700 dark:text-gray-300">{{ $transaction->correlation_id }}</code>
                                <button type="button" class="shrink-0 rounded-md p-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Salin Correlation ID" x-on:click="navigator.clipboard.writeText(@js($transaction->correlation_id)); copied = true; setTimeout(() => copied = false, 1500)">
                                    <x-heroicon-o-clipboard-document class="h-4 w-4" aria-hidden="true" />
                                </button>
                                <span x-show="copied" x-cloak class="text-xs text-emerald-600" role="status">Tersalin</span>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="{{ $sectionClass }}">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Record Domain</h3>
                    @if ($localRecord)
                        @if ($localRecord['url'])
                            <a href="{{ $localRecord['url'] }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400">{{ $localRecord['label'] }}<x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" aria-hidden="true" /></a>
                        @else
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $localRecord['label'] }}</p>
                        @endif
                        <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $localRecord['missing'] ? 'Record domain sudah tidak tersedia.' : 'Tabel domain tetap menjadi sumber kebenaran; monitoring hanya mengamati.' }}</p>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada record domain yang ditautkan.</p>
                    @endif
                </section>

                @if ($transaction->status->value === 'reconciled' || $transaction->safe_error_message)
                    <section class="{{ $sectionClass }}">
                        <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Hasil &amp; Error</h3>
                        <dl class="space-y-3">
                            @if ($transaction->safe_error_message)
                                <div><dt class="{{ $labelClass }}">Pesan aman</dt><dd class="{{ $valueClass }}">{{ $transaction->safe_error_message }}</dd></div>
                                <div><dt class="{{ $labelClass }}">Kode</dt><dd class="{{ $valueClass }} font-mono">{{ $transaction->error_code ?: '-' }}</dd></div>
                            @endif
                            @if ($transaction->reconciliation_outcome)
                                <div><dt class="{{ $labelClass }}">Rekonsiliasi</dt><dd class="{{ $valueClass }}">{{ str($transaction->reconciliation_outcome)->headline() }} oleh {{ $transaction->reconciledBy?->name ?? 'System' }} · {{ $jakarta($transaction->verified_at) }}</dd></div>
                                <div><dt class="{{ $labelClass }}">Catatan</dt><dd class="{{ $valueClass }}">{{ $transaction->reconciliation_note ?: '-' }}</dd></div>
                            @endif
                        </dl>
                    </section>
                @endif
            </div>

            <section class="{{ $sectionClass }}">
                <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Timeline Attempt</h3>
                    @unless ($canViewPayload)
                        <p class="text-xs text-gray-500 dark:text-gray-400">Ringkasan request/response hanya untuk permission payload teknis.</p>
                    @endunless
                </div>

                <ol class="space-y-4">
                    @forelse ($attempts as $attempt)
                        <li class="rounded-xl border border-gray-200 p-4 dark:border-gray-700" wire:key="attempt-{{ $attempt->id }}">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ str($attempt->operation_type)->headline() }} <span class="font-normal text-gray-500">· percobaan ke-{{ $attempt->attempt_count }}</span></p>
                                    <p class="mt-1 break-all font-mono text-xs text-gray-500 dark:text-gray-400">{{ $attempt->method }} {{ $attempt->endpoint_template }}{{ $attempt->company_code ? ' · '.$attempt->company_code : '' }}</p>
                                </div>
                                <x-helpdesk.monitoring.status-badge :label="$attempt->status->getLabel()" :color="$attempt->status->getColor()" />
                            </div>
                            <dl class="mt-3 grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                                <div><dt class="text-gray-500">Mulai</dt><dd class="text-gray-900 dark:text-gray-100">{{ $jakarta($attempt->started_at) }}</dd></div>
                                <div><dt class="text-gray-500">HTTP</dt><dd class="text-gray-900 dark:text-gray-100">{{ $attempt->http_status ?? ($attempt->mutation_sent ? 'Tanpa respons' : 'Tidak terkirim') }}</dd></div>
                                <div><dt class="text-gray-500">Durasi</dt><dd class="text-gray-900 dark:text-gray-100">{{ $attempt->duration_ms !== null ? $attempt->duration_ms.' ms' : '-' }}</dd></div>
                                <div><dt class="text-gray-500">Referensi</dt><dd class="break-all font-mono text-gray-900 dark:text-gray-100">{{ $attempt->external_reference ?: '-' }}</dd></div>
                            </dl>
                            @if ($attempt->safe_error_message)
                                <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs leading-5 text-red-700 dark:bg-red-950/30 dark:text-red-300"><span class="font-semibold">{{ $attempt->error_code }}</span> — {{ $attempt->safe_error_message }}</p>
                            @endif
                            @if ($canViewPayload)
                                @if ($attempt->payload_expired_at)
                                    <p class="mt-3 text-xs text-gray-500">Ringkasan payload sudah dihapus sesuai retensi.</p>
                                @else
                                    <div class="mt-3 grid gap-3 lg:grid-cols-2">
                                        @foreach (['Request (tersanitasi)' => $attempt->request_summary, 'Response (tersanitasi)' => $attempt->response_summary] as $title => $summary)
                                            @php($json = json_encode($summary ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                                            <details class="min-w-0 rounded-lg border border-gray-200 dark:border-gray-700" x-data="{ copied: false }">
                                                <summary class="flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-xs font-semibold text-gray-700 dark:text-gray-200">
                                                    {{ $title }}
                                                    <button type="button" class="rounded-md p-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Salin {{ $title }}" x-on:click.prevent="navigator.clipboard.writeText(@js($json)); copied = true; setTimeout(() => copied = false, 1500)"><x-heroicon-o-clipboard-document class="h-4 w-4" aria-hidden="true" /></button>
                                                </summary>
                                                <pre class="max-h-72 overflow-auto border-t border-gray-200 bg-gray-50 p-3 text-xs leading-5 text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200">{{ $json }}</pre>
                                            </details>
                                        @endforeach
                                    </div>
                                @endif
                            @endif
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700">Belum ada attempt mutation.</li>
                    @endforelse

                    @foreach ($transaction->timeline ?? [] as $step)
                        <li class="flex items-start gap-3 rounded-xl border border-dashed border-gray-200 p-3 text-xs dark:border-gray-700">
                            <x-heroicon-o-magnifying-glass class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" aria-hidden="true" />
                            <span class="text-gray-600 dark:text-gray-300"><span class="font-semibold">{{ str($step['step'] ?? '')->headline() }}</span> (read, bukan mutation): {{ str($step['result'] ?? '')->headline() }} · {{ isset($step['at']) ? \Illuminate\Support\Carbon::parse($step['at'])->timezone('Asia/Jakarta')->format('d M Y H:i:s') : '' }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>
    </div>
</x-filament-panels::page>
