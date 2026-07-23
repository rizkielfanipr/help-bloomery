<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItRequestActivity extends Model
{
    protected $fillable = ['erp_repair_request_id', 'actor_id', 'action', 'from_status', 'to_status', 'notes'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ErpRepairRequest::class, 'erp_repair_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
