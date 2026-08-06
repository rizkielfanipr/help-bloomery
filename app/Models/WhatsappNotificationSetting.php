<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappNotificationSetting extends Model
{
    /** @var array<string, string> */
    public const MODULES = [
        'design_request' => 'Request Design',
        'content_request' => 'Request Konten',
        'erp_request' => 'Request ERP/IT',
        'purchase_request' => 'Request Pembelian',
        'service_request' => 'Request Servis (Teknisi)',
    ];

    /** @var array<string, string> */
    public const DEFAULT_TEMPLATES = [
        'design_request' => <<<'TEXT'
Halo, ada permintaan design baru dari {cabang} atas nama {requester}.
Kode: {kode}
Judul: {judul}
Kategori: {kategori}
Brief: {brief}

Detail: {link}
TEXT,
        'content_request' => <<<'TEXT'
Halo, ada permintaan konten baru dari {cabang} atas nama {requester}.
Kode: {kode}
Judul: {judul}
Jenis: {jenis}
Platform Tujuan: {platform}
Tujuan Konten: {tujuan}

Detail: {link}
TEXT,
        'erp_request' => <<<'TEXT'
Halo, ada permintaan ERP baru dari {cabang} atas nama {requester}.
Kode: {kode}
Modul ERP: {modul}
Request Type: {request_type}
Keterangan: {keterangan}

Detail: {link}
TEXT,
        'purchase_request' => <<<'TEXT'
Halo, ada pengajuan pembelian baru dari {cabang} atas nama {requester}.
Kode: {kode}
Nama Barang: {nama_barang}
Jumlah: {jumlah}
Alasan: {alasan}

Detail: {link}
TEXT,
        'service_request' => <<<'TEXT'
Halo, ada permintaan servis baru dari {cabang} atas nama {requester}.
Kode: {kode}
Tanggal Jadwal: {tanggal}
Deskripsi: {deskripsi}

Detail: {link}
TEXT,
    ];

    protected $fillable = [
        'module_key',
        'is_enabled',
        'pic_user_id',
        'message_template',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public static function forModule(string $moduleKey): static
    {
        return static::firstOrCreate(['module_key' => $moduleKey], [
            'is_enabled' => false,
            'message_template' => self::DEFAULT_TEMPLATES[$moduleKey] ?? '',
        ]);
    }

    /** @param  array<string, string>  $values */
    public function renderMessage(array $values): string
    {
        return strtr($this->message_template, collect($values)
            ->mapWithKeys(fn (string $value, string $key): array => ['{'.$key.'}' => $value])
            ->all());
    }
}
