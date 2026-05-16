<?php

namespace Database\Seeders;

use App\Models\HelpdeskFormField;
use App\Models\HelpdeskFormTemplate;
use Illuminate\Database\Seeder;

class HelpdeskFormTemplateSeeder extends Seeder
{
    private function defaultStatuses(): array
    {
        return [
            ['label' => 'Diajukan', 'value' => 'diajukan', 'color' => 'info'],
            ['label' => 'Sedang Ditinjau', 'value' => 'sedang_ditinjau', 'color' => 'warning'],
            ['label' => 'Sedang Dikerjakan', 'value' => 'sedang_dikerjakan', 'color' => 'primary'],
            ['label' => 'Selesai', 'value' => 'selesai', 'color' => 'success'],
            ['label' => 'Ditolak', 'value' => 'ditolak', 'color' => 'danger'],
        ];
    }

    public function run(): void
    {
        $templates = [
            [
                'name' => 'Permintaan Desain',
                'description' => 'Form untuk mengajukan permintaan desain grafis',
                'icon' => 'heroicon-o-paint-brush',
                'color' => 'purple',
                'statuses' => $this->defaultStatuses(),
                'fields' => [
                    ['label' => 'Judul Permintaan', 'type' => 'text', 'is_required' => true, 'hint' => 'Ringkas dan jelas'],
                    [
                        'label' => 'Kategori Desain',
                        'type' => 'select',
                        'is_required' => true,
                        'options' => [
                            ['label' => 'Social Media', 'value' => 'social_media'],
                            ['label' => 'Banner', 'value' => 'banner'],
                            ['label' => 'Poster', 'value' => 'poster'],
                            ['label' => 'Packaging', 'value' => 'packaging'],
                            ['label' => 'Logo', 'value' => 'logo'],
                            ['label' => 'Brosur', 'value' => 'brosur'],
                            ['label' => 'Presentasi', 'value' => 'presentasi'],
                        ],
                    ],
                    ['label' => 'Ringkasan Brief', 'type' => 'textarea', 'is_required' => true, 'hint' => 'Jelaskan kebutuhan desain secara detail'],
                    ['label' => 'Deadline', 'type' => 'date', 'is_required' => false],
                    ['label' => 'Lampiran / Referensi', 'type' => 'file', 'is_required' => false],
                ],
            ],
            [
                'name' => 'Perbaikan ERP',
                'description' => 'Form untuk melaporkan masalah atau meminta perbaikan pada sistem ERP',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'color' => 'orange',
                'statuses' => $this->defaultStatuses(),
                'fields' => [
                    [
                        'label' => 'Modul ERP',
                        'type' => 'select',
                        'is_required' => true,
                        'options' => [
                            ['label' => 'Keuangan', 'value' => 'finance'],
                            ['label' => 'Pembelian', 'value' => 'purchasing'],
                            ['label' => 'Penjualan', 'value' => 'sales'],
                            ['label' => 'Inventaris', 'value' => 'inventory'],
                            ['label' => 'HRD', 'value' => 'hrd'],
                            ['label' => 'Produksi', 'value' => 'production'],
                            ['label' => 'Lainnya', 'value' => 'other'],
                        ],
                    ],
                    [
                        'label' => 'Prioritas',
                        'type' => 'select',
                        'is_required' => true,
                        'options' => [
                            ['label' => 'Rendah', 'value' => 'low'],
                            ['label' => 'Sedang', 'value' => 'medium'],
                            ['label' => 'Tinggi', 'value' => 'high'],
                            ['label' => 'Kritis', 'value' => 'critical'],
                        ],
                    ],
                    ['label' => 'Deskripsi Masalah', 'type' => 'textarea', 'is_required' => true, 'hint' => 'Jelaskan masalah yang terjadi'],
                    ['label' => 'Screenshot / Bukti', 'type' => 'file', 'is_required' => false],
                ],
            ],
            [
                'name' => 'Permintaan Pembelian',
                'description' => 'Form untuk mengajukan permintaan pembelian barang atau jasa',
                'icon' => 'heroicon-o-shopping-cart',
                'color' => 'green',
                'statuses' => $this->defaultStatuses(),
                'fields' => [
                    ['label' => 'Nama Barang / Jasa', 'type' => 'text', 'is_required' => true],
                    ['label' => 'Jumlah', 'type' => 'number', 'is_required' => true],
                    ['label' => 'Estimasi Harga (Rp)', 'type' => 'number', 'is_required' => false],
                    ['label' => 'Tujuan / Kegunaan', 'type' => 'textarea', 'is_required' => true, 'hint' => 'Jelaskan untuk apa barang/jasa ini dibutuhkan'],
                    ['label' => 'Diperlukan Sebelum', 'type' => 'date', 'is_required' => false],
                    ['label' => 'Lampiran (Quotation / Referensi)', 'type' => 'file', 'is_required' => false],
                ],
            ],
        ];

        foreach ($templates as $templateData) {
            $fields = $templateData['fields'];
            unset($templateData['fields']);

            $template = HelpdeskFormTemplate::firstOrCreate(
                ['name' => $templateData['name']],
                $templateData
            );

            if ($template->wasRecentlyCreated) {
                foreach ($fields as $order => $fieldData) {
                    HelpdeskFormField::create([
                        ...$fieldData,
                        'helpdesk_form_template_id' => $template->id,
                        'sort_order' => $order,
                    ]);
                }
            } elseif ($template->statuses === null) {
                $template->update(['statuses' => $templateData['statuses']]);
            }
        }
    }
}
