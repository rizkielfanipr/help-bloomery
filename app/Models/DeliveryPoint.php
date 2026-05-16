<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Database\Factories\DeliveryPointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryPoint extends Model
{
    /** @use HasFactory<DeliveryPointFactory> */
    use HasFactory;

    protected $fillable = [
        'driver_log_id',
        'urutan',
        'nama_toko',
        'alamat',
        'waktu_tiba',
        'waktu_selesai',
        'jumlah_produk',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
        ];
    }

    public function driverLog(): BelongsTo
    {
        return $this->belongsTo(DriverLog::class);
    }
}
