<x-filament-panels::page>

<div
    class="space-y-5"
    x-data="{
        charts: {},
        branchTable: [],
        categoryDetailMap: {},
        selectedCat: '',
        selectedSubCat: '',
        rawData: null,

        get catOptions() { return Object.keys(this.categoryDetailMap).sort(); },
        get subCatOptions() {
            if (!this.selectedCat || !this.categoryDetailMap[this.selectedCat]) return [];
            return Object.keys(this.categoryDetailMap[this.selectedCat]).sort();
        },

        onCatChange()    { this.selectedSubCat = ''; this.updateFilteredMenus(); },
        onSubCatChange() { this.updateFilteredMenus(); },

        // ── Helpers ───────────────────────────────────────────────────────
        pct(v, t)       { return t > 0 ? (v / t * 100).toFixed(1) + '%' : '0%'; },
        dlBtn(wb, name) { XLSX.writeFile(wb, name); },
        sheet(headers, rows) { return XLSX.utils.aoa_to_sheet([headers, ...rows]); },

        // ── Export All ────────────────────────────────────────────────────
        exportAllXlsx() {
            const d = this.rawData;
            if (!d) return;
            const wb  = XLSX.utils.book_new();
            const pct = this.pct.bind(this);
            const dateFrom = d.kpi ? (this.rawData?.dateFrom ?? '') : '';
            const period   = (d.kpi && d.dateFrom && d.dateTo) ? `_${d.dateFrom}_sd_${d.dateTo}` : '';

            // KPI
            const k = d.kpi ?? {};
            XLSX.utils.book_append_sheet(wb, this.sheet(
                ['Metrik', 'Nilai'],
                [
                    ['Total Pendapatan (Rp)', k.totalRevenue ?? 0],
                    ['Total Transaksi', k.totalTransactions ?? 0],
                    ['Rata-rata / Transaksi (Rp)', k.avgTransaction ?? 0],
                    ['Total Pax', k.totalPax ?? 0],
                    ['Rata-rata / Pax (Rp)', k.avgPerPax ?? 0],
                    ['Total Item Terjual', k.totalItems ?? 0],
                    ['Total Diskon (Rp)', k.totalDiscount ?? 0],
                    ['Diskon Menu (Rp)', k.discountMenuTotal ?? 0],
                    ['Diskon Promosi (Rp)', k.discountPromoTotal ?? 0],
                    ['Diskon Voucher (Rp)', k.discountVoucherTotal ?? 0],
                ]
            ), 'KPI');

            // Tren Pendapatan
            if (d.revenueTrend?.labels?.length) {
                const tot = d.revenueTrend.data.reduce((a, b) => a + b, 0);
                XLSX.utils.book_append_sheet(wb, this.sheet(
                    ['Tanggal', 'Pendapatan (Rp)', '% dari Total'],
                    d.revenueTrend.labels.map((l, i) => [l, d.revenueTrend.data[i], pct(d.revenueTrend.data[i], tot)])
                ), 'Tren Pendapatan');
            }

            // Per Cabang
            if (d.branchTable?.length) {
                XLSX.utils.book_append_sheet(wb, this.sheet(
                    ['Cabang', 'Pendapatan (Rp)', 'Transaksi', 'Pax', 'Rata-rata/Transaksi (Rp)', 'Rata-rata/Pax (Rp)', 'Total Diskon (Rp)'],
                    d.branchTable.map(b => [b.name, b.revenue, b.transactions, b.pax, Math.round(b.avgPerTransaction), Math.round(b.avgPerPax), b.discountTotal])
                ), 'Per Cabang');
            }

            // Top Menu
            if (d.topMenus?.labels?.length) {
                const tot = d.topMenus.data.reduce((a, b) => a + b, 0);
                XLSX.utils.book_append_sheet(wb, this.sheet(
                    ['Menu', 'Qty Terjual', '% dari Total'],
                    d.topMenus.labels.map((l, i) => [l, d.topMenus.data[i], pct(d.topMenus.data[i], tot)])
                ), 'Top Menu');
            }

            // Metode Pembayaran (chart)
            if (d.paymentMix?.labels?.length) {
                const tot = d.paymentMix.data.reduce((a, b) => a + b, 0);
                XLSX.utils.book_append_sheet(wb, this.sheet(
                    ['Metode Pembayaran', 'Total (Rp)', '% dari Total'],
                    d.paymentMix.labels.map((l, i) => [l, d.paymentMix.data[i], pct(d.paymentMix.data[i], tot)])
                ), 'Metode Pembayaran');
            }

            // Rekapitulasi Pembayaran (tabel)
            if (d.paymentTable?.length) {
                XLSX.utils.book_append_sheet(wb, this.sheet(
                    ['Metode Pembayaran', 'Tipe', 'Total (Rp)'],
                    d.paymentTable.map(p => [p.name, p.type ?? '', p.total])
                ), 'Rekapitulasi Pembayaran');
            }

            // Distribusi per Jam
            if (d.peakHours?.labels?.length) {
                const tot = d.peakHours.data.reduce((a, b) => a + b, 0);
                XLSX.utils.book_append_sheet(wb, this.sheet(
                    ['Jam', 'Jumlah Transaksi', '% dari Total'],
                    d.peakHours.labels.map((l, i) => [l, d.peakHours.data[i], pct(d.peakHours.data[i], tot)])
                ), 'Distribusi Jam');
            }

            // Tujuan Kunjungan
            if (d.visitPurpose?.labels?.length) {
                const tot = d.visitPurpose.data.reduce((a, b) => a + b, 0);
                XLSX.utils.book_append_sheet(wb, this.sheet(
                    ['Tujuan Kunjungan', 'Jumlah Transaksi', '% dari Total'],
                    d.visitPurpose.labels.map((l, i) => [l, d.visitPurpose.data[i], pct(d.visitPurpose.data[i], tot)])
                ), 'Tujuan Kunjungan');
            }

            // Per Kategori
            if (d.categories?.labels?.length) {
                const tot = d.categories.data.reduce((a, b) => a + b, 0);
                XLSX.utils.book_append_sheet(wb, this.sheet(
                    ['Kategori', 'Pendapatan (Rp)', '% dari Total'],
                    d.categories.labels.map((l, i) => [l, d.categories.data[i], pct(d.categories.data[i], tot)])
                ), 'Per Kategori');
            }

            // Detail Menu
            if (d.categoryDetailMap) {
                const rows = [];
                for (const [cat, sc] of Object.entries(d.categoryDetailMap)) {
                    for (const [sub, menus] of Object.entries(sc)) {
                        for (const [menu, v] of Object.entries(menus)) {
                            rows.push([cat, sub, menu, v.qty, v.revenue]);
                        }
                    }
                }
                rows.sort((a, b) => b[3] - a[3]);
                XLSX.utils.book_append_sheet(wb, this.sheet(
                    ['Kategori', 'Sub-Kategori', 'Menu', 'Qty Terjual', 'Pendapatan (Rp)'],
                    rows
                ), 'Detail Menu');
            }

            // Promosi
            if (d.promoTable?.length) {
                XLSX.utils.book_append_sheet(wb, this.sheet(
                    ['Nama Promosi', 'Volume Transaksi', 'Total Diskon (Rp)', 'Total Pendapatan (Rp)'],
                    d.promoTable.map(p => [p.name, p.count, p.discountTotal, p.revenue])
                ), 'Promosi');
            }

            XLSX.writeFile(wb, 'laporan-penjualan-lengkap.xlsx');
        },

        // ── Per-section exports ───────────────────────────────────────────
        exportRevenueTrendXlsx() {
            const d = this.rawData;
            if (!d?.revenueTrend?.labels?.length) return;
            const tot = d.revenueTrend.data.reduce((a, b) => a + b, 0);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, this.sheet(
                ['Tanggal', 'Pendapatan (Rp)', '% dari Total'],
                d.revenueTrend.labels.map((l, i) => [l, d.revenueTrend.data[i], this.pct(d.revenueTrend.data[i], tot)])
            ), 'Tren Pendapatan');
            XLSX.writeFile(wb, 'tren-pendapatan.xlsx');
        },

        exportTopMenusXlsx() {
            const d = this.rawData;
            if (!d?.topMenus?.labels?.length) return;
            const tot = d.topMenus.data.reduce((a, b) => a + b, 0);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, this.sheet(
                ['Menu', 'Qty Terjual', '% dari Total'],
                d.topMenus.labels.map((l, i) => [l, d.topMenus.data[i], this.pct(d.topMenus.data[i], tot)])
            ), 'Top Menu');
            XLSX.writeFile(wb, 'top-menu.xlsx');
        },

        exportPaymentMixXlsx() {
            const d = this.rawData;
            if (!d?.paymentMix?.labels?.length) return;
            const tot = d.paymentMix.data.reduce((a, b) => a + b, 0);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, this.sheet(
                ['Metode Pembayaran', 'Total (Rp)', '% dari Total'],
                d.paymentMix.labels.map((l, i) => [l, d.paymentMix.data[i], this.pct(d.paymentMix.data[i], tot)])
            ), 'Metode Pembayaran');
            XLSX.writeFile(wb, 'komposisi-pembayaran.xlsx');
        },

        exportPeakHoursXlsx() {
            const d = this.rawData;
            if (!d?.peakHours?.labels?.length) return;
            const tot = d.peakHours.data.reduce((a, b) => a + b, 0);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, this.sheet(
                ['Jam', 'Jumlah Transaksi', '% dari Total'],
                d.peakHours.labels.map((l, i) => [l, d.peakHours.data[i], this.pct(d.peakHours.data[i], tot)])
            ), 'Distribusi Jam');
            XLSX.writeFile(wb, 'distribusi-per-jam.xlsx');
        },

        exportVisitPurposeXlsx() {
            const d = this.rawData;
            if (!d?.visitPurpose?.labels?.length) return;
            const tot = d.visitPurpose.data.reduce((a, b) => a + b, 0);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, this.sheet(
                ['Tujuan Kunjungan', 'Jumlah Transaksi', '% dari Total'],
                d.visitPurpose.labels.map((l, i) => [l, d.visitPurpose.data[i], this.pct(d.visitPurpose.data[i], tot)])
            ), 'Tujuan Kunjungan');
            XLSX.writeFile(wb, 'tujuan-kunjungan.xlsx');
        },

        exportCategoriesXlsx() {
            const d = this.rawData;
            if (!d) return;
            const wb = XLSX.utils.book_new();
            if (d.categories?.labels?.length) {
                const tot = d.categories.data.reduce((a, b) => a + b, 0);
                XLSX.utils.book_append_sheet(wb, this.sheet(
                    ['Kategori', 'Pendapatan (Rp)', '% dari Total'],
                    d.categories.labels.map((l, i) => [l, d.categories.data[i], this.pct(d.categories.data[i], tot)])
                ), 'Per Kategori');
            }
            if (d.categoryDetailMap) {
                const rows = [];
                for (const [cat, sc] of Object.entries(d.categoryDetailMap)) {
                    for (const [sub, menus] of Object.entries(sc)) {
                        for (const [menu, v] of Object.entries(menus)) {
                            rows.push([cat, sub, menu, v.qty, v.revenue]);
                        }
                    }
                }
                rows.sort((a, b) => b[3] - a[3]);
                XLSX.utils.book_append_sheet(wb, this.sheet(
                    ['Kategori', 'Sub-Kategori', 'Menu', 'Qty Terjual', 'Pendapatan (Rp)'],
                    rows
                ), 'Detail Menu');
            }
            XLSX.writeFile(wb, 'analisis-kinerja-produk.xlsx');
        },

        exportPromoXlsx() {
            const d = this.rawData;
            if (!d?.promoTable?.length) return;
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, this.sheet(
                ['Nama Promosi', 'Volume Transaksi', 'Total Diskon (Rp)', 'Total Pendapatan (Rp)'],
                d.promoTable.map(p => [p.name, p.count, p.discountTotal, p.revenue])
            ), 'Promosi');
            XLSX.writeFile(wb, 'kinerja-promosi.xlsx');
        },

        exportPaymentTableXlsx() {
            const d = this.rawData;
            if (!d?.paymentTable?.length) return;
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, this.sheet(
                ['Metode Pembayaran', 'Tipe', 'Total (Rp)'],
                d.paymentTable.map(p => [p.name, p.type ?? '', p.total])
            ), 'Rekapitulasi');
            XLSX.writeFile(wb, 'rekapitulasi-pembayaran.xlsx');
        },

        // ── Category drill-down ───────────────────────────────────────────
        updateFilteredMenus() {
            if (this.charts.filteredMenus) { this.charts.filteredMenus.destroy(); delete this.charts.filteredMenus; }
            if (!this.selectedCat || !this.categoryDetailMap[this.selectedCat]) return;
            const cat      = this.categoryDetailMap[this.selectedCat];
            const scKeys   = this.selectedSubCat ? [this.selectedSubCat] : Object.keys(cat);
            const agg      = {};
            for (const sc of scKeys) {
                for (const [name, v] of Object.entries(cat[sc] ?? {})) {
                    if (!agg[name]) agg[name] = { qty: 0, revenue: 0 };
                    agg[name].qty     += v.qty;
                    agg[name].revenue += v.revenue;
                }
            }
            const sorted    = Object.entries(agg).sort((a, b) => b[1].qty - a[1].qty).slice(0, 15);
            const totalQty  = sorted.reduce((s, [, v]) => s + v.qty, 0);
            const el = document.getElementById('chartFilteredMenus');
            if (!el || !sorted.length) return;
            const pal   = ['#3b82f6','#6366f1','#ec4899','#f59e0b','#14b8a6','#10b981','#0ea5e9','#8b5cf6','#f97316','#06b6d4'];
            const dark  = () => document.documentElement.classList.contains('dark');
            const tick  = () => dark() ? '#475569' : '#94a3b8';
            const grid  = () => dark() ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
            const tBg   = () => dark() ? '#1e293b' : '#fff';
            const tT    = () => dark() ? '#e2e8f0' : '#0f172a';
            const tM    = () => dark() ? '#94a3b8' : '#64748b';
            const tBrd  = () => dark() ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
            const ax    = { grid: { color: grid(), drawBorder: false }, ticks: { color: tick(), font: { size: 11 } }, border: { display: false } };
            const tip   = { backgroundColor: tBg(), titleColor: tT(), bodyColor: tM(), borderColor: tBrd(), borderWidth: 1, cornerRadius: 10, padding: 12 };
            const pct   = (val, total) => total > 0 ? (val / total * 100).toFixed(1) + '%' : '0%';
            this.charts.filteredMenus = new Chart(el.getContext('2d'), {
                type: 'bar',
                data: { labels: sorted.map(([n]) => n), datasets: [{ label: 'Kuantitas', data: sorted.map(([, v]) => v.qty), backgroundColor: sorted.map((_, i) => pal[i % pal.length]), borderRadius: 6, borderSkipped: false }] },
                options: {
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                    layout: { padding: { right: 80 } },
                    plugins: {
                        legend: { display: false },
                        tooltip: { ...tip, callbacks: { label: ctx => ` ${ctx.parsed.x} Unit (${pct(ctx.parsed.x, totalQty)})` } },
                        datalabels: {
                            anchor: 'end', align: 'right',
                            color: tick,
                            font: { size: 11, weight: '600' },
                            formatter: (val) => `${val} (${pct(val, totalQty)})`,
                        },
                    },
                    scales: { x: { ...ax, beginAtZero: true, ticks: { ...ax.ticks, precision: 0 } }, y: ax },
                },
            });
        },

        exportBranchXlsx() {
            const bt = this.branchTable;
            if (!bt.length) return;
            const totalRev = bt.reduce((s, b) => s + b.revenue, 0);
            const totalTrx = bt.reduce((s, b) => s + b.transactions, 0);
            const totalPax = bt.reduce((s, b) => s + b.pax, 0);
            const totalDis = bt.reduce((s, b) => s + b.discountTotal, 0);
            const headers  = ['Metrik', ...bt.map(b => b.name), 'Total'];
            const rows     = [
                ['Pendapatan (Rp)',          ...bt.map(b => b.revenue),                                                    totalRev],
                ['Jumlah Transaksi',         ...bt.map(b => b.transactions),                                               totalTrx],
                ['Pax Visitor',              ...bt.map(b => b.pax),                                                        totalPax],
                ['Rata-rata/Transaksi (Rp)', ...bt.map(b => Math.round(b.avgPerTransaction)), totalTrx > 0 ? Math.round(totalRev / totalTrx) : 0],
                ['Rata-rata/Pax (Rp)',       ...bt.map(b => Math.round(b.avgPerPax)),         totalPax > 0 ? Math.round(totalRev / totalPax) : 0],
                ['Total Diskon (Rp)',         ...bt.map(b => b.discountTotal),                                               totalDis],
            ];
            const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Perbandingan Cabang');
            XLSX.writeFile(wb, 'laporan-perbandingan-cabang.xlsx');
        },

        init() {
            $wire.on('sales-loaded', (data) => { this.buildCharts(data); });
            $wire.on('fetch-next-page', () => { $wire.call('fetchNextPage'); });
        },
        destroyAll() {
            Object.values(this.charts).forEach(c => { if (c) c.destroy(); });
            this.charts = {};
        },
        buildCharts(d) {
            this.rawData           = d;
            this.destroyAll();
            this.branchTable       = d.branchTable ?? [];
            this.categoryDetailMap = d.categoryDetailMap ?? {};
            this.selectedSubCat    = '';
            const cats             = Object.keys(this.categoryDetailMap).sort();
            this.selectedCat       = cats.length ? cats[0] : '';

            const dark        = () => document.documentElement.classList.contains('dark');
            const grid        = () => dark() ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
            const tick        = () => dark() ? '#475569' : '#94a3b8';
            const tBg         = () => dark() ? '#1e293b' : '#fff';
            const tText       = () => dark() ? '#e2e8f0' : '#0f172a';
            const tMuted      = () => dark() ? '#94a3b8' : '#64748b';
            const tBorder     = () => dark() ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
            const palette     = ['#3b82f6','#6366f1','#ec4899','#f59e0b','#14b8a6','#10b981','#0ea5e9','#8b5cf6','#f97316','#06b6d4'];
            const tooltipBase = { backgroundColor: tBg(), titleColor: tText(), bodyColor: tMuted(), borderColor: tBorder(), borderWidth: 1, cornerRadius: 10, padding: 12 };
            const axisBase    = { grid: { color: grid(), drawBorder: false }, ticks: { color: tick(), font: { size: 11 } }, border: { display: false } };
            const rupiah      = v => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(v));
            const pct         = (val, total) => total > 0 ? (val / total * 100).toFixed(1) + '%' : '0%';

            // ── Revenue Trend ──────────────────────────────────────────────
            const trendEl = document.getElementById('chartRevenueTrend');
            if (trendEl && d.revenueTrend) {
                const totalRevenue = d.revenueTrend.data.reduce((a, b) => a + b, 0);
                const ctx = trendEl.getContext('2d');
                const grad = ctx.createLinearGradient(0, 0, 0, 280);
                grad.addColorStop(0, '#3b82f620');
                grad.addColorStop(1, '#3b82f600');
                this.charts.revenueTrend = new Chart(ctx, {
                    type: 'line',
                    data: { labels: d.revenueTrend.labels, datasets: [{ label: 'Pendapatan', data: d.revenueTrend.data, borderColor: '#3b82f6', backgroundColor: grad, fill: true, tension: 0.4, borderWidth: 2, pointRadius: 3, pointBackgroundColor: '#3b82f6', pointHoverRadius: 5 }] },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { ...tooltipBase, callbacks: { label: ctx => ` ${rupiah(ctx.parsed.y)} (${pct(ctx.parsed.y, totalRevenue)} dari total periode berjalan)` } },
                            datalabels: { display: false },
                        },
                        scales: { x: axisBase, y: { ...axisBase, beginAtZero: true, ticks: { ...axisBase.ticks, callback: v => rupiah(v) } } },
                    },
                });
            }

            // ── Top 10 Menus ───────────────────────────────────────────────
            const menuEl = document.getElementById('chartTopMenus');
            if (menuEl && d.topMenus) {
                const totalQty = d.topMenus.data.reduce((a, b) => a + b, 0);
                this.charts.topMenus = new Chart(menuEl.getContext('2d'), {
                    type: 'bar',
                    data: { labels: d.topMenus.labels, datasets: [{ label: 'Kuantitas Terjual', data: d.topMenus.data, backgroundColor: palette.slice(0, d.topMenus.labels.length), borderRadius: 6, borderSkipped: false }] },
                    options: {
                        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                        layout: { padding: { right: 80 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: { ...tooltipBase, callbacks: { label: ctx => ` ${ctx.parsed.x} Unit (${pct(ctx.parsed.x, totalQty)})` } },
                            datalabels: {
                                anchor: 'end', align: 'right',
                                color: tick,
                                font: { size: 11, weight: '600' },
                                formatter: (val) => `${val} (${pct(val, totalQty)})`,
                            },
                        },
                        scales: { x: { ...axisBase, beginAtZero: true, ticks: { ...axisBase.ticks, precision: 0 } }, y: axisBase },
                    },
                });
            }

            // ── Payment Mix ────────────────────────────────────────────────
            const payEl = document.getElementById('chartPaymentMix');
            if (payEl && d.paymentMix) {
                const totalPay = d.paymentMix.data.reduce((a, b) => a + b, 0);
                this.charts.paymentMix = new Chart(payEl.getContext('2d'), {
                    type: 'doughnut',
                    data: { labels: d.paymentMix.labels, datasets: [{ data: d.paymentMix.data, backgroundColor: palette, borderWidth: 0, hoverOffset: 6 }] },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '65%',
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    color: tick(), font: { size: 11 }, boxWidth: 12, padding: 10,
                                    generateLabels: (chart) => {
                                        const ds = chart.data.datasets[0];
                                        return chart.data.labels.map((label, i) => ({
                                            text: `${label}  ${pct(ds.data[i], totalPay)}`,
                                            fillStyle: ds.backgroundColor[i],
                                            hidden: false, index: i,
                                        }));
                                    },
                                },
                            },
                            tooltip: { ...tooltipBase, callbacks: { label: ctx => ` ${ctx.label}: ${rupiah(ctx.parsed)} (${pct(ctx.parsed, totalPay)})` } },
                            datalabels: {
                                display: (ctx) => ctx.dataset.data[ctx.dataIndex] / totalPay > 0.04,
                                color: '#fff',
                                font: { size: 11, weight: '700' },
                                formatter: (val) => pct(val, totalPay),
                            },
                        },
                    },
                });
            }

            // ── Peak Hours ─────────────────────────────────────────────────
            const peakEl = document.getElementById('chartPeakHours');
            if (peakEl && d.peakHours) {
                const maxVal   = Math.max(...d.peakHours.data);
                const totalPeak = d.peakHours.data.reduce((a, b) => a + b, 0);
                this.charts.peakHours = new Chart(peakEl.getContext('2d'), {
                    type: 'bar',
                    data: { labels: d.peakHours.labels, datasets: [{ label: 'Transaksi', data: d.peakHours.data, backgroundColor: d.peakHours.data.map(v => v === maxVal ? '#f59e0b' : '#3b82f640'), borderColor: d.peakHours.data.map(v => v === maxVal ? '#f59e0b' : '#3b82f6'), borderWidth: 1, borderRadius: 4 }] },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { ...tooltipBase, callbacks: { label: ctx => ` ${ctx.parsed.y} Transaksi (${pct(ctx.parsed.y, totalPeak)} dari total)` } },
                            datalabels: { display: false },
                        },
                        scales: { x: { ...axisBase, ticks: { ...axisBase.ticks, maxTicksLimit: 12 } }, y: { ...axisBase, beginAtZero: true, ticks: { ...axisBase.ticks, precision: 0 } } },
                    },
                });
            }

            // ── Category Revenue ───────────────────────────────────────────
            const catEl = document.getElementById('chartCategories');
            if (catEl && d.categories) {
                const totalCat = d.categories.data.reduce((a, b) => a + b, 0);
                this.charts.categories = new Chart(catEl.getContext('2d'), {
                    type: 'bar',
                    data: { labels: d.categories.labels, datasets: [{ label: 'Pendapatan', data: d.categories.data, backgroundColor: palette.slice(0, d.categories.labels.length), borderRadius: 6, borderSkipped: false }] },
                    options: {
                        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                        layout: { padding: { right: 120 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: { ...tooltipBase, callbacks: { label: ctx => ` ${rupiah(ctx.parsed.x)} (${pct(ctx.parsed.x, totalCat)})` } },
                            datalabels: {
                                anchor: 'end', align: 'right',
                                color: tick,
                                font: { size: 11, weight: '600' },
                                formatter: (val) => `${pct(val, totalCat)}`,
                            },
                        },
                        scales: { x: { ...axisBase, beginAtZero: true, ticks: { ...axisBase.ticks, callback: v => rupiah(v) } }, y: axisBase },
                    },
                });
            }

            // ── Visit Purpose ──────────────────────────────────────────────
            const purposeEl = document.getElementById('chartVisitPurpose');
            if (purposeEl && d.visitPurpose) {
                const totalPurpose = d.visitPurpose.data.reduce((a, b) => a + b, 0);
                this.charts.visitPurpose = new Chart(purposeEl.getContext('2d'), {
                    type: 'doughnut',
                    data: { labels: d.visitPurpose.labels, datasets: [{ data: d.visitPurpose.data, backgroundColor: palette, borderWidth: 0, hoverOffset: 6 }] },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '65%',
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    color: tick(), font: { size: 11 }, boxWidth: 12, padding: 10,
                                    generateLabels: (chart) => {
                                        const ds = chart.data.datasets[0];
                                        return chart.data.labels.map((label, i) => ({
                                            text: `${label}  ${pct(ds.data[i], totalPurpose)}`,
                                            fillStyle: ds.backgroundColor[i],
                                            hidden: false, index: i,
                                        }));
                                    },
                                },
                            },
                            tooltip: { ...tooltipBase, callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} Transaksi (${pct(ctx.parsed, totalPurpose)})` } },
                            datalabels: {
                                display: (ctx) => ctx.dataset.data[ctx.dataIndex] / totalPurpose > 0.04,
                                color: '#fff',
                                font: { size: 11, weight: '700' },
                                formatter: (val) => pct(val, totalPurpose),
                            },
                        },
                    },
                });
            }

            // Auto-render first category chart after DOM updates
            if (this.selectedCat) {
                this.$nextTick(() => { this.updateFilteredMenus(); });
            }
        }
    }"
>

    {{-- ── Filter Card ─────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-end gap-4">

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Periode Awal</label>
                <input type="date" wire:model="dateFrom"
                       class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Periode Akhir <span class="text-red-500">*</span></label>
                <input type="date" wire:model="dateTo" required
                       class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 @error('dateTo') border-red-500 @enderror">
                @error('dateTo') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Brand</label>
                <select wire:model.live="selectedBrandId"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    <option value="">— Semua Brand —</option>
                    @foreach($this->getBrands() as $brand)
                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-[200px] flex-1">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Cabang</label>
                <select wire:model="selectedBranchId"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    <option value="">— Semua Cabang —</option>
                    @foreach($this->getBranches() as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->esb_branch_code }})</option>
                    @endforeach
                </select>
                @error('selectedBranchId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <button wire:click="fetch" @disabled($isFetching)
                    class="flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700 active:bg-primary-800 disabled:cursor-not-allowed disabled:opacity-60">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                </svg>
                Tarik Data
            </button>
        </div>
    </div>

    {{-- Progress bar --}}
    @if($isFetching)
    @php
        $totalBranches = count($fetchBranchIds);
        $branchDone    = $totalBranches > 0 ? $fetchBranchIndex / $totalBranches : 0;
        $pageSlice     = ($totalBranches > 0 && $fetchTotalPages > 0)
            ? ($fetchCurrentPage / $fetchTotalPages) / $totalBranches : 0;
        $progressWidth = max(3, (int) round(($branchDone + $pageSlice) * 100));
    @endphp
    <div class="rounded-xl border border-blue-100 bg-white p-4 dark:border-blue-900/40 dark:bg-gray-900">
        <div class="mb-2 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Mengambil data dari sistem ESB...</span>
            </div>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                @if($totalBranches > 1)
                    Cabang {{ $fetchBranchIndex + 1 }}/{{ $totalBranches }}
                    @if($fetchTotalPages > 0) &mdash; Halaman {{ $fetchCurrentPage }}/{{ $fetchTotalPages }} @endif
                @elseif($fetchTotalPages > 0)
                    Halaman {{ $fetchCurrentPage }}/{{ $fetchTotalPages }}
                @endif
            </span>
        </div>
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
            <div class="h-full rounded-full transition-all duration-300"
                 style="width: {{ $progressWidth }}%; background-color: #3b82f6;"></div>
        </div>
    </div>
    @endif

    @if($fetched)

    {{-- ── Export All ──────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between rounded-xl border border-emerald-100 bg-emerald-50 px-5 py-3.5 dark:border-emerald-900/30 dark:bg-emerald-500/5">
        <div>
            <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">Ekspor Laporan Lengkap</p>
            <p class="mt-0.5 text-xs text-emerald-600 dark:text-emerald-500">Seluruh data (KPI, tren, cabang, menu, pembayaran, promosi) dalam satu file XLSX multi-sheet</p>
        </div>
        <button @click="exportAllXlsx()"
                class="flex shrink-0 items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 active:bg-emerald-800">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
            </svg>
            Export Semua Data
        </button>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        @php
            $kpis = [
                ['label' => 'Total Pendapatan',         'value' => 'Rp '.number_format($totalRevenue, 0, ',', '.'),   'icon_color' => 'text-blue-600',   'icon_bg' => 'bg-blue-50 dark:bg-blue-500/10',    'path' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z'],
                ['label' => 'Total Transaksi',          'value' => number_format($totalTransactions),                  'icon_color' => 'text-indigo-600', 'icon_bg' => 'bg-indigo-50 dark:bg-indigo-500/10', 'path' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z'],
                ['label' => 'Rata-rata / Transaksi',   'value' => 'Rp '.number_format($avgTransaction, 0, ',', '.'), 'icon_color' => 'text-emerald-600','icon_bg' => 'bg-emerald-50 dark:bg-emerald-500/10', 'path' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z'],
                ['label' => 'Total Pax',               'value' => number_format($totalPax),                          'icon_color' => 'text-cyan-600',   'icon_bg' => 'bg-cyan-50 dark:bg-cyan-500/10',    'path' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z'],
                ['label' => 'Rata-rata / Pax',         'value' => 'Rp '.number_format($avgPerPax, 0, ',', '.'),     'icon_color' => 'text-violet-600', 'icon_bg' => 'bg-violet-50 dark:bg-violet-500/10', 'path' => 'M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941'],
                ['label' => 'Total Item Terjual',      'value' => number_format($totalItems),                        'icon_color' => 'text-amber-600',  'icon_bg' => 'bg-amber-50 dark:bg-amber-500/10',  'path' => 'M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z'],
            ];
        @endphp
        @foreach($kpis as $kpi)
        <div class="rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700/50 dark:bg-gray-900">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</p>
                    <p class="mt-1.5 truncate text-xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $kpi['value'] }}</p>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $kpi['icon_bg'] }}">
                    <svg class="h-5 w-5 {{ $kpi['icon_color'] }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $kpi['path'] }}"/>
                    </svg>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Discount Breakdown --}}
    @if($totalDiscount > 0)
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-red-100 bg-red-50 p-4 dark:border-red-900/30 dark:bg-red-500/5">
            <p class="text-xs font-medium text-red-500 dark:text-red-400">Total Diskon</p>
            <p class="mt-1 text-lg font-bold text-red-700 dark:text-red-300">Rp {{ number_format($totalDiscount, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 dark:border-gray-700/50 dark:bg-gray-900">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Diskon Menu</p>
            <p class="mt-1 text-lg font-bold text-gray-800 dark:text-white">Rp {{ number_format($discountMenuTotal, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 dark:border-gray-700/50 dark:bg-gray-900">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Diskon Promosi</p>
            <p class="mt-1 text-lg font-bold text-gray-800 dark:text-white">Rp {{ number_format($discountPromoTotal, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 dark:border-gray-700/50 dark:bg-gray-900">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Diskon Voucher</p>
            <p class="mt-1 text-lg font-bold text-gray-800 dark:text-white">Rp {{ number_format($discountVoucherTotal, 0, ',', '.') }}</p>
        </div>
    </div>
    @endif

    {{-- Cross-Branch Comparison Table --}}
    @if(count($branchTable) > 0)
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white dark:border-gray-700/50 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
            <div>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Perbandingan Antar Cabang</p>
                <p class="mt-0.5 text-xs text-gray-400">Perbandingan metrik utama antar cabang dalam periode berjalan</p>
            </div>
            @if(count($branchTable) > 1)
            <button @click="exportBranchXlsx()"
                    class="flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                </svg>
                XLSX
            </button>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                        <th class="sticky left-0 z-10 min-w-[150px] bg-gray-50 px-4 py-3 text-left dark:bg-gray-800/50">Metrik</th>
                        @foreach($branchTable as $b)
                            <th class="min-w-[140px] px-4 py-3 text-right">{{ $b['name'] }}</th>
                        @endforeach
                        @if(count($branchTable) > 1)
                        <th class="min-w-[140px] px-4 py-3 text-right text-primary-600 dark:text-primary-400">Total</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @php
                        $crossRows = [
                            ['label' => 'Pendapatan',            'values' => array_column($branchTable, 'revenue'),          'total' => $totalRevenue,     'format' => 'rupiah'],
                            ['label' => 'Jumlah Transaksi',      'values' => array_column($branchTable, 'transactions'),     'total' => $totalTransactions,'format' => 'number'],
                            ['label' => 'Pax Visitor',           'values' => array_column($branchTable, 'pax'),              'total' => $totalPax,         'format' => 'number'],
                            ['label' => 'Rata-rata / Transaksi', 'values' => array_column($branchTable, 'avgPerTransaction'),'total' => $avgTransaction,   'format' => 'rupiah'],
                            ['label' => 'Rata-rata / Pax',       'values' => array_column($branchTable, 'avgPerPax'),        'total' => $avgPerPax,        'format' => 'rupiah'],
                            ['label' => 'Total Diskon',          'values' => array_column($branchTable, 'discountTotal'),    'total' => $totalDiscount,    'format' => 'rupiah_red'],
                        ];
                    @endphp
                    @foreach($crossRows as $row)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                        <td class="sticky left-0 z-10 bg-white px-4 py-3 font-semibold text-gray-600 dark:bg-gray-900 dark:text-gray-400">{{ $row['label'] }}</td>
                        @foreach($row['values'] as $val)
                            @if($row['format'] === 'rupiah')
                            <td class="px-4 py-3 text-right font-mono text-gray-800 dark:text-gray-100">Rp {{ number_format($val, 0, ',', '.') }}</td>
                            @elseif($row['format'] === 'rupiah_red')
                            <td class="px-4 py-3 text-right font-mono text-red-600 dark:text-red-400">Rp {{ number_format($val, 0, ',', '.') }}</td>
                            @else
                            <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">{{ number_format($val) }}</td>
                            @endif
                        @endforeach
                        @if(count($branchTable) > 1)
                            @if($row['format'] === 'rupiah')
                            <td class="px-4 py-3 text-right font-mono font-bold text-primary-700 dark:text-primary-400">Rp {{ number_format($row['total'], 0, ',', '.') }}</td>
                            @elseif($row['format'] === 'rupiah_red')
                            <td class="px-4 py-3 text-right font-mono font-bold text-red-600 dark:text-red-400">Rp {{ number_format($row['total'], 0, ',', '.') }}</td>
                            @else
                            <td class="px-4 py-3 text-right font-bold text-primary-700 dark:text-primary-400">{{ number_format($row['total']) }}</td>
                            @endif
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Revenue Trend (full width) --}}
    <div class="rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700/50 dark:bg-gray-900">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h3 class="font-semibold text-gray-800 dark:text-white">Tren Pendapatan Harian</h3>
                <p class="mt-0.5 text-xs text-gray-400">Akumulasi pendapatan bersih per hari dalam periode berjalan</p>
            </div>
            <button @click="exportRevenueTrendXlsx()"
                    class="flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                XLSX
            </button>
        </div>
        <div wire:ignore style="height: 260px">
            <canvas id="chartRevenueTrend"></canvas>
        </div>
    </div>

    {{-- Top Menus + Payment Mix --}}
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-5">
        <div class="rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700/50 dark:bg-gray-900 xl:col-span-3">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-gray-800 dark:text-white">Top 10 Menu Terlaris</h3>
                    <p class="mt-0.5 text-xs text-gray-400">Berdasarkan total kuantitas terjual dalam periode berjalan</p>
                </div>
                <button @click="exportTopMenusXlsx()"
                        class="flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    XLSX
                </button>
            </div>
            <div wire:ignore style="height: 300px">
                <canvas id="chartTopMenus"></canvas>
            </div>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700/50 dark:bg-gray-900 xl:col-span-2">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-gray-800 dark:text-white">Komposisi Metode Pembayaran</h3>
                    <p class="mt-0.5 text-xs text-gray-400">Proporsi pendapatan per metode pembayaran</p>
                </div>
                <button @click="exportPaymentMixXlsx()"
                        class="flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    XLSX
                </button>
            </div>
            <div wire:ignore style="height: 300px">
                <canvas id="chartPaymentMix"></canvas>
            </div>
        </div>
    </div>

    {{-- Peak Hours + Visit Purpose --}}
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700/50 dark:bg-gray-900">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-gray-800 dark:text-white">Distribusi Transaksi per Jam</h3>
                    <p class="mt-0.5 text-xs text-gray-400">Volume transaksi per jam — batang kuning menandai jam tersibuk</p>
                </div>
                <button @click="exportPeakHoursXlsx()"
                        class="flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    XLSX
                </button>
            </div>
            <div wire:ignore style="height: 240px">
                <canvas id="chartPeakHours"></canvas>
            </div>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700/50 dark:bg-gray-900">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-gray-800 dark:text-white">Distribusi Tujuan Kunjungan</h3>
                    <p class="mt-0.5 text-xs text-gray-400">Komposisi transaksi berdasarkan tujuan kunjungan tamu</p>
                </div>
                <button @click="exportVisitPurposeXlsx()"
                        class="flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    XLSX
                </button>
            </div>
            <div wire:ignore style="height: 240px">
                <canvas id="chartVisitPurpose"></canvas>
            </div>
        </div>
    </div>

    {{-- Analisis Kinerja Produk (merged: category + menu drill-down) --}}
    @if(count($categoryDetailMap) > 0)
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white dark:border-gray-700/50 dark:bg-gray-900">

        {{-- Header with filters --}}
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
            <div>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Analisis Kinerja Produk</p>
                <p class="mt-0.5 text-xs text-gray-400">Pendapatan per kategori dan peringkat menu berdasarkan pilihan kategori</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select x-model="selectedCat" @change="onCatChange()"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    <option value="">— Semua Kategori —</option>
                    <template x-for="cat in catOptions" :key="cat">
                        <option :value="cat" x-text="cat"></option>
                    </template>
                </select>
                <template x-if="selectedCat && subCatOptions.length > 0">
                    <select x-model="selectedSubCat" @change="onSubCatChange()"
                            class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">— Semua Sub-Kategori —</option>
                        <template x-for="sc in subCatOptions" :key="sc">
                            <option :value="sc" x-text="sc"></option>
                        </template>
                    </select>
                </template>
                <template x-if="selectedCat">
                    <button @click="selectedCat = ''; selectedSubCat = ''; onCatChange();"
                            class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">× Reset</button>
                </template>
                <button @click="exportCategoriesXlsx()"
                        class="flex items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    XLSX
                </button>
            </div>
        </div>

        <div class="space-y-5 p-5">

            {{-- Section A: Pendapatan per Kategori (always visible) --}}
            <div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Pendapatan per Kategori</p>
                <div wire:ignore style="height: 240px">
                    <canvas id="chartCategories"></canvas>
                </div>
            </div>

            {{-- Section B: Peringkat Menu (visible when category selected) --}}
            <div x-show="selectedCat" class="border-t border-gray-100 pt-5 dark:border-gray-800">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500"
                   x-text="'Peringkat Menu — ' + selectedCat + (selectedSubCat ? ' / ' + selectedSubCat : ' (Semua Sub-Kategori)')"></p>
                <div wire:ignore style="height: 340px">
                    <canvas id="chartFilteredMenus"></canvas>
                </div>
            </div>

        </div>
    </div>
    @endif

    {{-- Promo Performance --}}
    @if(count($promoTable) > 0)
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white dark:border-gray-700/50 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
            <div>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Ringkasan Kinerja Promosi</p>
                <p class="mt-0.5 text-xs text-gray-400">Penggunaan dan dampak per program promosi, diurutkan berdasarkan volume transaksi</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700 dark:bg-violet-500/10 dark:text-violet-400">
                    {{ count($promoTable) }} Program Promosi
                </span>
                <button @click="exportPromoXlsx()"
                        class="flex items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    XLSX
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                        <th class="px-5 py-3 text-left">Nama Promosi</th>
                        <th class="px-5 py-3 text-right">Volume Transaksi</th>
                        <th class="px-5 py-3 text-right">Total Diskon</th>
                        <th class="px-5 py-3 text-right">Total Pendapatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($promoTable as $promo)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                        <td class="px-5 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $promo['name'] }}</td>
                        <td class="px-5 py-3 text-right text-gray-700 dark:text-gray-300">{{ number_format($promo['count']) }}</td>
                        <td class="px-5 py-3 text-right font-mono text-red-600 dark:text-red-400">Rp {{ number_format($promo['discountTotal'], 0, ',', '.') }}</td>
                        <td class="px-5 py-3 text-right font-mono text-gray-800 dark:text-gray-100">Rp {{ number_format($promo['revenue'], 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
                        <td class="px-5 py-3.5 text-sm font-bold text-gray-700 dark:text-gray-200">Total</td>
                        <td class="px-5 py-3.5 text-right font-bold text-gray-700 dark:text-gray-200">{{ number_format(array_sum(array_column($promoTable, 'count'))) }}</td>
                        <td class="px-5 py-3.5 text-right font-mono font-bold text-red-600 dark:text-red-400">Rp {{ number_format(array_sum(array_column($promoTable, 'discountTotal')), 0, ',', '.') }}</td>
                        <td class="px-5 py-3.5 text-right font-mono font-bold text-primary-700 dark:text-primary-400">Rp {{ number_format(array_sum(array_column($promoTable, 'revenue')), 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    {{-- Payment Method Table --}}
    @if(count($paymentTable) > 0)
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white dark:border-gray-700/50 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
            <div>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Rekapitulasi Pembayaran</p>
                <p class="mt-0.5 text-xs text-gray-400">
                    {{ $dateFrom && $dateTo ? \Carbon\Carbon::parse($dateFrom)->isoFormat('D MMM Y').' – '.\Carbon\Carbon::parse($dateTo)->isoFormat('D MMM Y') : ($dateFrom ? 'Dari '.\Carbon\Carbon::parse($dateFrom)->isoFormat('D MMM Y') : ($dateTo ? 'S/d '.\Carbon\Carbon::parse($dateTo)->isoFormat('D MMM Y') : 'Semua periode')) }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-400">
                    {{ count($paymentTable) }} Metode Pembayaran
                </span>
                <button @click="exportPaymentTableXlsx()"
                        class="flex items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    XLSX
                </button>
            </div>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                    <th class="px-5 py-3 text-left">Metode Pembayaran</th>
                    <th class="px-5 py-3 text-left">Tipe</th>
                    <th class="px-5 py-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($paymentTable as $row)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                    <td class="px-5 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $row['name'] }}</td>
                    <td class="px-5 py-3">
                        @if($row['type'])
                        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $row['type'] }}</span>
                        @else
                        <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right font-mono font-semibold text-gray-800 dark:text-gray-100">
                        Rp {{ number_format($row['total'], 0, ',', '.') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
                    <td colspan="2" class="px-5 py-3.5 text-sm font-bold text-gray-700 dark:text-gray-200">Grand Total</td>
                    <td class="px-5 py-3.5 text-right font-mono text-base font-bold text-primary-700 dark:text-primary-400">
                        Rp {{ number_format(array_sum(array_column($paymentTable, 'total')), 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif

    @else
    {{-- Empty state --}}
    <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-white py-16 text-center dark:border-gray-700 dark:bg-gray-900">
        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-50 dark:bg-gray-800">
            <svg class="h-7 w-7 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pilih cabang dan tentukan periode laporan, kemudian klik <strong>Tarik Data</strong> untuk memulai analisis.</p>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Data penjualan akan diambil dari sistem ESB dan ditampilkan dalam grafik secara otomatis.</p>
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>Chart.register(ChartDataLabels);</script>
@endpush

</x-filament-panels::page>
